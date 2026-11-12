<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\AiForecast;
use App\Modules\Accounting\Models\AiInsight;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\CustomerReceipt;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\SalesInvoice;
use App\Modules\Accounting\Models\Supplier;
use App\Modules\Accounting\Models\SupplierInvoice;
use App\Modules\Accounting\Services\Ai\AnomalyDetectionService;
use App\Modules\Accounting\Services\Ai\CashCollectionsService;
use App\Modules\Accounting\Services\Ai\ForecastService;
use App\Modules\Accounting\Services\JournalPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsLedger;
use Tests\TestCase;

class AiAutomationTest extends TestCase
{
    use BuildsLedger, RefreshDatabase;

    private Company $company;
    private User $manager;
    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15 10:00:00');
        $this->company = $this->createLedgerCompany();
        $this->manager = $this->ledgerUser($this->company, 'finance_manager');
        $this->accountant = $this->ledgerUser($this->company, 'accountant');
        $sub = app(\App\Services\Platform\SubscriptionService::class)->activate($this->company->subscription, \App\Models\Platform\Plan::where('code', 'business')->first(), 'annual');
        $sub->update(['current_period_ends_at' => '2030-01-01']);
        $this->actingAs($this->manager);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function journal(string $date, string $debit, string $credit, float $amount, ?User $preparedBy = null): JournalEntry
    {
        $entry = JournalEntry::create(['entry_number' => 'JV-'.uniqid(), 'entry_date' => $date, 'currency' => 'USD', 'exchange_rate' => 1, 'memo' => 'Test', 'status' => 'draft', 'created_by' => ($preparedBy ?? $this->accountant)->id]);
        foreach ([[$debit, $amount, 0], [$credit, 0, $amount]] as [$code, $d, $c]) {
            $entry->lines()->create(['company_id' => $this->company->id, 'account_id' => $this->ledgerAccount($this->company, $code)->id, 'description' => 'Test', 'debit' => $d, 'credit' => $c, 'fx_debit' => $d, 'fx_credit' => $c]);
        }

        return app(JournalPostingService::class)->post($entry);
    }

    public function test_holt_model_follows_trend_and_seasonality(): void
    {
        $trend = ForecastService::fit(array_map(fn ($t) => 100 + 10 * $t, range(0, 11)), 3);
        $this->assertSame('holt_linear', $trend['method']);
        $this->assertEqualsWithDelta(220, $trend['forecast'][0], 6);
        $this->assertGreaterThan(90, $trend['confidence']);

        $season = [10, 12, 15, 20, 30, 40, 45, 40, 30, 20, 15, 12];
        $seasonal = ForecastService::fit(array_merge(...array_map(fn ($year) => array_map(fn ($v) => $v * 10 + $year * 12, $season), [0, 1, 2])), 2);
        $this->assertSame('holt_winters', $seasonal['method']);
        $this->assertEqualsWithDelta(100 + 36, $seasonal['forecast'][0], 15); // January of year 4
    }

    public function test_forecasts_are_generated_from_the_ledger_and_approved(): void
    {
        foreach (range(1, 9) as $month) {
            $this->journal(sprintf('2026-%02d-10', $month), '1000', '4000', 1000 + 100 * $month);
            $this->journal(sprintf('2026-%02d-12', $month), '6000', '1000', 600);
        }

        $this->post(route('accounting.ai-automation.forecasts.generate'), ['horizon' => 3])->assertSessionHas('success');
        $revenue = AiForecast::where('type', 'revenue')->orderBy('forecast_date')->get();
        $this->assertSame(['2026-10-01', '2026-11-01', '2026-12-01'], $revenue->map(fn ($f) => $f->forecast_date->toDateString())->all());
        $this->assertEqualsWithDelta(2000, (float) $revenue[0]->predicted_value, 60);
        $this->assertLessThan((float) $revenue[0]->predicted_value, (float) $revenue[0]->lower_value);
        $this->assertSame(3, AiForecast::where('type', 'cash_flow')->count());

        $this->get(route('accounting.ai-automation.forecasts.index'))->assertOk()->assertSee('Approve as plan');
        $this->post(route('accounting.ai-automation.forecasts.review'), ['type' => 'revenue', 'decision' => 'approved']);
        $this->assertSame(['approved'], AiForecast::where('type', 'revenue')->distinct()->pluck('status')->all());
        $this->get(route('accounting.ai-automation.index'))->assertOk()->assertSee('approved as plan');

        // Regenerating replaces the forecasts; accountants cannot run or see the models.
        $this->post(route('accounting.ai-automation.forecasts.generate'), ['horizon' => 3]);
        $this->assertSame(3, AiForecast::where('type', 'revenue')->count());
        $this->actingAs($this->accountant)->post(route('accounting.ai-automation.forecasts.generate'))->assertForbidden();
        $this->get(route('accounting.ai-automation.index'))->assertForbidden();
    }

    public function test_auto_coding_learns_from_posted_supplier_invoices(): void
    {
        $zesa = Supplier::create(['supplier_number' => 'S1', 'name' => 'ZESA', 'status' => true]);
        $landlord = Supplier::create(['supplier_number' => 'S2', 'name' => 'Eastgate Properties', 'status' => true]);
        $samples = [[$zesa, 'Electricity prepaid units', '6300'], [$zesa, 'Electricity bill office', '6300'], [$landlord, 'Office rent monthly', '6200'], [$landlord, 'Rent warehouse', '6200'], [null, 'Stationery and printer paper', '6000']];
        foreach (range(1, 4) as $round) {
            foreach ($samples as $i => [$supplier, $text, $code]) {
                $invoice = SupplierInvoice::create(['supplier_id' => ($supplier ?? $zesa)->id, 'invoice_number' => "SI-$round-$i", 'invoice_date' => sprintf('2026-%02d-0%d', $round, $i + 1), 'due_date' => '2026-12-31', 'currency' => 'USD', 'subtotal' => 50, 'tax_total' => 0, 'total' => 50, 'status' => 'posted']);
                $invoice->lines()->create(['company_id' => $this->company->id, 'expense_account_id' => $this->ledgerAccount($this->company, $code)->id, 'description' => $text, 'quantity' => 1, 'unit_cost' => 50, 'tax_rate' => 0, 'line_total' => 50]);
            }
        }

        // Accountants use the suggestions while capturing invoices.
        $response = $this->actingAs($this->accountant)->getJson(route('accounting.ai-automation.suggest', ['text' => 'electricity for march', 'supplier_id' => $zesa->id]))->assertOk();
        $this->assertSame('6300', $response->json('suggestions.0.code'));
        $this->assertGreaterThan(0.6, $response->json('suggestions.0.probability'));
        $this->assertSame('6200', $this->getJson(route('accounting.ai-automation.suggest', ['text' => 'rent for november', 'supplier_id' => $landlord->id]))->json('suggestions.0.code'));

        $this->actingAs($this->manager)->get(route('accounting.ai-automation.auto-coding', ['text' => 'printer paper']))->assertOk()->assertSee('100%');
    }

    public function test_anomalies_are_found_once_and_dismissals_stick(): void
    {
        foreach (range(1, 10) as $i) {
            $this->journal('2026-0'.(($i % 8) + 1).'-05', '6000', '1000', 95 + $i);
        }
        $spike = $this->journal('2026-09-20', '6000', '1000', 5000);
        $self = $this->journal('2026-09-21', '6300', '1000', 3000, $this->manager); // prepared and posted by the manager, round amount

        $supplier = Supplier::create(['supplier_number' => 'S1', 'name' => 'Harare Paper', 'status' => true]);
        foreach (['HP-101', 'hp 101'] as $i => $number) {
            SupplierInvoice::create(['supplier_id' => $supplier->id, 'invoice_number' => $number, 'invoice_date' => '2026-09-0'.($i + 1), 'due_date' => '2026-10-30', 'currency' => 'USD', 'subtotal' => 80, 'tax_total' => 0, 'total' => 80 + $i, 'status' => 'draft']);
        }

        $this->post(route('accounting.ai-automation.insights.run'))->assertSessionHas('success');
        $types = AiInsight::pluck('type', 'id');
        $this->assertContains('unusual_amount', $types);
        $this->assertContains('duplicate_supplier_invoice', $types);
        $this->assertContains('self_posted_journal', $types);
        $this->assertContains('round_amount_journal', $types);
        $this->assertSame($spike->id, (int) AiInsight::where('type', 'unusual_amount')->value('subject_id'));
        $this->assertSame('high', AiInsight::where('type', 'duplicate_supplier_invoice')->value('severity'));

        $finding = AiInsight::where('type', 'self_posted_journal')->first();
        $this->post(route('accounting.ai-automation.insights.review', $finding), ['status' => 'dismissed', 'note' => 'Month-end accrual'])->assertSessionHasNoErrors();
        $count = AiInsight::count();
        app(AnomalyDetectionService::class)->detect();
        $this->assertSame([$count, 'dismissed'], [AiInsight::count(), $finding->fresh()->status]);
        $this->get(route('accounting.ai-automation.insights.index'))->assertOk()->assertSee('looks like a duplicate');
        $this->assertNotNull($self);
    }

    public function test_payment_dates_are_predicted_from_customer_history(): void
    {
        $slow = Customer::create(['customer_number' => 'C1', 'name' => 'Slow Payer', 'credit_limit' => 0, 'currency' => 'USD', 'status' => true]);
        foreach (['2026-05-01', '2026-06-01', '2026-07-01'] as $i => $due) {
            $paid = SalesInvoice::create(['customer_id' => $slow->id, 'invoice_number' => 'P'.$i, 'invoice_date' => $due, 'due_date' => $due, 'currency' => 'USD', 'subtotal' => 100, 'tax_total' => 0, 'total' => 100, 'amount_paid' => 100, 'status' => 'paid']);
            CustomerReceipt::create(['customer_id' => $slow->id, 'invoice_id' => $paid->id, 'receipt_number' => 'R'.$i, 'receipt_date' => Carbon::parse($due)->addDays(10)->toDateString(), 'currency' => 'USD', 'amount' => 100, 'payment_method' => 'bank', 'status' => 'posted']);
        }
        SalesInvoice::create(['customer_id' => $slow->id, 'invoice_number' => 'OPEN', 'invoice_date' => '2026-10-10', 'due_date' => '2026-10-31', 'currency' => 'USD', 'subtotal' => 400, 'tax_total' => 0, 'total' => 400, 'amount_paid' => 0, 'status' => 'posted']);

        $prediction = app(CashCollectionsService::class)->predictions('ar')->first();
        $this->assertSame(['2026-11-10', 10.0, 400.0], [$prediction['expected']->toDateString(), $prediction['delay'], $prediction['base']]);

        $outlook = app(CashCollectionsService::class)->outlook();
        $this->assertSame(400.0, collect($outlook['weeks'])->sum('receipts'));
        $this->get(route('accounting.ai-automation.collections'))->assertOk()->assertSee('Slow Payer');
    }

    public function test_scheduled_refresh_runs_for_each_company(): void
    {
        foreach (range(1, 6) as $month) {
            $this->journal(sprintf('2026-%02d-10', $month), '1000', '4000', 500);
        }
        auth()->forgetUser();

        $this->artisan('ai:refresh', ['--forecasts' => true])->assertSuccessful();

        $this->assertSame(6, AiForecast::withoutGlobalScopes()->where('company_id', $this->company->id)->where('type', 'revenue')->count());
    }
}
