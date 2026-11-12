<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BillingRun;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Item;
use App\Modules\Accounting\Models\Subscription;
use App\Modules\Accounting\Services\AccountMappingService;
use App\Modules\Accounting\Services\Annuity\RecurringBillingService;
use App\Rules\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Recurring customer contracts, billing runs and renewals. */
class AnnuityBillingController extends Controller
{
    public function __construct(private readonly RecurringBillingService $billing)
    {
    }

    public function index(Request $request): View
    {
        $status = $request->query('status');
        $contracts = Subscription::with('customer')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")
            ->orderBy('next_invoice_on')
            ->paginate(25)->withQueryString();
        $active = Subscription::where('status', 'active')->get();
        $due = $this->billing->due(today());
        $mrr = round($active->sum(fn ($s) => $s->monthlyValueInBase()), 2);

        return view('accounting.annuity.index', [
            'contracts' => $contracts,
            'status' => $status,
            'metrics' => [
                ['Active contracts', $active->count(), 'repeat', 'col-blue'],
                ['Monthly recurring revenue', number_format($mrr, 2), 'trending-up', 'col-green', 'ARR '.number_format($mrr * 12, 2)],
                ['Due to invoice today', $due->count(), 'calendar', 'col-orange', $due->isNotEmpty() ? 'oldest '.$due->first()->next_invoice_on->format('d M Y') : null],
                ['Renewals in 60 days', $active->filter(fn ($s) => $s->ends_on && $s->ends_on->between(today(), today()->addDays(60)))->count(), 'refresh-cw', 'col-purple'],
            ],
            'renewals' => $active->filter(fn ($s) => $s->ends_on && $s->ends_on->lte(today()->addDays(60)))->sortBy('ends_on')->values(),
            'runs' => BillingRun::latest('id')->limit(8)->get(),
        ]);
    }

    public function create(): View
    {
        return view('accounting.annuity.form', $this->formData(new Subscription([
            'billing_cycle' => 'monthly', 'starts_on' => today(), 'auto_renew' => true, 'term_months' => 12, 'payment_terms_days' => 30,
            'currency' => app(\App\Modules\Accounting\Services\ExchangeRateService::class)->baseCurrency(auth()->user()->company_id),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $lines] = $this->validated($request);
        $contract = $this->billing->save(new Subscription(), $data, $lines);

        return redirect()->route('accounting.annuity-billing.contracts.show', $contract)->with('success', 'Contract '.$contract->contract_number.' created.');
    }

    public function show(Subscription $contract): View
    {
        return view('accounting.annuity.show', [
            'contract' => $contract->load(['customer', 'lines.revenueAccount', 'events.user']),
            'invoices' => $contract->invoices()->limit(36)->get(),
        ]);
    }

    public function edit(Subscription $contract): View
    {
        abort_if(in_array($contract->status, ['cancelled', 'expired'], true), 422, 'Closed contracts cannot be edited.');

        return view('accounting.annuity.form', $this->formData($contract->load('lines')));
    }

    public function update(Request $request, Subscription $contract): RedirectResponse
    {
        abort_if(in_array($contract->status, ['cancelled', 'expired'], true), 422, 'Closed contracts cannot be edited.');
        [$data, $lines] = $this->validated($request, $contract);
        $this->billing->save($contract, $data, $lines);

        return redirect()->route('accounting.annuity-billing.contracts.show', $contract)->with('success', 'Contract updated. Changes apply from the next invoice.');
    }

    public function pause(Subscription $contract): RedirectResponse
    {
        $this->billing->pause($contract);

        return back()->with('success', 'Contract paused; it will not be invoiced until resumed.');
    }

    public function resume(Subscription $contract): RedirectResponse
    {
        $this->billing->resume($contract);

        return back()->with('success', 'Contract resumed; next invoice '.$contract->fresh()->next_invoice_on->format('d M Y').'.');
    }

    public function cancel(Request $request, Subscription $contract): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->billing->cancel($contract, $data['reason']);

        return back()->with('success', 'Contract cancelled. Invoices already raised are not affected.');
    }

    public function preview(Request $request): View
    {
        $asOf = Carbon::parse($request->validate(['date' => ['nullable', 'date']])['date'] ?? today())->startOfDay();

        return view('accounting.annuity.run', ['asOf' => $asOf, 'preview' => $this->billing->preview($asOf)]);
    }

    public function runBilling(Request $request): RedirectResponse
    {
        $data = $request->validate(['date' => ['required', 'date', 'before_or_equal:'.today()->addDays(31)->toDateString()], 'post' => ['nullable', 'boolean']]);
        $post = $request->boolean('post');
        abort_if($post && !$request->user()->can('sales.post'), 403);

        $run = $this->billing->run(Carbon::parse($data['date'])->startOfDay(), $post ?: null);

        return redirect()->route('accounting.annuity-billing.runs.show', $run)
            ->with('success', $run->invoice_count ? $run->invoice_count.' '.str('invoice')->plural($run->invoice_count).' raised in '.$run->run_number.'.' : 'Nothing was due.');
    }

    public function showRun(BillingRun $run): View
    {
        return view('accounting.annuity.run-show', ['run' => $run->load(['invoices.customer', 'creator'])]);
    }

    private function formData(Subscription $contract): array
    {
        $companyId = auth()->user()->company_id;
        try {
            $defaultRevenue = app(AccountMappingService::class)->account('sales_revenue', $companyId)->id;
        } catch (\Throwable) {
            $defaultRevenue = null;
        }

        return [
            'contract' => $contract,
            'customers' => Customer::where('status', true)->orderBy('name')->get(['id', 'name', 'currency']),
            'items' => Item::where('status', true)->orderBy('sku')->get(['id', 'sku', 'name', 'selling_price']),
            'accounts' => Account::where('type', 'revenue')->where('allow_posting', true)->where('status', true)->orderBy('code')->get(['id', 'code', 'name']),
            'currencies' => Currency::where('status', true)->orderBy('code')->pluck('code'),
            'defaultRevenue' => $defaultRevenue,
        ];
    }

    private function validated(Request $request, ?Subscription $contract = null): array
    {
        $data = $request->validate([
            'customer_id' => ['required', new TenantExists('accounting_customers')],
            'contract_number' => ['required', 'max:40', Rule::unique('accounting_subscriptions')->where('company_id', $request->user()->company_id)->ignore($contract)],
            'description' => ['required', 'max:255'],
            'currency' => ['required', 'size:3', TenantExists::currency()],
            'billing_cycle' => ['required', Rule::in(array_keys(Subscription::CYCLES))],
            'starts_on' => [$contract ? 'nullable' : 'required', 'date'],
            'term_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'auto_renew' => ['nullable', 'boolean'],
            'escalation_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'post_invoices' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['nullable', new TenantExists('accounting_items')],
            'lines.*.revenue_account_id' => ['required', (new TenantExists('accounting_accounts'))->where('allow_posting', true)->where('status', true)],
            'lines.*.description' => ['required', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $lines = collect($data['lines'])->map(fn ($l) => [
            'item_id' => $l['item_id'] ?? null, 'revenue_account_id' => $l['revenue_account_id'], 'description' => $l['description'],
            'quantity' => $l['quantity'], 'unit_price' => $l['unit_price'], 'tax_rate' => $l['tax_rate'] ?? 0,
        ])->values()->all();
        unset($data['lines']);
        $data['currency'] = strtoupper($data['currency']);
        $data['auto_renew'] = $request->boolean('auto_renew');
        $data['post_invoices'] = $request->boolean('post_invoices') && $request->user()->can('sales.post');
        $data['escalation_percent'] = $data['escalation_percent'] ?? 0;
        if ($contract) {
            unset($data['starts_on']); // the billing anchor does not move once a contract exists
        }

        return [$data, $lines];
    }
}
