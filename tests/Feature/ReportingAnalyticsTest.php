<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\ReportDefinition;
use App\Modules\Accounting\Services\JournalPostingService;
use App\Modules\Accounting\Services\Reporting\ReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsLedger;
use Tests\TestCase;

class ReportingAnalyticsTest extends TestCase
{
    use BuildsLedger, RefreshDatabase;

    private Company $company;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-20 10:00:00');
        $this->company = $this->createLedgerCompany();
        $this->manager = $this->ledgerUser($this->company, 'finance_manager');
        $this->actingAs($this->manager);

        // May: sales 1,000 (cost 400), rent 300. June: sales 1,500 (cost 600), rent 300.
        foreach (['2026-05' => [1000, 400], '2026-06' => [1500, 600]] as $month => [$sales, $cost]) {
            $this->journal($month.'-05', '1100', '4000', $sales);
            $this->journal($month.'-05', '5000', '1200', $cost);
            $this->journal($month.'-06', '6200', '1000', 300);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function journal(string $date, string $debit, string $credit, float $amount): void
    {
        $entry = JournalEntry::create(['entry_number' => 'JV-'.uniqid(), 'entry_date' => $date, 'currency' => 'USD', 'exchange_rate' => 1, 'memo' => 'Test', 'status' => 'draft']);
        foreach ([[$debit, $amount, 0], [$credit, 0, $amount]] as [$code, $d, $c]) {
            $entry->lines()->create(['company_id' => $this->company->id, 'account_id' => $this->ledgerAccount($this->company, $code)->id, 'description' => 'Line '.$code, 'debit' => $d, 'credit' => $c, 'fx_debit' => $d, 'fx_credit' => $c]);
        }
        app(JournalPostingService::class)->post($entry);
    }

    public function test_kpis_separate_cost_of_sales_from_operating_expenses(): void
    {
        $kpi = app(ReportingService::class)->kpis(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        $this->assertSame([1500.0, 600.0, 900.0, 60.0, 300.0, 600.0, 40.0], [$kpi['revenue'], $kpi['cost_of_sales'], $kpi['gross_profit'], $kpi['gross_margin'], $kpi['operating_expenses'], $kpi['net_profit'], $kpi['net_margin']]);
        $this->assertSame([2500.0, -600.0], [$kpi['receivables'], $kpi['cash']]);

        $this->get(route('accounting.reporting.index', ['range' => 'this_month', 'compare' => 'previous_period']))
            ->assertOk()->assertSee('Revenue and expenses')->assertSee('▲ +50.0%'); // 1,500 vs 1,000
    }

    public function test_every_report_runs_and_profit_and_loss_compares_periods(): void
    {
        foreach (array_keys(ReportingService::REPORTS) as $type) {
            $this->get(route('accounting.reporting.run', ['type' => $type, 'account_id' => $this->ledgerAccount($this->company, '4000')->id]))->assertOk();
        }

        $report = app(ReportingService::class)->run('profit_and_loss', ['range' => 'custom', 'from' => '2026-06-01', 'to' => '2026-06-30', 'compare' => 'previous_period']);
        $net = collect($report['rows'])->firstWhere('cells.account', 'Net profit')['cells'];
        $this->assertSame([600.0, 300.0, 300.0, 100.0], [$net['current'], $net['prior'], $net['variance'], $net['variance_pct']]);
        $gross = collect($report['rows'])->firstWhere('cells.account', 'Gross profit')['cells'];
        $this->assertSame(900.0, $gross['current']);

        $sheet = app(ReportingService::class)->run('balance_sheet', ['as_at' => '2026-06-30']);
        $this->assertSame([], $sheet['notes']); // balances
    }

    public function test_drill_down_shows_ledger_lines_with_running_balance(): void
    {
        $report = app(ReportingService::class)->run('general_ledger', ['range' => 'custom', 'from' => '2026-06-01', 'to' => '2026-06-30', 'account_id' => $this->ledgerAccount($this->company, '1100')->id]);
        $rows = collect($report['rows']);

        $this->assertSame(1000.0, $rows->first()['cells']['balance']); // opening = May sale
        $this->assertSame(2500.0, $rows->last()['cells']['balance']);
        $this->assertStringContainsString('/journals/', $rows[1]['link']);
    }

    public function test_csv_print_and_saved_reports(): void
    {
        $csv = $this->get(route('accounting.reporting.run', ['type' => 'trial_balance', 'format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('"Sales Revenue"', $csv);
        $this->get(route('accounting.reporting.run', ['type' => 'sales_by_month', 'print' => 1]))->assertOk()->assertSee('printed');

        $this->post(route('accounting.reporting.saved.store'), ['name' => 'Monthly P&L', 'type' => 'profit_and_loss', 'range' => 'this_month', 'compare' => 'previous_year'])->assertRedirect();
        $saved = ReportDefinition::firstOrFail();
        $this->assertSame(['range' => 'this_month', 'compare' => 'previous_year'], $saved->filters);
        $this->get(route('accounting.reporting.saved.show', $saved))->assertRedirect(route('accounting.reporting.run', ['type' => 'profit_and_loss', 'range' => 'this_month', 'compare' => 'previous_year']));
        $this->assertNotNull($saved->fresh()->last_run_at);
        $this->get(route('accounting.reporting.library'))->assertOk()->assertSee('Monthly P&amp;L', false);
    }

    public function test_accountants_cannot_open_management_reports(): void
    {
        $accountant = $this->ledgerUser($this->company, 'accountant');
        $this->actingAs($accountant)->get(route('accounting.reporting.index'))->assertForbidden();

        $auditor = $this->ledgerUser($this->company, 'auditor');
        $this->actingAs($auditor)->get(route('accounting.reporting.index'))->assertOk();
        $this->post(route('accounting.reporting.saved.store'), ['name' => 'X', 'type' => 'trial_balance'])->assertForbidden();
    }
}
