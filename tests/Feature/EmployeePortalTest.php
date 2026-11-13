<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\LeaveRequest;
use App\Modules\Payroll\Models\LeaveType;
use App\Modules\Payroll\Models\OvertimeRequest;
use App\Modules\Payroll\Models\PayItem;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\Payslip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsLedger;
use Tests\TestCase;

/** Tax figures here are made-up test data. */
class EmployeePortalTest extends TestCase
{
    use BuildsLedger, RefreshDatabase;

    private Company $company;
    private User $admin;
    private User $manager;
    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-10 09:00:00'); // a Wednesday
        $this->company = $this->createLedgerCompany();
        $this->admin = $this->ledgerUser($this->company, 'admin');
        $this->manager = $this->ledgerUser($this->company, 'finance_manager');
        $this->accountant = $this->ledgerUser($this->company, 'accountant');

        $this->actingAs($this->admin)->post(route('accounting.payroll.settings.store'), [
            'tax_year' => 2026, 'currency' => 'USD', 'nssa_employee_rate' => 0, 'nssa_employer_rate' => 0, 'aids_levy_rate' => 0,
            'bands' => [['lower' => 0, 'upper' => null, 'rate' => 10, 'deduct' => 0]],
        ])->assertSessionHasNoErrors();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function employee(string $number = 'EMP0001', string $email = 'tendai@example.com', ?Company $company = null): Employee
    {
        $employee = new Employee(['employee_number' => $number, 'first_name' => 'Tendai', 'last_name' => 'Moyo', 'email' => $email, 'start_date' => '2025-01-01',
            'basic_salary' => 1000, 'salary_currency' => 'USD', 'payment_method' => 'cash', 'status' => 'active']);
        $employee->company_id = ($company ?? $this->company)->id;
        $employee->saveQuietly();

        return $employee;
    }

    /** Payroll staff issue a one-time password; the employee signs in and sets their own password. */
    private function signIn(Employee $employee): void
    {
        $this->actingAs($this->manager)->post(route('accounting.payroll.employees.portal.issue', $employee))->assertSessionHas('portal_password');
        $temporary = session('portal_password');
        auth()->logout();

        $this->post(route('staff.login.submit'), ['email' => $employee->email, 'password' => $temporary])->assertRedirect(route('staff.password.edit'));
        $this->get(route('staff.payslips'))->assertRedirect(route('staff.password.edit')); // must change first
        $this->post(route('staff.password.update'), ['current_password' => $temporary, 'password' => 'my-new-password', 'password_confirmation' => 'my-new-password'])->assertRedirect(route('staff.dashboard'));
    }

    private function runPayroll(): PayrollRun
    {
        $this->actingAs($this->accountant)->post(route('accounting.payroll.runs.store'), ['period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'pay_date' => '2026-06-28']);
        $run = PayrollRun::latest('id')->first();
        $this->post(route('accounting.payroll.runs.calculate', $run))->assertSessionHas('success');

        return $run;
    }

    public function test_sign_in_needs_access_and_a_new_password(): void
    {
        $employee = $this->employee();
        $this->post(route('staff.login.submit'), ['email' => $employee->email, 'password' => 'anything'])->assertSessionHasErrors('email');

        // Accountants cannot hand out portal access.
        $this->actingAs($this->accountant)->post(route('accounting.payroll.employees.portal.issue', $employee))->assertForbidden();
        auth()->logout();

        $this->signIn($employee);
        $this->get(route('staff.dashboard'))->assertOk()->assertSee('Hello, Tendai');
        $this->assertNotNull($employee->fresh()->portal_password_changed_at);

        $this->post(route('staff.logout'));
        $this->get(route('staff.dashboard'))->assertRedirect(route('staff.login'));
        $this->post(route('staff.login.submit'), ['email' => $employee->email, 'password' => 'my-new-password'])->assertRedirect(route('staff.dashboard'));

        // Leaving the company ends portal access.
        $employee->forceFill(['status' => 'terminated'])->saveQuietly();
        $this->get(route('staff.dashboard'))->assertRedirect(route('staff.login'));
    }

    public function test_employees_see_only_their_own_approved_payslips(): void
    {
        $tendai = $this->employee();
        $rudo = $this->employee('EMP0002', 'rudo@example.com');
        $run = $this->runPayroll();

        $this->signIn($tendai);
        $this->get(route('staff.payslips'))->assertOk()->assertDontSee('June 2026'); // draft payroll: not visible yet
        $this->get(route('staff.payslips.pdf', $run->id))->assertNotFound();

        $this->actingAs($this->manager)->post(route('accounting.payroll.runs.approve', $run))->assertSessionHasNoErrors();
        auth()->logout();
        $this->post(route('staff.login.submit'), ['email' => $tendai->email, 'password' => 'my-new-password']);

        $this->get(route('staff.payslips'))->assertOk()->assertSee('June 2026')->assertSee('USD 900.00');
        $this->get(route('staff.payslips.pdf', $run->id))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame(2, Payslip::withoutGlobalScopes()->count());
        $this->assertNotNull($rudo);
    }

    public function test_another_companys_employee_cannot_reach_these_records(): void
    {
        $mine = $this->employee();
        $otherCompany = $this->createLedgerCompany('Other');
        $this->ledgerUser($otherCompany, 'admin');
        $theirs = $this->employee('X1', 'x@example.com', $otherCompany);

        $this->signIn($mine);
        $this->get(route('staff.leave'))->assertOk(); // creates the default leave types
        $this->post(route('staff.leave.store'), ['leave_type_id' => LeaveType::withoutGlobalScopes()->where('company_id', $this->company->id)->value('id'), 'starts_on' => '2026-06-15', 'ends_on' => '2026-06-16'])->assertSessionHasNoErrors();
        $myLeave = LeaveRequest::withoutGlobalScopes()->firstOrFail();
        $this->post(route('staff.logout'));

        $theirs->forceFill(['portal_password' => bcrypt('their-password'), 'portal_password_changed_at' => now()])->saveQuietly();
        $this->post(route('staff.login.submit'), ['email' => 'x@example.com', 'password' => 'their-password'])->assertRedirect(route('staff.dashboard'));
        $this->post(route('staff.leave.cancel', $myLeave->id))->assertNotFound();
        $this->get(route('staff.leave'))->assertOk()->assertDontSee('15 Jun');
        $this->assertSame('pending', $myLeave->fresh()->status);
    }

    public function test_leave_requests_respect_working_days_balances_and_overlaps(): void
    {
        $employee = $this->employee();
        $this->signIn($employee);
        $this->get(route('staff.leave'))->assertOk()->assertSee('Annual leave'); // default types created
        $annual = LeaveType::withoutGlobalScopes()->where('company_id', $this->company->id)->where('code', 'annual')->firstOrFail();
        $annual->forceFill(['days_per_year' => 8])->saveQuietly();

        // Mon 15 – Sun 21 June = 5 working days.
        $this->post(route('staff.leave.store'), ['leave_type_id' => $annual->id, 'starts_on' => '2026-06-15', 'ends_on' => '2026-06-21', 'reason' => 'Family visit'])->assertSessionHasNoErrors();
        $this->assertSame(5.0, (float) LeaveRequest::withoutGlobalScopes()->value('days'));

        $this->post(route('staff.leave.store'), ['leave_type_id' => $annual->id, 'starts_on' => '2026-06-19', 'ends_on' => '2026-06-22'])->assertSessionHasErrors('starts_on'); // overlap
        $this->post(route('staff.leave.store'), ['leave_type_id' => $annual->id, 'starts_on' => '2026-07-06', 'ends_on' => '2026-07-09'])->assertSessionHasErrors('ends_on'); // 4 > 3 left
        $this->post(route('staff.leave.store'), ['leave_type_id' => $annual->id, 'starts_on' => '2026-06-27', 'ends_on' => '2026-06-28'])->assertSessionHasErrors('ends_on'); // weekend only

        $leave = LeaveRequest::withoutGlobalScopes()->firstOrFail();
        $this->post(route('staff.logout'));

        // Accountants see requests but cannot decide; managers approve.
        $this->actingAs($this->accountant)->get(route('accounting.payroll.requests.index'))->assertOk()->assertSee('Family visit');
        $this->post(route('accounting.payroll.requests.leave.decide', $leave), ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($this->manager)->post(route('accounting.payroll.requests.leave.decide', $leave), ['decision' => 'reject'])->assertSessionHasErrors('note');
        $this->post(route('accounting.payroll.requests.leave.decide', $leave), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $leave->fresh()->status);
        auth()->logout();

        $this->post(route('staff.login.submit'), ['email' => $employee->email, 'password' => 'my-new-password']);
        $this->get(route('staff.dashboard'))->assertOk()->assertSee('of 8 days left');
    }

    public function test_approved_overtime_is_paid_once_in_the_months_payroll(): void
    {
        $employee = $this->employee();
        $this->signIn($employee);
        $this->post(route('staff.overtime.store'), ['work_date' => '2026-06-12', 'hours' => 3, 'reason' => 'Stock take'])->assertSessionHasErrors('work_date'); // future
        $this->post(route('staff.overtime.store'), ['work_date' => '2026-06-06', 'hours' => 3, 'reason' => 'Stock take'])->assertSessionHasNoErrors();
        $claim = OvertimeRequest::withoutGlobalScopes()->firstOrFail();
        $this->post(route('staff.logout'));

        // No hourly overtime item yet: approval explains what is missing.
        $this->actingAs($this->manager)->post(route('accounting.payroll.requests.overtime.decide', $claim), ['decision' => 'approve'])->assertSessionHasErrors('pay_item_id');
        $item = PayItem::create(['code' => 'OT', 'name' => 'Overtime', 'type' => 'earning', 'category' => 'overtime', 'calculation' => 'hourly', 'default_amount' => 15, 'taxable' => true, 'pre_tax' => false, 'status' => true]);
        $this->post(route('accounting.payroll.requests.overtime.decide', $claim), ['decision' => 'approve', 'pay_item_id' => $item->id])->assertSessionHasNoErrors();

        $run = $this->runPayroll();
        $this->assertSame(1045.0, (float) Payslip::where('payroll_run_id', $run->id)->value('gross')); // 1,000 + 3 h × 15
        $this->actingAs($this->manager)->post(route('accounting.payroll.runs.approve', $run))->assertSessionHasNoErrors();
        $this->assertSame($run->id, $claim->fresh()->payroll_run_id);

        // A second June run does not pay it again.
        $this->actingAs($this->accountant)->post(route('accounting.payroll.runs.store'), ['period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'pay_date' => '2026-06-30']);
        $second = PayrollRun::latest('id')->first();
        $this->post(route('accounting.payroll.runs.calculate', $second));
        $this->assertSame(1000.0, (float) Payslip::where('payroll_run_id', $second->id)->value('gross'));

        auth()->logout();
        $this->post(route('staff.login.submit'), ['email' => $employee->email, 'password' => 'my-new-password']);
        $this->get(route('staff.overtime'))->assertOk()->assertSee('Paid in June 2026 payroll');
    }
}
