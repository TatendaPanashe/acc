<?php

namespace App\Services\Billing\Gateways;

use App\Models\Platform\Payment;
use App\Models\Platform\PaymentGateway;
use Illuminate\Http\Request;

interface GatewayDriver
{
    /**
     * Starts a payment with the provider.
     * @return array{redirect?: string, instructions?: array<string, string>}
     */
    public function initiate(Payment $payment, PaymentGateway $gateway): array;

    /**
     * Asks the provider for the payment's current state.
     * @return array{status: string, provider_reference?: ?string, amount?: ?float, reason?: ?string} status: succeeded|failed|pending
     */
    public function verify(Payment $payment, PaymentGateway $gateway): array;

    /**
     * Validates and interprets a webhook/callback.
     * @return array{valid: bool, event_id: string, reference: ?string, status: string, amount: ?float, provider_reference: ?string}
     */
    public function webhook(Request $request, PaymentGateway $gateway): array;
}
