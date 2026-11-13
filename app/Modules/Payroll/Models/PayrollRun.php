<?php

namespace App\Modules\Payroll\Models;

use App\Models\User;
use App\Modules\Accounting\Models\AccountingModel;

class PayrollRun extends AccountingModel
{
    protected $table = 'payroll_runs';

    public const STATUSES = ['draft' => 'Draft', 'approved' => 'Approved & posted', 'paid' => 'Paid', 'cancelled' => 'Cancelled'];

    protected $fillable = [
        'company_id', 'run_number', 'period_start', 'period_end', 'pay_date', 'status', 'employee_count', 'total_cost_base', 'totals',
        'calculated_at', 'created_by', 'approved_by', 'approved_at', 'paid_at', 'journal_entry_ids', 'notes',
    ];

    protected $casts = [
        'period_start' => 'date', 'period_end' => 'date', 'pay_date' => 'date', 'total_cost_base' => 'decimal:2', 'totals' => 'array',
        'calculated_at' => 'datetime', 'approved_at' => 'datetime', 'paid_at' => 'datetime', 'journal_entry_ids' => 'array',
    ];

    public function payslips() { return $this->hasMany(Payslip::class)->orderBy('employee_id')->orderBy('currency'); }
    public function inputs() { return $this->hasMany(RunInput::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }

    public function taxYear(): int
    {
        return (int) $this->period_end->year;
    }
}
