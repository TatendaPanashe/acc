<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Platform\Payment;
use App\Models\Platform\PaymentGateway;
use App\Models\Platform\Plan;
use App\Models\Platform\PlatformInvoice;
use App\Services\Billing\BillingService;
use App\Services\Billing\Entitlements;
use App\Services\Billing\SubscriptionAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The company admin's subscription: plan choice, invoices and payment. */
class BillingController extends Controller
{
    public function __construct(private readonly BillingService $billing)
    {
    }

    public function index(Request $request, Entitlements $entitlements, SubscriptionAccess $access): View
    {
        $company = $request->user()->company;
        $subscription = $company->subscription?->load('plan');

        return view('billing.index', [
            'company' => $company,
            'subscription' => $subscription,
            'access' => $access->check($subscription),
            'plans' => Plan::with('features')->where('is_active', true)->orderBy('sort_order')->get(),
            'usage' => $entitlements->usageSummary($company),
            'invoices' => $company->platformInvoices()->whereIn('status', ['pending', 'paid'])->latest('issued_at')->take(12)->get(),
            'openInvoice' => $company->platformInvoices()->where('status', 'pending')->latest('issued_at')->first(),
        ]);
    }

    public function locked(Request $request, SubscriptionAccess $access): View|RedirectResponse
    {
        $result = $access->check($request->user()->company?->subscription);
        if ($result['state'] === 'open') {
            return redirect()->route('dashboard');
        }

        return view('billing.locked', ['access' => $result]);
    }

    public function checkout(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', Rule::exists('platform_plans', 'code')->where('is_active', 1)],
            'billing_cycle' => ['required', Rule::in(['monthly', 'annual'])],
            'coupon' => ['nullable', 'string', 'max:40'],
        ]);

        $invoice = $this->billing->checkout($request->user()->company, Plan::where('code', $data['plan'])->firstOrFail(), $data['billing_cycle'], $data['coupon'] ?? null);

        return $invoice->status === 'paid'
            ? redirect()->route('billing.index')->with('success', 'Your '.$invoice->plan->name.' subscription is active.')
            : redirect()->route('billing.invoices.show', $invoice);
    }

    public function invoice(Request $request, PlatformInvoice $invoice): View
    {
        $this->authorizeInvoice($request, $invoice);

        return view('billing.invoice', [
            'invoice' => $invoice->load(['payments', 'plan']),
            'gateways' => PaymentGateway::enabled(),
            'instructions' => $invoice->payments->firstWhere(fn ($p) => $p->status === 'pending' && isset($p->meta['instructions']))?->meta['instructions'] ?? null,
        ]);
    }

    public function pay(Request $request, PlatformInvoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);
        $data = $request->validate(['provider' => ['required', 'string']]);
        $result = $this->billing->startPayment($invoice, $data['provider']);

        return isset($result['redirect'])
            ? redirect()->away($result['redirect'])
            : redirect()->route('billing.invoices.show', $invoice)->with('success', 'Use the details below to pay. Your subscription activates as soon as the payment is confirmed.');
    }

    /** Customer returns from the provider: verify the payment with the provider before trusting it. */
    public function returned(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorizePayment($request, $payment);
        $payment = $this->billing->verify($payment);

        return redirect()->route('billing.invoices.show', $payment->platform_invoice_id)->with(...match ($payment->status) {
            'succeeded' => ['success', 'Payment received. Thank you, your subscription is active.'],
            'failed' => ['error', 'The payment did not go through: '.($payment->failure_reason ?? 'declined').'. You can try again.'],
            default => ['warning', 'Your payment is still being processed. This page will show it as paid once the provider confirms.'],
        });
    }

    public function webhook(Request $request, string $provider): Response
    {
        abort_unless(array_key_exists($provider, BillingService::DRIVERS), 404);
        $webhook = $this->billing->handleWebhook($provider, $request);

        // Providers retry on non-2xx; reject only bad signatures.
        return response($webhook->signature_valid ? 'OK' : 'Invalid signature', $webhook->signature_valid ? 200 : 400);
    }

    public function sandbox(Request $request, Payment $payment): View
    {
        $this->authorizeSandbox($request, $payment);

        return view('billing.sandbox', ['payment' => $payment->load('invoice')]);
    }

    public function sandboxComplete(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorizeSandbox($request, $payment);
        $this->billing->simulate($payment, $request->input('outcome') === 'success');

        return redirect()->route('billing.payments.return', $payment);
    }

    private function authorizeInvoice(Request $request, PlatformInvoice $invoice): void
    {
        abort_unless($invoice->company_id === $request->user()->company_id, 404);
    }

    private function authorizePayment(Request $request, Payment $payment): void
    {
        abort_unless($payment->company_id === $request->user()->company_id, 404);
    }

    private function authorizeSandbox(Request $request, Payment $payment): void
    {
        $this->authorizePayment($request, $payment);
        abort_unless($payment->provider === 'sandbox' && !app()->isProduction()
            && PaymentGateway::where('provider', 'sandbox')->where('status', true)->exists(), 404);
    }
}
