<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Platform\Plan;
use App\Models\Platform\Subscription;
use App\Services\Platform\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Platform administrator's view of tenant businesses and their subscriptions. */
class TenantController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    public function index(Request $request): View
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('q'));

        $tenants = Company::with(['subscription.plan'])
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when($request->filled('plan'), fn ($q) => $q->whereHas('subscription', fn ($s) => $s->where('platform_plan_id', $request->integer('plan'))))
            ->when($status === 'trial_expired', fn ($q) => $q->whereHas('subscription', fn ($s) => $s->where('status', 'expired')->orWhere(fn ($s) => $s->where('status', 'trialing')->where('trial_ends_at', '<', now()))))
            ->when($status === 'trialing', fn ($q) => $q->whereHas('subscription', fn ($s) => $s->where('status', 'trialing')->where('trial_ends_at', '>=', now())))
            ->when($status === 'promotional', fn ($q) => $q->whereHas('subscription', fn ($s) => $s->where('is_promotional', true)->whereIn('status', Subscription::PAYING)))
            ->when($status && !in_array($status, ['trial_expired', 'trialing', 'promotional'], true), fn ($q) => $q->whereHas('subscription', fn ($s) => $s->where('status', $status)))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('platform.tenants.index', [
            'tenants' => $tenants,
            'plans' => Plan::orderBy('sort_order')->get(),
            'statuses' => collect(Subscription::STATUSES)->except('expired')->all() + ['trial_expired' => 'Trial ended', 'promotional' => 'Promotional'],
        ]);
    }

    public function show(Company $company): View
    {
        $subscription = $this->subscriptionFor($company);

        return view('platform.tenants.show', [
            'company' => $company->loadCount('users'),
            'subscription' => $subscription->load('plan'),
            'plans' => Plan::where('is_active', true)->orderBy('sort_order')->get(),
            'invoices' => $company->platformInvoices()->with('payments')->latest('issued_at')->paginate(15),
            'events' => $subscription->events()->with('user')->take(30)->get(),
            'lifetimeRevenue' => (float) $company->platformInvoices()->where('status', 'paid')->sum('amount'),
        ]);
    }

    public function changePlan(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'platform_plan_id' => ['required', Rule::exists('platform_plans', 'id')->where('is_active', 1)],
            'billing_cycle' => ['required', Rule::in(['monthly', 'annual'])],
            'note' => ['nullable', 'max:255'],
            'custom_price' => ['nullable', 'numeric', 'min:0'],
            'limits.*' => ['nullable', 'integer', 'min:1'],
        ]);
        $overrides = collect($data['limits'] ?? [])->filter(fn ($v) => $v !== null && $v !== '')->map(fn ($v) => (int) $v)->all();
        $sub = $this->subscriptions->changePlan($this->subscriptionFor($company), Plan::findOrFail($data['platform_plan_id']), $data['billing_cycle'],
            $data['note'] ?? null, isset($data['custom_price']) ? (float) $data['custom_price'] : null, $overrides ?: null);

        return back()->with('success', $company->name.' is now on '.$sub->plan->name.' ('.$sub->billing_cycle.').');
    }

    public function grantPromotion(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'platform_plan_id' => ['required', Rule::exists('platform_plans', 'id')->where('is_active', 1)],
            'months' => ['required', 'integer', 'between:1,36'],
            'note' => ['required', 'max:255'],
        ]);
        $sub = $this->subscriptions->grantPromotion($this->subscriptionFor($company), Plan::findOrFail($data['platform_plan_id']), (int) $data['months'], $data['note']);

        return back()->with('success', 'Promotional '.$sub->plan->name.' subscription granted until '.$sub->promo_ends_at->format('d M Y').'.');
    }

    public function extendTrial(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate(['days' => ['required', 'integer', 'between:1,180'], 'note' => ['nullable', 'max:255']]);
        $sub = $this->subscriptions->extendTrial($this->subscriptionFor($company), (int) $data['days'], $data['note'] ?? null);

        return back()->with('success', 'Trial extended to '.$sub->trial_ends_at->format('d M Y').'.');
    }

    public function suspend(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'max:255']]);
        $this->subscriptions->suspend($this->subscriptionFor($company), $data['reason'], 'admin');

        return back()->with('success', $company->name.' has been suspended. Its users can no longer sign in.');
    }

    public function reactivate(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'max:255']]);
        $this->subscriptions->reactivate($this->subscriptionFor($company), $data['note'] ?? null);

        return back()->with('success', $company->name.' has been reactivated.');
    }

    /** Confirms an offline payment (e.g. bank transfer) against an open invoice. */
    public function recordPayment(Request $request, Company $company, \App\Models\Platform\PlatformInvoice $invoice): RedirectResponse
    {
        abort_unless($invoice->company_id === $company->id && $invoice->isOpen(), 404);
        $data = $request->validate([
            'provider' => ['required', Rule::in(array_keys(config('platform.payment_providers')))],
            'reference' => ['required', 'max:120'],
        ]);
        app(\App\Services\Billing\BillingService::class)->recordManualPayment($invoice, $data['provider'], $data['reference']);

        return back()->with('success', 'Payment recorded for invoice '.$invoice->number.'.');
    }

    public function cancel(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'max:255']]);
        $this->subscriptions->cancel($this->subscriptionFor($company), $data['reason']);

        return back()->with('success', 'Subscription cancelled.');
    }

    /** Older companies may predate platform billing; give them a trial record so they can be managed. */
    private function subscriptionFor(Company $company): Subscription
    {
        return $company->subscription ?? $this->subscriptions->startTrial($company);
    }
}
