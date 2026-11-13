<?php

namespace App\Modules\Payroll\Models;

use App\Models\User;
use App\Modules\Accounting\Models\AccountingModel;

class OvertimeRequest extends AccountingModel
{
    protected $table = 'payroll_overtime_requests';

    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Declined', 'cancelled' => 'Cancelled'];

    protected $fillable = ['company_id', 'employee_id', 'work_date', 'hours', 'reason', 'status', 'pay_item_id', 'payroll_run_id', 'decided_by', 'decided_at', 'decision_note'];

    protected $casts = ['work_date' => 'date', 'hours' => 'decimal:2', 'decided_at' => 'datetime'];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function payItem() { return $this->belongsTo(PayItem::class); }
    public function run() { return $this->belongsTo(PayrollRun::class, 'payroll_run_id'); }
    public function decider() { return $this->belongsTo(User::class, 'decided_by'); }
}
