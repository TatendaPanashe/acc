<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Bom;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Item;
use App\Modules\Accounting\Models\JobCost;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Models\SalesInvoice;
use App\Modules\Accounting\Models\Warehouse;
use App\Modules\Accounting\Models\WorkOrder;
use App\Modules\Accounting\Services\InventoryStockService;
use App\Modules\Accounting\Services\Production\ProductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsLedger;
use Tests\TestCase;

class BomJobCostingTest extends TestCase
{
    use BuildsLedger, RefreshDatabase;

    private Company $company;
    private User $accountant;
    private User $manager;
    private Warehouse $warehouse;
    private array $items = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-10 09:00:00');
        $this->company = $this->createLedgerCompany();
        $this->manager = $this->ledgerUser($this->company, 'finance_manager');
        $this->accountant = $this->ledgerUser($this->company, 'accountant');
        $this->actingAs($this->accountant);

        $this->warehouse = Warehouse::create(['code' => 'MAIN', 'name' => 'Main', 'status' => true]);
        foreach (['WOOD' => 'Timber plank', 'SCREW' => 'Screw', 'LEG' => 'Chair leg', 'CHAIR' => 'Dining chair'] as $sku => $name) {
            $this->items[$sku] = Item::create(['sku' => $sku, 'name' => $name, 'type' => 'stock', 'costing_method' => 'weighted_average', 'standard_cost' => 0, 'selling_price' => 0, 'status' => true]);
        }
        $stock = app(InventoryStockService::class);
        $stock->move($this->company->id, $this->items['WOOD']->id, $this->warehouse->id, 'opening', 100, 10);
        $stock->move($this->company->id, $this->items['SCREW']->id, $this->warehouse->id, 'opening', 500, 0.5);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function bom(string $code, string $makes, array $lines, array $extra = []): Bom
    {
        $this->post(route('accounting.bom-job-costing.boms.store'), $extra + [
            'code' => $code, 'name' => $code, 'version' => '1', 'item_id' => $this->items[$makes]->id, 'output_quantity' => 1,
            'labour_hours' => 0, 'labour_rate' => 20, 'overhead_rate' => 4, 'is_active' => 1,
            'lines' => collect($lines)->map(fn ($l, $sku) => ['item_id' => $this->items[$sku]->id, 'quantity' => $l[0], 'scrap_percent' => $l[1] ?? 0])->values()->all(),
        ])->assertSessionHasNoErrors();

        return Bom::where('code', $code)->firstOrFail();
    }

    private function balance(string $code): float
    {
        $id = $this->ledgerAccount($this->company, $code)->id;

        return round((float) JournalLine::where('account_id', $id)->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'))->get()->sum(fn ($l) => (float) $l->debit - (float) $l->credit), 2);
    }

    public function test_cost_rolls_up_through_sub_assemblies_and_explodes_to_raw_materials(): void
    {
        $this->bom('BOM-LEG', 'LEG', ['WOOD' => [1], 'SCREW' => [2]], ['labour_hours' => 0.5]);
        $chair = $this->bom('BOM-CHAIR', 'CHAIR', ['LEG' => [4], 'WOOD' => [1, 10]], ['labour_hours' => 1]);

        $rollup = app(ProductionService::class)->costRollup($chair);
        // Leg: 10 + 2 × 0.5 = 11 materials + 10 labour + 2 overhead = 23. Chair: 4 × 23 + 1.1 × 10 = 103 + 20 + 4.
        $this->assertSame([103.0, 20.0, 4.0, 127.0], [$rollup['materials'], $rollup['labour'], $rollup['overhead'], $rollup['total']]);

        $explosion = collect(app(ProductionService::class)->explode($chair, 2))->mapWithKeys(fn ($r) => [$r['item']->sku => $r['quantity']]);
        $this->assertEquals(['WOOD' => 10.2, 'SCREW' => 16.0], $explosion->sortKeys()->reverse()->all());

        $this->get(route('accounting.bom-job-costing.boms.show', $chair))->assertOk()->assertSee('Sub-assembly (BOM-LEG)')->assertSee('127.00');
    }

    public function test_circular_boms_are_rejected(): void
    {
        $this->bom('BOM-LEG', 'LEG', ['WOOD' => [1]]);
        $this->post(route('accounting.bom-job-costing.boms.store'), [
            'code' => 'BOM-WOOD', 'name' => 'x', 'version' => '1', 'item_id' => $this->items['WOOD']->id, 'output_quantity' => 1, 'is_active' => 1,
            'lines' => [['item_id' => $this->items['LEG']->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('lines');
    }

    public function test_work_order_moves_cost_through_wip_into_finished_goods(): void
    {
        $leg = $this->bom('BOM-LEG', 'LEG', ['WOOD' => [1], 'SCREW' => [2]], ['labour_hours' => 0.5]);
        $this->post(route('accounting.bom-job-costing.work-orders.store'), ['wo_number' => 'WO-00001', 'bom_id' => $leg->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 4])->assertSessionHasNoErrors();
        $order = WorkOrder::firstOrFail();

        $this->post(route('accounting.bom-job-costing.work-orders.release', $order))->assertSessionHas('success');
        $this->assertSame(23.0, (float) $order->fresh()->standard_unit_cost);
        $this->post(route('accounting.bom-job-costing.work-orders.issue', $order))->assertSessionHasNoErrors();
        $this->assertSame(44.0, $this->balance('1250')); // 4 × 10 + 8 × 0.5

        $this->post(route('accounting.bom-job-costing.work-orders.complete', $order), ['completed_quantity' => 4])->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(['completed', 2.0, 40.0, 8.0, 23.0], [$order->status, (float) $order->labour_hours, (float) $order->labour_cost, (float) $order->overhead_cost, $order->actualUnitCost()]);
        $this->assertSame(0.0, $this->balance('1250'));
        $this->assertSame(48.0, $this->balance('1200'));  // −44 materials + 92 finished goods
        $this->assertSame(-48.0, $this->balance('6190')); // labour + overhead applied
        $stock = app(InventoryStockService::class);
        $this->assertSame([4.0, 96.0], [$stock->available($this->items['LEG']->id, $this->warehouse->id), $stock->available($this->items['WOOD']->id, $this->warehouse->id)]);
        $this->assertSame(23.0, $stock->averageCost($this->items['LEG']->fresh()));
    }

    public function test_job_costs_flag_overruns_bill_cost_plus_and_close_to_cost_of_sales(): void
    {
        $customer = Customer::create(['customer_number' => 'C1', 'name' => 'Ruwa Farms', 'credit_limit' => 0, 'currency' => 'USD', 'status' => true]);
        $this->post(route('accounting.bom-job-costing.jobs.store'), [
            'job_number' => 'JOB-0001', 'name' => 'Pump install', 'customer_id' => $customer->id, 'budget_materials' => 100, 'budget_labour' => 50,
            'billing_method' => 'cost_plus', 'markup_percent' => 20, 'variance_tolerance' => 10,
        ])->assertSessionHasNoErrors();
        $job = JobCost::firstOrFail();
        $this->assertSame([150.0, 'planned'], [(float) $job->estimated_cost, $job->status]);

        $entry = fn (array $data) => $this->post(route('accounting.bom-job-costing.jobs.entries.store', $job), $data + ['entry_date' => '2026-06-10', 'description' => 'Cost'])->assertSessionHasNoErrors();
        $entry(['category' => 'materials', 'item_id' => $this->items['WOOD']->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 5]);
        $entry(['category' => 'labour', 'quantity' => 4, 'unit_cost' => 20]);
        $this->assertSame([130.0, 'wip'], [(float) $job->fresh()->actual_cost, $job->fresh()->status]);

        $entry(['category' => 'subcontract', 'amount' => 60]);
        $this->assertSame('variance_review', $job->fresh()->status); // 190 > 150 + 10%
        $this->assertSame(190.0, $this->balance('1250'));

        $this->post(route('accounting.bom-job-costing.jobs.invoice', $job), ['amount' => 228, 'description' => 'Pump install', 'tax_rate' => 0, 'currency' => 'USD', 'revenue_account_id' => $this->ledgerAccount($this->company, '4000')->id])->assertSessionHasNoErrors();
        $this->assertSame([228.0, 'draft'], [(float) SalesInvoice::where('job_cost_id', $job->id)->value('total'), SalesInvoice::where('job_cost_id', $job->id)->value('status')]);
        $this->assertSame(0.0, app(\App\Modules\Accounting\Services\Production\JobCostingService::class)->costPlusBillable($job->fresh()));

        // Closing is an approval step.
        $this->post(route('accounting.bom-job-costing.jobs.close', $job))->assertForbidden();
        $this->actingAs($this->manager)->post(route('accounting.bom-job-costing.jobs.close', $job))->assertSessionHasNoErrors();
        $this->assertSame(['closed', 0.0, 190.0], [$job->fresh()->status, $this->balance('1250'), $this->balance('5000')]);

        $this->get(route('accounting.bom-job-costing.jobs.show', $job))->assertOk()->assertSee('Closed');
        $this->get(route('accounting.reporting.run', 'job_profitability'))->assertOk()->assertSee('JOB-0001 Pump install')->assertSee('228.00')->assertSee('38.00');
    }

    public function test_pages_render(): void
    {
        $this->bom('BOM-LEG', 'LEG', ['WOOD' => [1]]);
        foreach (['index', 'boms.index', 'boms.create', 'work-orders.index', 'jobs.index', 'jobs.create'] as $page) {
            $this->get(route('accounting.bom-job-costing.'.$page))->assertOk();
        }
    }
}
