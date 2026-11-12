<?php

namespace App\Services\Billing\Gateways;

use App\Models\Platform\Payment;
use App\Models\Platform\PaymentGateway;
use Codevirtus\Payments\Pesepay;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Pesepay (EcoCash, InnBucks, ZimSwitch, Visa/Mastercard) via the codevirtus/pesepay SDK, redirect checkout.
 * Configuration: integration_key, encryption_key (16, 24 or 32 characters); mode "test" uses the Pesepay sandbox.
 *
 * Pesepay's result-URL call carries no signature we can check, so a callback is only treated as a hint:
 * the payment is always re-checked with Pesepay server-to-server before anything is applied.
 */
class PesepayGateway implements GatewayDriver
{
    public const PAID = ['SUCCESS'];
    public const FAILED = ['FAILED', 'CANCELLED', 'DECLINED', 'ERROR', 'TIME_OUT', 'TIMED_OUT', 'REVERSED', 'CLOSED', 'CLOSED_PERIOD_ELAPSED', 'AUTHORIZATION_FAILED', 'INSUFFICIENT_FUNDS', 'SERVICE_UNAVAILABLE', 'TERMINATED'];

    public function __construct(private readonly PesepayClientFactory $factory)
    {
    }

    public function initiate(Payment $payment, PaymentGateway $gateway): array
    {
        $client = $this->client($gateway);
        $client->returnUrl = route('billing.payments.return', $payment);
        $client->resultUrl = route('billing.webhooks', 'pesepay');

        $transaction = $client->createTransaction(
            round((float) $payment->amount, 2),
            $payment->currency ?: 'USD',
            'acc263 subscription '.$payment->invoice->number,
            $payment->transaction_reference,
        );
        $response = $client->initiateTransaction($transaction);

        if (!$response->success()) {
            throw ValidationException::withMessages(['payment' => 'Pesepay could not start the payment: '.rtrim((string) ($response->message() ?: 'unknown error'), '.').'.']);
        }
        if (!$response->redirectUrl()) {
            throw ValidationException::withMessages(['payment' => 'Pesepay did not return a checkout page.']);
        }

        $payment->update([
            'provider_reference' => $response->referenceNumber(),
            'poll_url' => $response->pollUrl(),
            'redirect_url' => $response->redirectUrl(),
        ]);

        return ['redirect' => $response->redirectUrl()];
    }

    public function verify(Payment $payment, PaymentGateway $gateway): array
    {
        $client = $this->client($gateway);
        if ($payment->poll_url) {
            $response = $client->pollTransaction($payment->poll_url);
        } elseif ($payment->provider_reference) {
            $response = $client->checkPayment($payment->provider_reference);
        } else {
            return ['status' => 'pending'];
        }

        if (!$response->success()) {
            return ['status' => 'pending', 'reason' => 'Pesepay: '.$response->message()];
        }

        $data = $response->rawData();
        // The poll answer must be about this payment (guards against a mixed-up poll URL).
        if (($data['merchantReference'] ?? $payment->transaction_reference) !== $payment->transaction_reference
            || ($payment->provider_reference && ($data['referenceNumber'] ?? null) !== $payment->provider_reference)) {
            return ['status' => 'pending', 'reason' => 'Pesepay answered for a different transaction'];
        }

        return $this->interpret($data, $payment);
    }

    public function webhook(Request $request, PaymentGateway $gateway): array
    {
        $referenceNumber = $this->callbackReference($request, $gateway);
        $payment = $referenceNumber
            ? Payment::where('provider', 'pesepay')->where('provider_reference', $referenceNumber)->first()
            : null;

        // Unknown or unreadable callbacks are rejected: nothing to re-check against.
        if (!$payment) {
            return ['valid' => false, 'event_id' => '', 'reference' => null, 'status' => 'ignored', 'amount' => null, 'provider_reference' => $referenceNumber];
        }

        $result = $this->verify($payment, $gateway);

        return $result + [
            'valid' => true,
            'event_id' => $referenceNumber.':'.$result['status'],
            'reference' => $payment->transaction_reference,
            'amount' => null,
            'provider_reference' => $referenceNumber,
        ];
    }

    private function interpret(array $data, Payment $payment): array
    {
        $status = strtoupper((string) ($data['transactionStatus'] ?? ''));
        $amount = Arr::get($data, 'amountDetails.amount');
        $currency = Arr::get($data, 'amountDetails.currencyCode');

        if (in_array($status, self::PAID, true) && $currency && strtoupper($currency) !== strtoupper($payment->currency ?: 'USD')) {
            return ['status' => 'failed', 'reason' => 'Paid in '.$currency.' but the invoice is in '.($payment->currency ?: 'USD')];
        }

        return [
            'status' => in_array($status, self::PAID, true) ? 'succeeded' : (in_array($status, self::FAILED, true) ? 'failed' : 'pending'),
            'provider_reference' => $data['referenceNumber'] ?? $payment->provider_reference,
            'amount' => $amount !== null ? (float) $amount : null,
            'reason' => in_array($status, self::FAILED, true)
                ? 'Pesepay: '.($data['transactionStatusDescription'] ?? ucfirst(strtolower(str_replace('_', ' ', $status))))
                : null,
        ];
    }

    /** Pesepay's reference number from the callback body: plain JSON, form fields, or an encrypted "payload". */
    private function callbackReference(Request $request, PaymentGateway $gateway): ?string
    {
        $data = json_decode($request->getContent(), true);
        $data = is_array($data) ? $data : $request->all();

        if (isset($data['payload']) && is_string($data['payload'])) {
            $decrypted = $this->decrypt($data['payload'], $this->encryptionKey($gateway));
            $data = is_array($decrypted) ? $decrypted : [];
        }

        $reference = $data['referenceNumber'] ?? null;

        return is_string($reference) && $reference !== '' ? $reference : null;
    }

    /** Same scheme as the SDK (AES-256-CBC, IV = first 16 characters of the key); the SDK keeps its own private. */
    private function decrypt(string $cipherText, string $key): ?array
    {
        if (!in_array(strlen($key), [16, 24, 32], true)) {
            return null;
        }
        $plain = openssl_decrypt(base64_decode($cipherText), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, substr($key, 0, 16));
        $decoded = $plain === false ? null : json_decode($plain, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function client(PaymentGateway $gateway): Pesepay
    {
        $integrationKey = (string) ($gateway->config('integration_key') ?: config('services.pesepay.integration_key'));
        $encryptionKey = $this->encryptionKey($gateway);

        if ($integrationKey === '' || !in_array(strlen($encryptionKey), [16, 24, 32], true)) {
            throw ValidationException::withMessages(['payment' => 'Pesepay is not configured yet. Please choose another payment method.']);
        }

        return $this->factory->make($integrationKey, $encryptionKey, $gateway->mode !== 'live');
    }

    private function encryptionKey(PaymentGateway $gateway): string
    {
        return (string) ($gateway->config('encryption_key') ?: config('services.pesepay.encryption_key'));
    }
}
