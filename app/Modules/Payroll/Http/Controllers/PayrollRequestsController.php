<?php

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\LeaveRequest;
use App\Modules\Payroll\Models\LeaveType;
use App\Modules\Payroll\Models\OvertimeRequest;
use App\Modules\Payroll\Models\PayItem;
use App\Modules\Payroll\Services\EmployeePortalService;
use App\Modules\Payroll\Services\SelfServiceRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Back office: leave and overtime requests from the employee portal, leave types, portal access. */
class PayrollRequestsController extends Controller
{
    public function __construct(
        private readonly SelfServiceRequestService $requests,
        private readonly EmployeePortalService $portal,
    ) {
    }

    public function index(Request $request): View
    {
        $companyId = (int) $request->user()->company_id;

        return view('payroll.requests', [
            'leave' => LeaveRequest::with(['employee', 'type'])->where('status', 'pending')->orderBy('starts_on')->get(),
            'overtime' => OvertimeRequest::with('employee')->where('status', 'pending')->orderBy('work_date')->get(),
            'decided' => LeaveRequest::with(['employee', 'type', 'decider'])->whereIn('status', ['approved', 'rejected'])->latest('decided_at')->limit(15)->get(),
            'decidedOvertime' => OvertimeRequest::with(['employee', 'decider', 'run', 'payItem'])->whereIn('status', ['approved', 'rejected'])->latest('decided_at')->limit(15)->get(),
            'types' => $this->requests->leaveTypes($companyId),
            'overtimeItems' => PayItem::where('type', 'earning')->where('calculation', 'hourly')->where('status', true)->orderByRaw("CASE category WHEN 'overtime' THEN 0 ELSE 1 END")->get(),
            'onLeaveToday' => LeaveRequest::with(['employee', 'type'])->where('status', 'approved')->whereDate('starts_on', '<=', today()->toDateString())->whereDate('ends_on', '>=', today()->toDateString())->get(),
        ]);
    }

    public function decideLeave(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'note' => ['nullable', 'string', 'max:255', 'required_if:decision,reject']]);
        $this->requests->decideLeave($leave, $data['decision'] === 'approve', $request->user(), $data['note'] ?? null);

        return back()->with('success', 'Leave for '.$leave->employee->name.' '.($data['decision'] === 'approve' ? 'approved' : 'declined').'.');
    }

    public function decideOvertime(Request $request, OvertimeRequest $overtime): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'pay_item_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:255', 'required_if:decision,reject'],
        ]);
        $this->requests->decideOvertime($overtime, $data['decision'] === 'approve', $request->user(), isset($data['pay_item_id']) ? (int) $data['pay_item_id'] : null, $data['note'] ?? null);

        return back()->with('success', 'Overtime for '.$overtime->employee->name.' '.($data['decision'] === 'approve' ? 'approved: it will be paid in the payroll for '.$overtime->work_date->format('F Y').'.' : 'declined.'));
    }

    public function storeLeaveType(Request $request): RedirectResponse
    {
        LeaveType::create($this->validatedType($request));

        return back()->with('success', 'Leave type added.');
    }

    public function updateLeaveType(Request $request, LeaveType $type): RedirectResponse
    {
        $type->update($this->validatedType($request, $type));

        return back()->with('success', 'Leave type updated.');
    }

    private function validatedType(Request $request, ?LeaveType $type = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('payroll_leave_types')->where('company_id', $request->user()->company_id)->ignore($type)],
            'name' => ['required', 'max:255'],
            'days_per_year' => ['nullable', 'numeric', 'min:0', 'max:366'],
            'paid' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
        ]);

        return ['days_per_year' => $data['days_per_year'] ?? null, 'paid' => $request->boolean('paid'), 'status' => $request->boolean('status')] + $data;
    }

    public function issuePortalAccess(Employee $employee): RedirectResponse
    {
        if (!$employee->email) {
            return back()->with('error', 'Add a work email for '.$employee->name.' first: it is their portal sign-in.');
        }
        $password = $this->portal->issueTemporaryPassword($employee);

        return back()->with('portal_password', $password)->with('success', 'Portal access set up for '.$employee->name.'. Give them the one-time password below; they must change it when they first sign in.');
    }

    public function revokePortalAccess(Employee $employee): RedirectResponse
    {
        $this->portal->revoke($employee);

        return back()->with('success', 'Portal access removed for '.$employee->name.'.');
    }
}
