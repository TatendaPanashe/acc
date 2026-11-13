<?php

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\LeaveRequest;
use App\Modules\Payroll\Models\OvertimeRequest;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Services\EmployeePortalService;
use App\Modules\Payroll\Services\PayrollRunService;
use App\Modules\Payroll\Services\SelfServiceRequestService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Employee self-service: payslips, leave and overtime requests. Every page runs behind the `staff.portal`
 * middleware, which scopes queries to the employee's company; each query is also limited to the employee.
 */
class EmployeePortalController extends Controller
{
    public function __construct(
        private readonly EmployeePortalService $portal,
        private readonly SelfServiceRequestService $requests,
    ) {
    }

    private function employee(Request $request): Employee
    {
        return $request->attributes->get('employee');
    }

    // ---- sign in -----------------------------------------------------------------------

    public function loginForm(Request $request): View|RedirectResponse
    {
        return $this->portal->current($request) ? redirect()->route('staff.dashboard') : view('staff.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $employee = $this->portal->find($data['email'], $data['password']);
        if (!$employee) {
            return back()->withErrors(['email' => 'Those sign-in details are not correct, or portal access has not been set up for you.'])->onlyInput('email');
        }
        $this->portal->startSession($request, $employee);

        return redirect()->route($request->session()->get('staff_must_change_password') ? 'staff.password.edit' : 'staff.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->portal->endSession($request);

        return redirect()->route('staff.login')->with('success', 'You have signed out.');
    }

    public function passwordForm(): View
    {
        return view('staff.password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $employee = $this->employee($request);
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        if (!Hash::check($data['current_password'], $employee->portal_password)) {
            return back()->withErrors(['current_password' => 'Your current password is not correct.']);
        }
        if (Hash::check($data['password'], $employee->portal_password)) {
            return back()->withErrors(['password' => 'Choose a password different from your current one.']);
        }
        $employee->forceFill(['portal_password' => Hash::make($data['password']), 'portal_password_changed_at' => now()])->save();
        $request->session()->forget('staff_must_change_password');

        return redirect()->route('staff.dashboard')->with('success', 'Password changed.');
    }

    // ---- pages -----------------------------------------------------------------------------

    public function dashboard(Request $request): View
    {
        $employee = $this->employee($request);

        return view('staff.dashboard', [
            'employee' => $employee,
            'company' => Company::find($employee->company_id),
            'latest' => $this->payslipQuery($employee)->first(),
            'balances' => $this->requests->balances($employee, now()->year),
            'pendingLeave' => $employee->leaveRequests()->where('status', 'pending')->count(),
            'pendingOvertime' => $employee->overtimeRequests()->where('status', 'pending')->count(),
            'upcoming' => $employee->leaveRequests()->with('type')->where('status', 'approved')->whereDate('ends_on', '>=', today()->toDateString())->orderBy('starts_on')->limit(3)->get(),
        ]);
    }

    public function payslips(Request $request): View
    {
        $employee = $this->employee($request);

        return view('staff.payslips', ['runs' => $this->payslipQuery($employee)->get(), 'employee' => $employee]);
    }

    /** Approved or paid payrolls with a payslip for this employee, newest first. */
    private function payslipQuery(Employee $employee)
    {
        return PayrollRun::whereIn('status', ['approved', 'paid'])
            ->whereHas('payslips', fn ($q) => $q->where('employee_id', $employee->id))
            ->with(['payslips' => fn ($q) => $q->where('employee_id', $employee->id)])
            ->latest('period_end');
    }

    public function payslipPdf(Request $request, int $run): Response
    {
        $employee = $this->employee($request);
        $payroll = PayrollRun::whereIn('status', ['approved', 'paid'])->findOrFail($run);
        $slips = Payslip::where('payroll_run_id', $payroll->id)->where('employee_id', $employee->id)->get();
        abort_if($slips->isEmpty(), 404);

        return Pdf::loadView('payroll.payslip-pdf', [
            'run' => $payroll, 'employee' => $employee, 'slips' => $slips, 'company' => Company::find($employee->company_id),
            'ytd' => app(PayrollRunService::class)->yearToDate($employee, $payroll),
        ])->setPaper('a4', 'portrait')->stream('payslip-'.$payroll->run_number.'.pdf');
    }

    public function leave(Request $request): View
    {
        $employee = $this->employee($request);

        return view('staff.leave', [
            'employee' => $employee,
            'types' => $this->requests->leaveTypes((int) $employee->company_id)->where('status', true),
            'balances' => $this->requests->balances($employee, now()->year),
            'leave' => $employee->leaveRequests()->with('type')->limit(50)->get(),
        ]);
    }

    public function storeLeave(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'leave_type_id' => ['required', 'integer'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $leave = $this->requests->submitLeave($this->employee($request), $data);

        return back()->with('success', 'Leave request for '.rtrim(rtrim(number_format((float) $leave->days, 2), '0'), '.').' working days sent for approval.');
    }

    public function cancelLeave(Request $request, int $leave): RedirectResponse
    {
        $this->requests->cancel(LeaveRequest::where('employee_id', $this->employee($request)->id)->findOrFail($leave), $this->employee($request));

        return back()->with('success', 'Leave request cancelled.');
    }

    public function overtime(Request $request): View
    {
        $employee = $this->employee($request);

        return view('staff.overtime', ['employee' => $employee, 'overtime' => $employee->overtimeRequests()->with('run')->limit(50)->get()]);
    }

    public function storeOvertime(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'work_date' => ['required', 'date', 'after:'.today()->subDays(90)->toDateString()],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $this->requests->submitOvertime($this->employee($request), $data);

        return back()->with('success', 'Overtime claim sent for approval.');
    }

    public function cancelOvertime(Request $request, int $overtime): RedirectResponse
    {
        $this->requests->cancel(OvertimeRequest::where('employee_id', $this->employee($request)->id)->findOrFail($overtime), $this->employee($request));

        return back()->with('success', 'Overtime claim cancelled.');
    }
}
