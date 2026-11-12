<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bom;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\JobCost;
use App\Modules\Accounting\Models\Warehouse;
use App\Modules\Accounting\Models\WorkOrder;
use App\Modules\Accounting\Services\AccountMappingService;
use App\Modules\Accounting\Services\ExchangeRateService;
use App\Modules\Accounting\Services\Production\JobCostingService;
use App\Modules\Accounting\Services\Production\ProductionService;
use App\Rules\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Bills of materials, work orders and project job costing. */
class BomJobCostingController extends Controller
{
    public function __construct(
        private readonly ProductionService $production,
        private readonly JobCostingService $jobs,
    ) {
    }

    public function index(): View
    {
        $openJobs = JobCost::whereIn('status', ['planned', 'wip', 'variance_review'])->get();

        return view('accounting.production.index', [
            'metrics' => [
                ['Active BOMs', Bom::where('is_active', true)->count(), 'git-branch', 'col-blue'],
                ['Open work orders', WorkOrder::whereIn('status', ['draft', 'released', 'in_progress'])->count(), 'layers', 'col-green',
                    'WIP '.number_format((float) WorkOrder::where('status', 'in_progress')->sum('material_cost'), 2)],
                ['Open jobs', $openJobs->count(), 'briefcase', 'col-purple', 'WIP '.number_format((float) $openJobs->sum('actual_cost'), 2)],
                ['Jobs over budget', $openJobs->where('status', 'variance_review')->count(), 'alert-circle', 'col-orange'],
            ],
            'workOrders' => WorkOrder::with(['item', 'warehouse'])->whereIn('status', ['draft', 'released', 'in_progress'])->orderBy('due_date')->limit(10)->get(),
            'jobs' => JobCost::with('customer')->whereIn('status', ['planned', 'wip', 'variance_review'])->orderByRaw("CASE status WHEN 'variance_review' THEN 0 ELSE 1 END")->orderBy('due_on')->limit(10)->get(),
        ]);
    }

    // ---- BOMs -------------------------------------------------------------------

    public function boms(): View
    {
        return view('accounting.production.boms', ['boms' => Bom::with('item')->withCount('lines')->orderByDesc('is_active')->orderBy('code')->paginate(25)]);
    }

    public function createBom(): View
    {
        return view('accounting.production.bom-form', ['bom' => new Bom(['version' => '1', 'output_quantity' => 1, 'is_active' => true]), 'items' => ProductionService::stockItems()]);
    }

    public function storeBom(Request $request): RedirectResponse
    {
        [$data, $lines] = $this->validatedBom($request);
        $bom = $this->production->saveBom(new Bom(), $data, $lines);

        return redirect()->route('accounting.bom-job-costing.boms.show', $bom)->with('success', 'BOM '.$bom->code.' saved.');
    }

    public function showBom(Request $request, Bom $bom): View
    {
        $quantity = max(0.0001, (float) $request->query('quantity', (float) $bom->output_quantity));

        return view('accounting.production.bom-show', [
            'bom' => $bom->load(['item', 'lines.item']),
            'rollup' => $this->production->costRollup($bom),
            'quantity' => $quantity,
            'explosion' => $this->production->explode($bom, $quantity),
            'warehouses' => Warehouse::where('status', true)->orderBy('code')->get(),
            'nextNumber' => $this->production->nextNumber(),
        ]);
    }

    public function editBom(Bom $bom): View
    {
        return view('accounting.production.bom-form', ['bom' => $bom->load('lines'), 'items' => ProductionService::stockItems()]);
    }

    public function updateBom(Request $request, Bom $bom): RedirectResponse
    {
        [$data, $lines] = $this->validatedBom($request, $bom);
        $this->production->saveBom($bom, $data, $lines);

        return redirect()->route('accounting.bom-job-costing.boms.show', $bom)->with('success', 'BOM updated. Released work orders keep their requirements.');
    }

    private function validatedBom(Request $request, ?Bom $bom = null): array
    {
        $data = $request->validate([
            'item_id' => ['required', (new TenantExists('accounting_items'))->where('type', 'stock')],
            'code' => ['required', 'max:40', Rule::unique('accounting_boms')->where('company_id', $request->user()->company_id)->ignore($bom)],
            'name' => ['required', 'max:255'],
            'version' => ['required', 'max:20'],
            'output_quantity' => ['required', 'numeric', 'min:0.0001'],
            'labour_hours' => ['nullable', 'numeric', 'min:0'],
            'labour_rate' => ['nullable', 'numeric', 'min:0'],
            'overhead_rate' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', (new TenantExists('accounting_items'))->where('type', 'stock')],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.scrap_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.notes' => ['nullable', 'max:255'],
        ]);
        $lines = collect($data['lines'])->map(fn ($l) => ['item_id' => (int) $l['item_id'], 'quantity' => $l['quantity'], 'scrap_percent' => $l['scrap_percent'] ?? 0, 'notes' => $l['notes'] ?? null])->values()->all();
        unset($data['lines']);

        return [$data + ['labour_hours' => $data['labour_hours'] ?? 0, 'labour_rate' => $data['labour_rate'] ?? 0, 'overhead_rate' => $data['overhead_rate'] ?? 0, 'is_active' => $request->boolean('is_active')], $lines];
    }

    // ---- work orders --------------------------------------------------------------

    public function workOrders(Request $request): View
    {
        return view('accounting.production.work-orders', [
            'orders' => WorkOrder::with(['item', 'warehouse'])->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))->latest('id')->paginate(25)->withQueryString(),
            'boms' => Bom::with('item')->where('is_active', true)->orderBy('code')->get(),
            'warehouses' => Warehouse::where('status', true)->orderBy('code')->get(),
            'nextNumber' => $this->production->nextNumber(),
            'status' => $request->query('status'),
        ]);
    }

    public function storeWorkOrder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'wo_number' => ['required', 'max:40', Rule::unique('accounting_work_orders')->where('company_id', $request->user()->company_id)],
            'bom_id' => ['required', new TenantExists('accounting_boms')],
            'warehouse_id' => ['required', new TenantExists('accounting_warehouses')],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'max:2000'],
        ]);
        $order = $this->production->createWorkOrder($data);

        return redirect()->route('accounting.bom-job-costing.work-orders.show', $order)->with('success', 'Work order '.$order->wo_number.' created. Release it to fix its material requirements.');
    }

    public function showWorkOrder(WorkOrder $workOrder): View
    {
        $workOrder->load(['item', 'warehouse', 'bom', 'materials.item', 'creator']);
        $stock = app(\App\Modules\Accounting\Services\InventoryStockService::class);

        return view('accounting.production.work-order-show', [
            'order' => $workOrder,
            'available' => $workOrder->materials->mapWithKeys(fn ($m) => [$m->id => $stock->available((int) $m->item_id, (int) $workOrder->warehouse_id)]),
            'standard' => $workOrder->bom ? $this->production->costRollup($workOrder->bom) : null,
        ]);
    }

    public function releaseWorkOrder(WorkOrder $workOrder): RedirectResponse
    {
        $shortages = $this->production->release($workOrder);

        return back()->with($shortages ? 'warning' : 'success', $shortages ? 'Released, but stock is short: '.implode('; ', $shortages).'.' : 'Released: all materials are in stock.');
    }

    public function issueWorkOrder(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate(['quantities' => ['nullable', 'array'], 'quantities.*' => ['nullable', 'numeric', 'min:0']]);
        $cost = $this->production->issueMaterials($workOrder, $data['quantities'] ?? null);

        return back()->with('success', 'Materials issued: '.number_format($cost, 2).' moved from inventory to work in progress.');
    }

    public function completeWorkOrder(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate(['completed_quantity' => ['required', 'numeric', 'min:0.0001'], 'labour_hours' => ['nullable', 'numeric', 'min:0']]);
        $order = $this->production->complete($workOrder, (float) $data['completed_quantity'], isset($data['labour_hours']) ? (float) $data['labour_hours'] : null);

        return back()->with('success', 'Completed: '.rtrim(rtrim(number_format((float) $order->completed_quantity, 4), '0'), '.').' received into stock at '.number_format((float) $order->actualUnitCost(), 4).' each.');
    }

    public function cancelWorkOrder(WorkOrder $workOrder): RedirectResponse
    {
        $this->production->cancel($workOrder);

        return back()->with('success', 'Work order cancelled.');
    }

    // ---- jobs -----------------------------------------------------------------------

    public function jobs(Request $request): View
    {
        return view('accounting.production.jobs', [
            'jobs' => JobCost::with('customer')->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))->latest('id')->paginate(25)->withQueryString(),
            'status' => $request->query('status'),
        ]);
    }

    public function createJob(): View
    {
        return view('accounting.production.job-form', $this->jobFormData(new JobCost(['job_number' => $this->jobs->nextNumber(), 'billing_method' => 'fixed', 'variance_tolerance' => 10])));
    }

    public function storeJob(Request $request): RedirectResponse
    {
        $job = $this->jobs->save(new JobCost(), $this->validatedJob($request));

        return redirect()->route('accounting.bom-job-costing.jobs.show', $job)->with('success', 'Job '.$job->job_number.' created.');
    }

    public function showJob(JobCost $job): View
    {
        $companyId = auth()->user()->company_id;
        try {
            $defaultRevenue = app(AccountMappingService::class)->account('sales_revenue', $companyId)->id;
        } catch (\Throwable) {
            $defaultRevenue = null;
        }

        return view('accounting.production.job-show', [
            'job' => $job->load(['customer', 'entries.item', 'entries.creditAccount', 'entries.creator', 'invoices']),
            'summary' => $this->jobs->summary($job),
            'billable' => $this->jobs->costPlusBillable($job),
            'items' => ProductionService::stockItems(),
            'warehouses' => Warehouse::where('status', true)->orderBy('code')->get(),
            'creditAccounts' => Account::whereIn('type', ['liability', 'asset'])->where('allow_posting', true)->where('status', true)->orderBy('code')->get(['id', 'code', 'name']),
            'revenueAccounts' => Account::where('type', 'revenue')->where('allow_posting', true)->where('status', true)->orderBy('code')->get(['id', 'code', 'name']),
            'defaultRevenue' => $defaultRevenue,
            'currencies' => Currency::where('status', true)->orderBy('code')->pluck('code'),
            'baseCurrency' => app(ExchangeRateService::class)->baseCurrency($companyId),
        ]);
    }

    public function editJob(JobCost $job): View
    {
        abort_unless($job->isOpen(), 422, 'Closed jobs cannot be edited.');

        return view('accounting.production.job-form', $this->jobFormData($job));
    }

    public function updateJob(Request $request, JobCost $job): RedirectResponse
    {
        abort_unless($job->isOpen(), 422, 'Closed jobs cannot be edited.');
        $this->jobs->save($job, $this->validatedJob($request, $job));

        return redirect()->route('accounting.bom-job-costing.jobs.show', $job)->with('success', 'Job updated.');
    }

    public function storeEntry(Request $request, JobCost $job): RedirectResponse
    {
        $category = $request->input('category');
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(JobCost::CATEGORIES))],
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'max:255'],
            'item_id' => [Rule::requiredIf($category === 'materials'), 'nullable', (new TenantExists('accounting_items'))->where('type', 'stock')],
            'warehouse_id' => [Rule::requiredIf($category === 'materials'), 'nullable', new TenantExists('accounting_warehouses')],
            'quantity' => [Rule::requiredIf(in_array($category, ['materials', 'labour', 'overhead'], true)), 'nullable', 'numeric', 'min:0.0001'],
            'unit_cost' => [Rule::requiredIf(in_array($category, ['labour', 'overhead'], true)), 'nullable', 'numeric', 'min:0'],
            'amount' => [Rule::requiredIf(in_array($category, ['subcontract', 'other'], true)), 'nullable', 'numeric', 'min:0.01'],
            'credit_account_id' => ['nullable', (new TenantExists('accounting_accounts'))->where('allow_posting', true)->where('status', true)],
        ]);
        $entry = $this->jobs->addEntry($job, $data);
        $job->refresh();

        return back()->with($job->status === 'variance_review' ? 'warning' : 'success',
            'Cost of '.number_format((float) $entry->amount, 2).' recorded.'.($job->status === 'variance_review' ? ' The job is now over budget beyond its tolerance and is flagged for review.' : ''));
    }

    public function invoiceJob(Request $request, JobCost $job): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'max:255'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'revenue_account_id' => ['required', (new TenantExists('accounting_accounts'))->where('allow_posting', true)->where('status', true)],
            'currency' => ['required', 'size:3', TenantExists::currency()],
        ]);
        $invoice = $this->jobs->invoice($job, (float) $data['amount'], $data['description'], (float) ($data['tax_rate'] ?? 0), (int) $data['revenue_account_id'], strtoupper($data['currency']));

        return redirect()->route('accounting.ar.invoices.show', $invoice)->with('success', 'Draft invoice '.$invoice->invoice_number.' created for '.$job->job_number.'. Review and post it.');
    }

    public function closeJob(JobCost $job): RedirectResponse
    {
        $job = $this->jobs->close($job);

        return back()->with('success', 'Job closed: '.number_format((float) $job->actual_cost, 2).' moved from work in progress to cost of sales.');
    }

    public function acknowledgeJob(JobCost $job): RedirectResponse
    {
        $this->jobs->acknowledgeVariance($job);

        return back()->with('success', 'Overrun acknowledged; the job is back in progress with a wider tolerance.');
    }

    private function jobFormData(JobCost $job): array
    {
        return ['job' => $job, 'customers' => Customer::where('status', true)->orderBy('name')->get(['id', 'name'])];
    }

    private function validatedJob(Request $request, ?JobCost $job = null): array
    {
        $data = $request->validate([
            'job_number' => ['required', 'max:40', Rule::unique('accounting_job_costs')->where('company_id', $request->user()->company_id)->ignore($job)],
            'name' => ['required', 'max:255'],
            'customer_id' => ['nullable', new TenantExists('accounting_customers')],
            'due_on' => ['nullable', 'date'],
            'budget_materials' => ['nullable', 'numeric', 'min:0'],
            'budget_labour' => ['nullable', 'numeric', 'min:0'],
            'budget_overhead' => ['nullable', 'numeric', 'min:0'],
            'budget_other' => ['nullable', 'numeric', 'min:0'],
            'billing_method' => ['required', Rule::in(['fixed', 'cost_plus'])],
            'contract_value' => ['nullable', 'numeric', 'min:0'],
            'markup_percent' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'variance_tolerance' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'max:2000'],
        ]);

        foreach (['budget_materials', 'budget_labour', 'budget_overhead', 'budget_other', 'contract_value', 'markup_percent'] as $key) {
            $data[$key] = $data[$key] ?? 0;
        }
        $data['variance_tolerance'] = $data['variance_tolerance'] ?? 10;

        return $data;
    }
}
