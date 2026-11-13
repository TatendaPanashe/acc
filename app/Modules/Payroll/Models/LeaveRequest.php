<?php

namespace App\Modules\Payroll\Models;

use App\Models\User;
use App\Modules\Accounting\Models\AccountingModel;

class LeaveRequest extends AccountingModel
{
    protected $table = 'payroll_leave_requests';

    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Declined', 'cancelled' => 'Cancelled'];

    protected $fillable = ['company_id', 'employee_id', 'leave_type_id', 'starts_on', 'ends_on', 'days', 'reason', 'status', 'decided_by', 'decided_at', 'decision_note'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'days' => 'decimal:2', 'decided_at' => 'datetime'];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function type() { return $this->belongsTo(LeaveType::class, 'leave_type_id'); }
    public function decider() { return $this->belongsTo(User::class, 'decided_by'); }
}
