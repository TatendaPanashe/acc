<?php

namespace App\Modules\Payroll\Services;

use App\Models\User;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\LeaveRequest;
use App\Modules\Payroll\Models\LeaveType;
use App\Modules\Payroll\Models\OvertimeRequest;
use App\Modules\Payroll\Models\PayItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Leave and overtime requests from the employee portal, and their approval by payroll staff.
 *
 * Leave days are working days (Monday to Friday) between the dates; public holidays are not deducted
 * automatically. Leave types with a yearly entitlement track a balance per calendar (tax) year.
 * Approved overtime is paid in the payroll whose period contains the overtime date, as hours on the
 * chosen hourly earning (rate from the employee's own item or the item default).
 */
class SelfServiceRequestService
{
    /** Leave types for the current company, creating the defaults the first time. */
    public function leaveTypes(int $companyId): Collection
    {
        if (!LeaveType::withoutGlobalScopes()->where('company_id', $companyId)->exists()) {
            foreach (LeaveType::DEFAULTS as $type) {
                $model = new LeaveType($type + ['status' => true]);
                $model->company_id = $companyId;
                $model->save();
            }
        }

        return LeaveType::withoutGlobalScopes()->where('company_id', $companyId)->orderBy('id')->get();
    }

    public static function workingDays(Carbon $start, Carbon $end): int
    {
        $days = 0;
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $days += $d->isWeekend() ? 0 : 1;
        }

        return $days;
    }

    /** @return array<int, array{type: LeaveType, entitlement: ?float, taken: float, pending: float, remaining: ?float}> */
    public function balances(Employee $employee, int $year): array
    {
        $requests = LeaveRequest::withoutGlobalScopes()->where('employee_id', $employee->id)->whereIn('status', ['approved', 'pending'])
            ->whereYear('starts_on', $year)->get()->groupBy('leave_type_id');
        $result = [];
        foreach ($this->leaveTypes((int) $employee->company_id)->where('status', true) as $type) {
            $taken = (float) ($requests[$type->id] ?? collect())->where('status', 'approved')->sum('days');
            $pending = (float) ($requests[$type->id] ?? collect())->where('status', 'pending')->sum('days');
            $entitlement = $type->days_per_year !== null ? (float) $type->days_per_year : null;
            $result[$type->id] = ['type' => $type, 'entitlement' => $entitlement, 'taken' => $taken, 'pending' => $pending,
                'remaining' => $entitlement !== null ? round($entitlement - $taken - $pending, 2) : null];
        }

        return $result;
    }

    public function submitLeave(Employee $employee, array $data): LeaveRequest
    {
        $start = Carbon::parse($data['starts_on'])->startOfDay();
        $end = Carbon::parse($data['ends_on'])->startOfDay();
        $type = $this->leaveTypes((int) $employee->company_id)->where('status', true)->firstWhere('id', (int) $data['leave_type_id'])
            ?? throw ValidationException::withMessages(['leave_type_id' => 'Choose a leave type.']);
        $days = self::workingDays($start, $end);
        if ($days === 0) {
            throw ValidationException::withMessages(['ends_on' => 'Those dates contain no working days (Monday to Friday).']);
        }
        if ($start->year !== $end->year) {
            throw ValidationException::withMessages(['ends_on' => 'Split leave that crosses into a new year into two requests.']);
        }
        $overlap = LeaveRequest::withoutGlobalScopes()->where('employee_id', $employee->id)->whereIn('status', ['pending', 'approved'])
            ->whereDate('starts_on', '<=', $end->toDateString())->whereDate('ends_on', '>=', $start->toDateString())->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['starts_on' => 'You already have leave requested or approved on some of these days.']);
        }
        $balance = $this->balances($employee, $start->year)[$type->id] ?? null;
        if ($balance && $balance['remaining'] !== null && $days > $balance['remaining']) {
            throw ValidationException::withMessages(['ends_on' => 'That is '.$days.' working days; you have '.max(0, $balance['remaining']).' days of '.strtolower($type->name).' left for '.$start->year.'.']);
        }

        $request = new LeaveRequest(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'starts_on' => $start->toDateString(), 'ends_on' => $end->toDateString(),
            'days' => $days, 'reason' => $data['reason'] ?? null, 'status' => 'pending']);
        $request->company_id = $employee->company_id;
        $request->save();

        return $request;
    }

    public function submitOvertime(Employee $employee, array $data): OvertimeRequest
    {
        $date = Carbon::parse($data['work_date']);
        if ($date->gt(today())) {
            throw ValidationException::withMessages(['work_date' => 'Overtime can only be claimed for days already worked.']);
        }
        $request = new OvertimeRequest(['employee_id' => $employee->id, 'work_date' => $date->toDateString(), 'hours' => $data['hours'], 'reason' => $data['reason'], 'status' => 'pending']);
        $request->company_id = $employee->company_id;
        $request->save();

        return $request;
    }

    public function cancel(LeaveRequest|OvertimeRequest $request, Employee $employee): void
    {
        if ($request->employee_id !== $employee->id || $request->status !== 'pending') {
            throw ValidationException::withMessages(['request' => 'Only your own pending requests can be cancelled.']);
        }
        $request->update(['status' => 'cancelled']);
    }

    public function decideLeave(LeaveRequest $request, bool $approve, User $user, ?string $note = null): void
    {
        $this->assertPending($request);
        if ($approve) {
            $balance = $this->balances($request->employee, $request->starts_on->year)[$request->leave_type_id] ?? null;
            // The request's own days are already counted as pending in the balance.
            if ($balance && $balance['remaining'] !== null && $balance['remaining'] < 0) {
                throw ValidationException::withMessages(['request' => 'Approving this would take '.$request->employee->name.' past their '.strtolower($balance['type']->name).' entitlement.']);
            }
        }
        $request->update(['status' => $approve ? 'approved' : 'rejected', 'decided_by' => $user->id, 'decided_at' => now(), 'decision_note' => $note]);
    }

    public function decideOvertime(OvertimeRequest $request, bool $approve, User $user, ?int $payItemId = null, ?string $note = null): void
    {
        $this->assertPending($request);
        $payItem = null;
        if ($approve) {
            $payItem = PayItem::where('type', 'earning')->where('calculation', 'hourly')->where('status', true)
                ->when($payItemId, fn ($q) => $q->whereKey($payItemId), fn ($q) => $q->orderByRaw("CASE category WHEN 'overtime' THEN 0 ELSE 1 END"))
                ->first() ?? throw ValidationException::withMessages(['pay_item_id' => 'Create an hourly overtime earning under Payroll → Pay items first.']);
        }
        $request->update(['status' => $approve ? 'approved' : 'rejected', 'pay_item_id' => $payItem?->id, 'decided_by' => $user->id, 'decided_at' => now(), 'decision_note' => $note]);
    }

    private function assertPending(LeaveRequest|OvertimeRequest $request): void
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['request' => 'This request has already been '.$request->status.'.']);
        }
    }
}
