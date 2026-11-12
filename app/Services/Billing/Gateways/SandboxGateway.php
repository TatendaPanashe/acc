<?php

namespace App\Services\Billing\Gateways;

use App\Models\Platform\Payment;
use App\Models\Platform\PaymentGateway;
use Illuminate\Http\Request;

/** Test gateway: a local page lets you simulate a successful or failed payment. Never enable in production. */
class SandboxGateway implements GatewayDriver
{
    public function initiate(Payment $payment, PaymentGateway $gateway): array
    {
        return ['redirect' => route('billing.sandbox.show', $payment)];
    }

    public function verify(Payment $payment, PaymentGateway $gateway): array
    {
        return ['status' => $payment->status === 'succeeded' ? 'succeeded' : ($payment->status === 'failed' ? 'failed' : 'pending')];
    }

    public function webhook(Request $request, PaymentGateway $gateway): array
    {
        return ['valid' => false, 'event_id' => '', 'reference' => null, 'status' => 'ignored', 'amount' => null, 'provider_reference' => null];
    }
}
