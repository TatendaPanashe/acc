<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\ExchangeRate;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\PayItem;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\StatutorySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsLedger;
use Tests\TestCase;

/**
 * NOTE: the tax bands and rates below are made-up test figures, not ZIMRA's.
 */
class PayrollTest extends TestCase
{
    use BuildsLedger, RefreshDatabase;

    private Company $company;
    private User $admin;
    private User $manager;
    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-25 09:00:00');
        $this->company = $this->createLedgerCompany();
        $this->admin = $this->ledgerUser($this->company, 'admin');
        $this->manager = $this->ledgerUser($this->company, 'finance_manager');
        $this->accountant = $this->ledgerUser($this->company, 'accountant');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Test table: 0–100 0%; 100.01–300 20% less 20; above 300 30% less 50. NSSA 4.5% each, ceiling 700; AIDS levy 3%. */
    private function usdSettings(array $overrides = []): void
    {
        $this->actingAs($this->admin)->post(route('accounting.payroll.settings.store'), $overrides + [
            'tax_year' => 2026, 'currency' => 'USD', 'nssa_employee_rate' => 4.5, 'nssa_employer_rate' => 4.5, 'nssa_ceiling' => 700,
            'nssa_deductible' => 1, 'aids_levy_rate' => 3, 'medical_aid_credit_rate' => 50,
            'bands' => [['lower' => 0, 'upper' => 100, 'rate' => 0, 'deduct' => 0], ['lower' => 100.01, 'upper' => 300, 'rate' => 20, 'deduct' => 20], ['lower' => 300.01, 'upper' => null, 'rate' => 30, 'deduct' => 50]],
            'credits' => [['code' => 'elderly', 'name' => 'Elderly person credit', 'amount' => 75]],
            'levies' => [['name' => 'Training levy', 'rate' => 1, 'payer' => 'employer']],
        ])->assertSessionHasNoErrors();
    }

    private function item(string $code, string $type, string $category, string $calculation, float $default, bool $taxable = true, bool $preTax = false): PayItem
    {
        $this->actingAs($this->accountant)->post(route('accounting.payroll.items.store'), [
            'code' => $code, 'name' => ucfirst(strtolower($code)), 'type' => $type, 'category' => $category, 'calculation' => $calculation,
            'default_amount' => $default, 'taxable' => $taxable ? 1 : 0, 'pre_tax' => $preTax ? 1 : 0, 'status' => 1,
        ])->assertSessionHasNoErrors();

        return PayItem::where('code', $code)->firstOrFail();
    }

    private function employee(array $overrides = []): Employee
    {
        $r = $this->actingAs($this->accountant)->post(route('accounting.payroll.employees.store'), $overrides + [
            'employee_number' => 'EMP0001', 'first_name' => 'Tendai', 'last_name' => 'Moyo', 'tin' => '2000123', 'nssa_number' => 'N123',
            'start_date' => '2025-01-01', 'basic_salary' => 1000, 'salary_currency' => 'USD', 'payment_method' => 'bank', 'bank_name' => 'CBZ', 'account_number' => '0123',
        ]); $r->assertSessionHasNoErrors()->assertRedirect();

        return Employee::where('employee_number', $overrides['employee_number'] ?? 'EMP0001')->firstOrFail();
    }

    private function newRun(): PayrollRun
    {
        $this->actingAs($this->accountant)->post(route('accounting.payroll.runs.store'), ['period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'pay_date' => '2026-06-28'])->assertSessionHasNoErrors();

        return PayrollRun::latest('id')->firstOrFail();
    }

    private function balance(string $code): float
    {
        $id = $this->ledgerAccount($this->company, $code)->id;

        return round((float) JournalLine::where('account_id', $id)->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'))->get()->sum(fn ($l) => (float) $l->debit - (float) $l->credit), 2);
    }

    public function test_pay_is_calculated_with_nssa_ceiling_pre_tax_deductions_credits_and_levies(): void
    {
        $this->usdSettings();
        $employee = $this->employee();
        $housing = $this->item('HOUSING', 'earning', 'allowance', 'fixed', 200);
        $pension = $this->item('PENSION', 'deduction', 'pension', 'percent_of_basic', 5, false, true);
        $medical = $this->item('MEDICAL', 'deduction', 'medical_aid', 'fixed', 40);
        foreach ([$housing, $pension, $medical] as $item) {
            $this->post(route('accounting.payroll.employees.items.store', $employee), ['pay_item_id' => $item->id])->assertSessionHasNoErrors();
        }

        $run = $this->newRun();
        $this->post(route('accounting.payroll.runs.calculate', $run))->assertSessionHas('success');
        $slip = Payslip::firstOrFail();

        // gross 1,200; NSSA on 700 = 31.50; taxable 1,200 − 50 pension − 31.50 = 1,118.50;
        // PAYE 1,118.50 × 30% − 50 = 285.55, less medical aid credit 50% × 40 = 265.55; AIDS levy 3% = 7.97;
        // net 1,200 − 265.55 − 7.97 − 31.50 − 90 = 804.98; employer: NSSA 31.50 + training levy 12.00.
        $this->assertSame([1200.0, 1118.5, 265.55, 7.97, 31.5, 31.5, 90.0, 12.0, 804.98],
            [(float) $slip->gross, (float) $slip->taxable, (float) $slip->paye, (float) $slip->aids_levy, (float) $slip->nssa_employee, (float) $slip->nssa_employer, (float) $slip->other_deductions, (float) $slip->employer_levies, (float) $slip->net]);
        $this->assertSame(1243.5, (float) $run->fresh()->total_cost_base);

        // The elderly credit applies once ticked on the employee.
        $employee->update(['tax_credits' => ['elderly']]);
        $this->post(route('accounting.payroll.runs.calculate', $run));
        $this->assertSame(190.55, (float) Payslip::firstOrFail()->paye);
    }

    public function test_mixed_usd_zwg_salary_is_taxed_on_the_usd_table_and_apportioned(): void
    {
        $this->usdSettings();
        $zwg = new Currency(['code' => 'ZWG', 'name' => 'Zimbabwe Gold', 'symbol' => 'ZiG', 'is_base' => false, 'status' => true]);
        $zwg->company_id = $this->company->id;
        $zwg->save();
        $rate = new ExchangeRate(['from_currency' => 'ZWG', 'to_currency' => 'USD', 'rate_date' => '2026-06-01', 'rate' => 0.04, 'source' => 'test']);
        $rate->company_id = $this->company->id;
        $rate->save();

        // No ZWG table is needed: mixed salaries use the USD table (ZIMRA).
        $this->employee(['secondary_currency' => 'ZWG', 'secondary_percent' => 25]);
        $run = $this->newRun();
        $this->post(route('accounting.payroll.runs.calculate', $run))->assertSessionHas('success');

        $usd = Payslip::where('currency', 'USD')->firstOrFail();
        $zig = Payslip::where('currency', 'ZWG')->firstOrFail();
        // Whole pay USD 1,000: NSSA 31.50 (ceiling 700); taxable 968.50 → 30% − 50 = 240.55.
        // Apportioned 75% to USD and 25% × 25 ZWG/USD to ZWG.
        $this->assertSame([750.0, 23.63, 180.41], [(float) $usd->gross, (float) $usd->nssa_employee, (float) $usd->paye]);
        $this->assertSame([6250.0, 196.88, 1503.44, 25.0], [(float) $zig->gross, (float) $zig->nssa_employee, (float) $zig->paye, (float) $zig->breakdown['fx_rate']]);
        $this->assertStringContainsString('ZIMRA rule for mixed salaries', $zig->breakdown['tax_basis']);

        $this->actingAs($this->manager)->post(route('accounting.payroll.runs.approve', $run))->assertSessionHasNoErrors();
        $this->assertSame(['PAY-PR-202606-USD', 'PAY-PR-202606-ZWG'], JournalEntry::where('source_type', PayrollRun::class)->orderBy('entry_number')->pluck('entry_number')->all());
        // Both journals post in base currency: 6100 carries 750 + 6,250 × 0.04 = 1,000 of gross pay.
        $this->assertSame(1000.0, $this->balance('6100'));
    }

    public function test_published_zimra_tables_match_zimras_worked_examples(): void
    {
        $zwg = new Currency(['code' => 'ZWG', 'name' => 'Zimbabwe Gold', 'symbol' => 'ZiG', 'is_base' => false, 'status' => true]);
        $zwg->company_id = $this->company->id;
        $zwg->save();

        // The settings screen offers the published figures before anything is saved.
        $this->actingAs($this->admin)->get(route('accounting.payroll.settings.index', ['year' => 2026, 'currency' => 'USD']))
            ->assertOk()->assertSee('Pre-filled with published figures')->assertSee('these are the 2025 tables');

        auth()->forgetUser();
        $this->artisan('payroll:load-published', ['year' => 2026])->assertSuccessful();
        $this->actingAs($this->admin);

        $usd = StatutorySetting::with('bands')->where('tax_year', 2026)->where('currency', 'USD')->firstOrFail();
        $zig = StatutorySetting::with('bands')->where('tax_year', 2026)->where('currency', 'ZWG')->firstOrFail();
        $calculator = app(\App\Modules\Payroll\Services\PayrollCalculator::class);
        $this->assertSame(455.0, $calculator->bandTax(1800, $usd)[0]);   // ZIMRA: $1 800 x 30% - $85.00 = US$455.00
        $this->assertSame(3520.0, $calculator->bandTax(18000, $zig)[0]); // ZIMRA: $18,000 x 25% - $980.00 = $3,520.00
        $this->assertSame([6, 6, 4.5, 700.0, 'USD', 3.0, 50.0], [$usd->bands->count(), $zig->bands->count(), (float) $usd->nssa_employee_rate, (float) $zig->nssa_ceiling, $zig->nssa_ceiling_currency, (float) $usd->aids_levy_rate, (float) $usd->medical_aid_credit_rate]);
        $this->assertSame(['elderly', 'blind', 'disabled'], array_column($usd->credits, 'code'));

        // A USD 1,800 salary: NSSA 31.50 (4.5% of the 700 ceiling) reduces taxable income to 1,768.50.
        $this->employee(['basic_salary' => 1800]);
        $run = $this->newRun();
        $this->post(route('accounting.payroll.runs.calculate', $run))->assertSessionHas('success');
        $slip = Payslip::firstOrFail();
        $this->assertSame([31.5, 1768.5, 445.55, 13.37, 1309.58], [(float) $slip->nssa_employee, (float) $slip->taxable, (float) $slip->paye, (float) $slip->aids_levy, (float) $slip->net]);
    }

    public function test_full_cycle_posts_the_payroll_and_net_pay(): void
    {
        $this->usdSettings();
        $employee = $this->employee();
        $this->employee(['employee_number' => 'EMP0002', 'first_name' => 'Rudo', 'start_date' => '2026-06-16', 'basic_salary' => 600, 'payment_method' => 'mobile_money', 'mobile_wallet' => '0771000000', 'account_number' => null]);
        $overtime = $this->item('OVERTIME', 'earning', 'overtime', 'hourly', 10);

        $run = $this->newRun();
        $this->post(route('accounting.payroll.runs.inputs.store', $run), ['employee_id' => $employee->id, 'pay_item_id' => $overtime->id, 'quantity' => 5])->assertSessionHasNoErrors();
        $this->post(route('accounting.payroll.runs.calculate', $run));

        $this->assertSame(1050.0, (float) Payslip::where('employee_id', $employee->id)->value('gross')); // 1,000 + 5 h × 10
        $this->assertSame(300.0, (float) Payslip::where('employee_id', '!=', $employee->id)->value('gross')); // joined 16 June: 15/30 of 600

        // Accountants prepare; approving and paying are controls.
        $this->post(route('accounting.payroll.runs.approve', $run))->assertForbidden();
        $this->actingAs($this->manager)->get(route('accounting.approvals-audit.index'))->assertSee($run->run_number);
        $this->post(route('accounting.payroll.runs.approve', $run))->assertSessionHasNoErrors();
        $run->refresh();
        $this->assertSame('approved', $run->status);

        $t = $run->totals['USD'];
        $this->assertSame(1350.0, $this->balance('6100'));
        $this->assertSame(round(-($t['paye'] + $t['aids_levy']), 2), $this->balance('2200'));
        $this->assertSame(round(-($t['nssa_employee'] + $t['nssa_employer']), 2), $this->balance('2210'));
        $this->assertSame(-$t['net'], $this->balance('2230'));
        $this->assertSame(round($t['nssa_employer'] + $t['employer_levies'], 2), $this->balance('6110'));

        // Paying out clears net wages from cash and mobile money.
        $this->post(route('accounting.payroll.runs.pay', $run))->assertSessionHasNoErrors();
        $this->assertSame(['paid', 0.0, -$t['net']], [$run->fresh()->status, $this->balance('2230'), $this->balance('1000')]);

        $this->get(route('accounting.payroll.runs.payslip', [$run, $employee]))->assertOk()->assertHeader('content-type', 'application/pdf');
        foreach (['register', 'paye', 'nssa', 'bank'] as $report) {
            $this->get(route('accounting.payroll.runs.export', [$run, $report]))->assertOk();
        }
        $this->assertStringContainsString('0771000000', $this->get(route('accounting.payroll.runs.export', [$run, 'bank']))->streamedContent());
    }

    public function test_calculation_is_blocked_until_statutory_settings_exist(): void
    {
        $this->employee();
        $run = $this->newRun();

        $this->get(route('accounting.payroll.runs.show', $run))->assertOk()->assertSee('No USD statutory settings for tax year 2026');
        $this->post(route('accounting.payroll.runs.calculate', $run))->assertSessionHasErrors('settings');
        $this->assertSame(0, Payslip::count());
    }

    public function test_only_company_admins_change_tax_tables_and_changes_are_audited(): void
    {
        $this->actingAs($this->manager)->post(route('accounting.payroll.settings.store'), ['tax_year' => 2026, 'currency' => 'USD', 'nssa_employee_rate' => 1, 'nssa_employer_rate' => 1, 'aids_levy_rate' => 1, 'bands' => [['lower' => 0, 'rate' => 0]]])->assertForbidden();
        $this->usdSettings();
        $this->assertSame(3, StatutorySetting::firstOrFail()->bands()->count());
        $this->assertTrue(\App\Modules\Accounting\Models\AuditLog::where('event', 'payroll.tax_table.saved')->exists());

        $this->actingAs($this->accountant)->get(route('accounting.payroll.settings.index'))->assertForbidden();
        $auditor = $this->ledgerUser($this->company, 'auditor');
        $this->actingAs($auditor)->get(route('accounting.payroll.settings.index'))->assertOk()->assertSee('Only a Company Admin');
        $this->post(route('accounting.payroll.employees.store'), [])->assertForbidden();
    }

    public function test_pages_render(): void
    {
        $this->usdSettings();
        $employee = $this->employee();
        $run = $this->newRun();
        $this->actingAs($this->accountant);
        foreach ([route('accounting.payroll.index'), route('accounting.payroll.employees.index'), route('accounting.payroll.employees.create'), route('accounting.payroll.employees.show', $employee),
            route('accounting.payroll.employees.edit', $employee), route('accounting.payroll.items.index'), route('accounting.payroll.runs.index'), route('accounting.payroll.runs.show', $run)] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
