<?php

namespace App\Services\Billing\Gateways;

use App\Models\Platform\Payment;
use App\Models\Platform\PaymentGateway;
use Illuminate\Http\Request;

/** Manual bank transfer: the customer pays using the reference; a platform administrator records receipt. */
class BankTransferGateway implements GatewayDriver
{
    public function initiate(Payment $payment, PaymentGateway $gateway): array
    {
        $bank = array_filter(($gateway->configuration ?? []) + config('billing.bank_details'));

        return ['instructions' => [
            'Bank' => $bank['bank'] ?? '',
            'Account name' => $bank['account_name'] ?? '',
            'Account number' => $bank['account_number'] ?? '',
            'Branch' => $bank['branch'] ?? '',
            'Amount' => $payment->currency.' '.number_format((float) $payment->amount, 2),
            'Payment reference' => $payment->transaction_reference,
        ]];
    }

    public function verify(Payment $payment, PaymentGateway $gateway): array
    {
        return ['status' => $payment->status === 'succeeded' ? 'succeeded' : 'pending'];
    }

    public function webhook(Request $request, PaymentGateway $gateway): array
    {
        return ['valid' => false, 'event_id' => '', 'reference' => null, 'status' => 'ignored', 'amount' => null, 'provider_reference' => null];
    }
}
