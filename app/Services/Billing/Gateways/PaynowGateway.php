<?php

namespace App\Services\Billing\Gateways;

use App\Models\Platform\Payment;
use App\Models\Platform\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Paynow Zimbabwe (EcoCash, OneMoney, ZimSwitch, Visa/Mastercard) using its message API:
 * values are posted form-encoded and signed with SHA-512 of the concatenated values + integration key.
 * Configuration: integration_id, integration_key, auth_email (merchant email; required in test mode).
 */
class PaynowGateway implements GatewayDriver
{
    public const INITIATE_URL = 'https://www.paynow.co.zw/interface/initiatetransaction';
    private const PAID = ['paid', 'awaiting delivery', 'delivered'];
    private const FAILED = ['cancelled', 'failed', 'disputed', 'refunded'];

    public function initiate(Payment $payment, PaymentGateway $gateway): array
    {
        $fields = [
            'id' => (string) $gateway->config('integration_id'),
            'reference' => $payment->transaction_reference,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'additionalinfo' => 'acc263 subscription '.$payment->invoice->number,
            'returnurl' => route('billing.payments.return', $payment),
            'resulturl' => route('billing.webhooks', 'paynow'),
            'authemail' => (string) $gateway->config('auth_email', ''),
            'status' => 'Message',
        ];
        $fields['hash'] = self::hash($fields, (string) $gateway->config('integration_key'));

        $response = $this->parse(Http::asForm()->timeout(20)->post(self::INITIATE_URL, $fields)->body());

        if (strtolower($response['status'] ?? '') !== 'ok') {
            throw ValidationException::withMessages(['payment' => 'Paynow could not start the payment: '.($response['error'] ?? 'unknown error').'.']);
        }
        if (!self::validHash($response, (string) $gateway->config('integration_key'))) {
            throw ValidationException::withMessages(['payment' => 'Paynow returned a response that failed verification.']);
        }

        $payment->update(['redirect_url' => $response['browserurl'] ?? null, 'poll_url' => $response['pollurl'] ?? null]);

        return ['redirect' => $response['browserurl']];
    }

    public function verify(Payment $payment, PaymentGateway $gateway): array
    {
        if (!$payment->poll_url) {
            return ['status' => 'pending'];
        }

        $response = $this->parse(Http::asForm()->timeout(20)->post($payment->poll_url)->body());
        if (!self::validHash($response, (string) $gateway->config('integration_key'))) {
            return ['status' => 'pending', 'reason' => 'Unverified response from Paynow'];
        }

        return $this->interpret($response);
    }

    public function webhook(Request $request, PaymentGateway $gateway): array
    {
        // Paynow posts the same fields as a poll response to the result URL; keep their order for the hash.
        $fields = $this->parse($request->getContent()) ?: $request->post();
        $valid = self::validHash($fields, (string) $gateway->config('integration_key'));

        return $this->interpret($fields) + [
            'valid' => $valid,
            'event_id' => ($fields['paynowreference'] ?? '').':'.strtolower($fields['status'] ?? ''),
            'reference' => $fields['reference'] ?? null,
        ];
    }

    private function interpret(array $fields): array
    {
        $status = strtolower($fields['status'] ?? '');

        return [
            'status' => in_array($status, self::PAID, true) ? 'succeeded' : (in_array($status, self::FAILED, true) ? 'failed' : 'pending'),
            'provider_reference' => $fields['paynowreference'] ?? null,
            'amount' => isset($fields['amount']) ? (float) $fields['amount'] : null,
            'reason' => in_array($status, self::FAILED, true) ? 'Paynow status: '.($fields['status'] ?? '') : null,
        ];
    }

    public static function hash(array $fields, string $key): string
    {
        $values = '';
        foreach ($fields as $name => $value) {
            if (strtolower((string) $name) !== 'hash') {
                $values .= $value;
            }
        }

        return strtoupper(hash('sha512', $values.$key));
    }

    public static function validHash(array $fields, string $key): bool
    {
        return isset($fields['hash']) && $key !== '' && hash_equals(self::hash($fields, $key), strtoupper((string) $fields['hash']));
    }

    private function parse(string $body): array
    {
        $fields = [];
        foreach (explode('&', trim($body)) as $pair) {
            if ($pair === '' || !str_contains($pair, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $pair, 2);
            $fields[urldecode($name)] = urldecode($value);
        }

        return $fields;
    }
}
