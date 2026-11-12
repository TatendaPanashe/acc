<?php

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\Platform\Coupon;
use App\Models\Platform\Payment;
use App\Models\Platform\PaymentGateway;
use App\Models\Platform\PaymentWebhook;
use App\Models\Platform\Plan;
use App\Models\Platform\PlatformInvoice;
use App\Models\Platform\Subscription;
use App\Services\Billing\Gateways\BankTransferGateway;
use App\Services\Billing\Gateways\GatewayDriver;
use App\Services\Billing\Gateways\PaynowGateway;
use App\Services\Billing\Gateways\PesepayGateway;
use App\Services\Billing\Gateways\SandboxGateway;
use App\Services\Billing\Gateways\StripeGateway;
use App\Services\Platform\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Invoice → payment → verification → activation/renewal (see docs: subscription lifecycle).
 */
class BillingService
{
    public const DRIVERS = [
        'pesepay' => PesepayGateway::class,
        'paynow' => PaynowGateway::class,
        'stripe' => StripeGateway::class,
        'bank_transfer' => BankTransferGateway::class,
        'sandbox' => SandboxGateway::class,
    ];

    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    public function driver(string $provider): GatewayDriver
    {
        $class = self::DRIVERS[$provider] ?? throw ValidationException::withMessages(['provider' => 'Unknown payment provider.']);

        return app($class);
    }

    // ---- invoices -------------------------------------------------------------

    /** Trial (or locked) business chooses a plan: an activation invoice to pay now. */
    public function checkout(Company $company, Plan $plan, string $cycle, ?string $couponCode = null): PlatformInvoice
    {
        if (!$plan->is_active || $plan->is_custom) {
            throw ValidationException::withMessages(['plan' => $plan->is_custom ? 'Contact us for '.$plan->name.' pricing.' : 'That plan is not available.']);
        }

        $coupon = $this->coupon($couponCode, $plan);
        $sub = $company->subscription ?? $this->subscriptions->startTrial($company, $plan);

        return DB::transaction(function () use ($company, $sub, $plan, $cycle, $coupon) {
            // Replace any unpaid activation invoice with the new choice.
            $sub->invoices()->where('purpose', 'activation')->where('status', 'pending')->update(['status' => 'void']);

            $start = now();

            return $this->createInvoice($company, $sub, 'activation', $plan, $cycle, $plan->priceFor($cycle), $coupon, $start, $start->copy()->add($cycle === 'annual' ? '1 year' : '1 month'), $start);
        });
    }

    /** Next period's invoice for an active subscription (issued before renewal). */
    public function renewalInvoice(Subscription $sub): ?PlatformInvoice
    {
        $periodStart = $sub->current_period_ends_at?->copy();
        if (!$periodStart || $sub->is_promotional || !in_array($sub->status, Subscription::PAYING, true)) {
            return null;
        }

        $existing = $sub->invoices()->where('purpose', 'renewal')->whereDate('period_start', $periodStart->toDateString())->whereIn('status', ['pending', 'paid'])->first();
        if ($existing) {
            return $existing;
        }

        // Subscription items already carry any "forever" discount, so no coupon is re-applied here.
        $coupon = null;
        $amount = (float) $sub->items()->get()->sum(fn ($item) => $item->total()) ?: (float) $sub->price;

        return $this->createInvoice($sub->company, $sub, 'renewal', $sub->plan, $sub->billing_cycle, $amount, $coupon, $periodStart,
            $periodStart->copy()->add($sub->billing_cycle === 'annual' ? '1 year' : '1 month'), $periodStart, countRedemption: false);
    }

    private function createInvoice(Company $company, Subscription $sub, string $purpose, Plan $plan, string $cycle, float $subtotal, ?Coupon $coupon, Carbon $from, Carbon $to, Carbon $due, bool $countRedemption = true): PlatformInvoice
    {
        $discount = $coupon ? $coupon->discountOn($subtotal) : 0.0;
        $lines = [['description' => $plan->name.' plan, '.$cycle.' ('.$from->format('d M Y').' – '.$to->copy()->subDay()->format('d M Y').')', 'amount' => round($subtotal, 2)]];
        if ($discount > 0) {
            $lines[] = ['description' => 'Coupon '.$coupon->code.' ('.$coupon->label().')', 'amount' => -$discount];
        }

        $invoice = PlatformInvoice::create([
            'company_id' => $company->id,
            'platform_subscription_id' => $sub->id,
            'number' => $this->nextNumber(),
            'purpose' => $purpose,
            'platform_plan_id' => $plan->id,
            'billing_cycle' => $cycle,
            'subtotal' => round($subtotal, 2),
            'discount_total' => $discount,
            'coupon_id' => $coupon?->id,
            'lines' => $lines,
            'amount' => round($subtotal - $discount, 2),
            'currency' => $plan->currency,
            'status' => 'pending',
            'period_start' => $from->toDateString(),
            'period_end' => $to->copy()->subDay()->toDateString(),
            'issued_at' => now(),
            'due_at' => $due,
        ]);

        if ($coupon && $countRedemption) {
            $coupon->increment('times_redeemed');
        }

        // A fully discounted invoice needs no payment.
        if ((float) $invoice->amount <= 0) {
            $this->settle($invoice, 'coupon', null);
        }

        return $invoice->fresh();
    }

    private function coupon(?string $code, Plan $plan): ?Coupon
    {
        if (blank($code)) {
            return null;
        }

        $coupon = Coupon::whereRaw('UPPER(code) = ?', [Str::upper(trim($code))])->first();
        $problem = $coupon ? $coupon->problemFor($plan) : 'That coupon code is not valid.';
        if ($problem) {
            throw ValidationException::withMessages(['coupon' => $problem]);
        }

        return $coupon;
    }

    private function nextNumber(): string
    {
        $prefix = 'ACC-'.now()->format('Ym').'-';
        $last = PlatformInvoice::where('number', 'like', $prefix.'%')->orderByDesc('number')->value('number');

        return $prefix.str_pad((string) ((int) Str::afterLast((string) $last, '-') + 1), 4, '0', STR_PAD_LEFT);
    }

    // ---- payments ------------------------------------------------------------

    /** @return array{payment: Payment, redirect?: string, instructions?: array} */
    public function startPayment(PlatformInvoice $invoice, string $provider): array
    {
        if (!$invoice->isOpen()) {
            throw ValidationException::withMessages(['invoice' => 'This invoice is not awaiting payment.']);
        }

        $gateway = PaymentGateway::where('provider', $provider)->where('status', true)->first()
            ?? throw ValidationException::withMessages(['provider' => 'That payment method is not available.']);

        $payment = Payment::create([
            'platform_invoice_id' => $invoice->id,
            'company_id' => $invoice->company_id,
            'provider' => $provider,
            'transaction_reference' => 'PAY-'.$invoice->id.'-'.Str::upper(Str::random(8)),
            'amount' => $invoice->amount,
            'currency' => $invoice->currency,
            'status' => 'pending',
        ]);

        try {
            $result = $this->driver($provider)->initiate($payment->load('invoice'), $gateway);
        } catch (\Throwable $e) {
            $payment->update(['status' => 'failed', 'failure_reason' => Str::limit($e->getMessage(), 250)]);
            throw $e instanceof ValidationException ? $e : ValidationException::withMessages(['payment' => 'The payment provider could not be reached. Please try again.']);
        }

        if (isset($result['instructions'])) {
            $payment->update(['meta' => ['instructions' => $result['instructions']]]);
        }

        return ['payment' => $payment->fresh()] + $result;
    }

    /** Verify with the provider (return URL / manual check). */
    public function verify(Payment $payment): Payment
    {
        if ($payment->status !== 'pending') {
            return $payment;
        }

        $gateway = PaymentGateway::where('provider', $payment->provider)->firstOrFail();
        $result = $this->driver($payment->provider)->verify($payment, $gateway);

        return $this->applyResult($payment, $result);
    }

    public function handleWebhook(string $provider, Request $request): PaymentWebhook
    {
        $gateway = PaymentGateway::where('provider', $provider)->firstOrFail();
        $result = $this->driver($provider)->webhook($request, $gateway);
        $payload = $request->all() ?: ['raw' => $request->getContent()];

        // Unverified calls are logged but never share an event id with genuine ones,
        // so a forged request cannot block the real delivery.
        if (!$result['valid']) {
            return PaymentWebhook::create([
                'provider' => $provider, 'event_id' => 'invalid:'.Str::uuid(), 'payload' => $payload,
                'signature_valid' => false, 'error' => 'Invalid signature', 'processed_at' => now(),
            ]);
        }

        $eventId = $result['event_id'] !== '' ? $result['event_id'] : sha1($request->getContent());
        $webhook = PaymentWebhook::firstOrCreate(
            ['provider' => $provider, 'event_id' => Str::limit($eventId, 190, '')],
            ['payload' => $payload, 'signature_valid' => true],
        );

        if ($webhook->processed_at) {
            return $webhook; // already handled: providers retry deliveries
        }

        $payment = $result['reference'] ? Payment::where('transaction_reference', $result['reference'])->first() : null;
        if (!$payment) {
            $webhook->update(['error' => $result['status'] === 'ignored' ? 'Event not used' : 'Unknown payment reference', 'processed_at' => now()]);

            return $webhook;
        }

        $this->applyResult($payment, $result);
        $webhook->update(['processed_at' => now()]);

        return $webhook;
    }

    /** Platform administrator confirms a bank transfer (or other offline payment). */
    public function recordManualPayment(PlatformInvoice $invoice, string $provider, string $reference): Payment
    {
        $payment = Payment::create([
            'platform_invoice_id' => $invoice->id,
            'company_id' => $invoice->company_id,
            'provider' => $provider,
            'transaction_reference' => 'MAN-'.$invoice->id.'-'.Str::upper(Str::random(6)),
            'provider_reference' => $reference,
            'amount' => $invoice->amount,
            'currency' => $invoice->currency,
            'status' => 'pending',
        ]);

        return $this->applyResult($payment, ['status' => 'succeeded', 'amount' => (float) $invoice->amount, 'provider_reference' => $reference]);
    }

    public function simulate(Payment $payment, bool $succeed): Payment
    {
        return $this->applyResult($payment, $succeed
            ? ['status' => 'succeeded', 'amount' => (float) $payment->amount, 'provider_reference' => 'SANDBOX-'.Str::upper(Str::random(6))]
            : ['status' => 'failed', 'reason' => 'Declined (sandbox)']);
    }

    private function applyResult(Payment $payment, array $result): Payment
    {
        return DB::transaction(function () use ($payment, $result) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== 'pending') {
                return $payment;
            }

            if ($result['status'] === 'succeeded') {
                if (isset($result['amount']) && $result['amount'] !== null && abs($result['amount'] - (float) $payment->amount) > 0.009) {
                    $payment->update(['status' => 'failed', 'failure_reason' => 'Amount paid ('.$result['amount'].') does not match the invoice ('.$payment->amount.')']);

                    return $payment;
                }

                $payment->update(['status' => 'succeeded', 'paid_at' => now(), 'provider_reference' => $result['provider_reference'] ?? $payment->provider_reference]);
                $this->settle($payment->invoice, $payment->provider, $payment);
            } elseif ($result['status'] === 'failed') {
                $payment->update(['status' => 'failed', 'failure_reason' => $result['reason'] ?? 'Payment failed']);
            }

            return $payment->fresh();
        });
    }

    /** Invoice paid: activate (first payment / plan choice) or renew (next period). */
    private function settle(PlatformInvoice $invoice, string $provider, ?Payment $payment): void
    {
        $invoice = PlatformInvoice::lockForUpdate()->findOrFail($invoice->id);
        if ($invoice->status === 'paid') {
            return;
        }

        $invoice->update(['status' => 'paid', 'paid_at' => now(), 'provider' => $provider, 'provider_reference' => $payment?->provider_reference]);
        $sub = $invoice->subscription;

        if ($invoice->purpose === 'activation') {
            // A "forever" coupon lowers the recurring price (and MRR); a one-off coupon only this invoice.
            $forever = $invoice->coupon && $invoice->coupon->duration === 'forever';
            $this->subscriptions->activate($sub, $invoice->plan, $invoice->billing_cycle ?? 'monthly', $provider,
                $forever ? (float) $invoice->amount : null, $forever ? $invoice->coupon_id : null);
        } else {
            $this->subscriptions->renew($sub, $provider);
        }
    }
}
