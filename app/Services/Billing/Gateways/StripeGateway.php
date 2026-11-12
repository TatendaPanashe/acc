<?php

namespace App\Services\Billing\Gateways;

use App\Models\Platform\Payment;
use App\Models\Platform\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Stripe Checkout (cards). Configuration: secret_key, webhook_secret.
 * Webhook signatures are verified (Stripe-Signature: t=…,v1=HMAC-SHA256(t.payload)).
 */
class StripeGateway implements GatewayDriver
{
    public const API = 'https://api.stripe.com/v1';
    private const TOLERANCE_SECONDS = 300;

    public function initiate(Payment $payment, PaymentGateway $gateway): array
    {
        $response = Http::asForm()->withToken((string) $gateway->config('secret_key'))->timeout(20)->post(self::API.'/checkout/sessions', [
            'mode' => 'payment',
            'client_reference_id' => $payment->transaction_reference,
            'success_url' => route('billing.payments.return', $payment),
            'cancel_url' => route('billing.invoices.show', $payment->platform_invoice_id),
            'metadata' => ['reference' => $payment->transaction_reference],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($payment->currency),
                    'unit_amount' => (int) round((float) $payment->amount * 100),
                    'product_data' => ['name' => 'acc263 subscription '.$payment->invoice->number],
                ],
            ]],
        ]);

        if (!$response->successful() || !$response->json('url')) {
            throw ValidationException::withMessages(['payment' => 'Stripe could not start the payment: '.($response->json('error.message') ?? 'unknown error').'.']);
        }

        $payment->update(['provider_reference' => $response->json('id'), 'redirect_url' => $response->json('url')]);

        return ['redirect' => $response->json('url')];
    }

    public function verify(Payment $payment, PaymentGateway $gateway): array
    {
        if (!$payment->provider_reference) {
            return ['status' => 'pending'];
        }

        $session = Http::withToken((string) $gateway->config('secret_key'))->timeout(20)
            ->get(self::API.'/checkout/sessions/'.$payment->provider_reference)->json();

        return [
            'status' => ($session['payment_status'] ?? null) === 'paid' ? 'succeeded' : (($session['status'] ?? null) === 'expired' ? 'failed' : 'pending'),
            'provider_reference' => $session['id'] ?? $payment->provider_reference,
            'amount' => isset($session['amount_total']) ? $session['amount_total'] / 100 : null,
            'reason' => ($session['status'] ?? null) === 'expired' ? 'Checkout session expired' : null,
        ];
    }

    public function webhook(Request $request, PaymentGateway $gateway): array
    {
        $payload = $request->getContent();
        $valid = self::validSignature($payload, (string) $request->header('Stripe-Signature'), (string) $gateway->config('webhook_secret'));
        $event = json_decode($payload, true) ?: [];
        $object = $event['data']['object'] ?? [];

        $status = match ($event['type'] ?? '') {
            'checkout.session.completed' => ($object['payment_status'] ?? '') === 'paid' ? 'succeeded' : 'pending',
            'checkout.session.async_payment_succeeded' => 'succeeded',
            'checkout.session.async_payment_failed', 'checkout.session.expired' => 'failed',
            default => 'ignored',
        };

        return [
            'valid' => $valid,
            'event_id' => (string) ($event['id'] ?? sha1($payload)),
            'reference' => $object['client_reference_id'] ?? ($object['metadata']['reference'] ?? null),
            'status' => $status,
            'amount' => isset($object['amount_total']) ? $object['amount_total'] / 100 : null,
            'provider_reference' => $object['id'] ?? null,
            'reason' => $status === 'failed' ? 'Stripe: '.($event['type'] ?? '') : null,
        ];
    }

    public static function validSignature(string $payload, string $header, string $secret): bool
    {
        if ($secret === '' || $header === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$k][] = $v;
        }
        $timestamp = (int) ($parts['t'][0] ?? 0);
        if ($timestamp === 0 || abs(time() - $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        foreach ($parts['v1'] ?? [] as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
