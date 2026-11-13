<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Accounting\Models\AccountingModel;

/** Payroll employee (company-scoped). Changes, including bank details, go to the audit trail. */
class Employee extends AccountingModel
{
    protected $table = 'payroll_employees';

    public const PAYMENT_METHODS = ['bank' => 'Bank transfer', 'mobile_money' => 'Mobile money', 'cash' => 'Cash'];

    protected $fillable = [
        'company_id', 'employee_number', 'first_name', 'last_name', 'email', 'phone', 'national_id', 'tin', 'nssa_number',
        'date_of_birth', 'gender', 'job_title', 'department', 'cost_centre', 'start_date', 'end_date', 'status',
        'basic_salary', 'salary_currency', 'secondary_currency', 'secondary_percent', 'payment_method',
        'bank_name', 'bank_branch', 'account_number', 'mobile_wallet', 'tax_credits', 'notes',
    ];

    protected $hidden = ['portal_password'];

    protected $casts = [
        'portal_password_changed_at' => 'datetime', 'portal_last_login_at' => 'datetime',
        'date_of_birth' => 'date', 'start_date' => 'date', 'end_date' => 'date',
        'basic_salary' => 'decimal:2', 'secondary_percent' => 'decimal:2', 'tax_credits' => 'array',
    ];

    public function items() { return $this->hasMany(EmployeeItem::class); }
    public function payslips() { return $this->hasMany(Payslip::class); }
    public function leaveRequests() { return $this->hasMany(LeaveRequest::class)->latest('starts_on'); }
    public function overtimeRequests() { return $this->hasMany(OvertimeRequest::class)->latest('work_date'); }

    public function getNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /** Employed for at least part of the period. */
    public function isPayableIn(\Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end): bool
    {
        return $this->start_date->lte($end) && (!$this->end_date || $this->end_date->gte($start));
    }

    /** [currency => share 0..1] */
    public function currencySplit(): array
    {
        $second = (float) $this->secondary_percent / 100;
        if (!$this->secondary_currency || $second <= 0 || $this->secondary_currency === $this->salary_currency) {
            return [$this->salary_currency => 1.0];
        }

        return [$this->salary_currency => round(1 - $second, 6), $this->secondary_currency => round($second, 6)];
    }
}
