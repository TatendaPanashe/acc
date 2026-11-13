<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Accounting\Models\AccountingModel;

class LeaveType extends AccountingModel
{
    protected $table = 'payroll_leave_types';

    /** Created for a company the first time leave is used; entitlements are left for the company to set. */
    public const DEFAULTS = [
        ['code' => 'annual', 'name' => 'Annual leave', 'paid' => true],
        ['code' => 'sick', 'name' => 'Sick leave', 'paid' => true],
        ['code' => 'maternity', 'name' => 'Maternity leave', 'paid' => true],
        ['code' => 'compassionate', 'name' => 'Compassionate leave', 'paid' => true],
        ['code' => 'study', 'name' => 'Study leave', 'paid' => true],
        ['code' => 'unpaid', 'name' => 'Unpaid leave', 'paid' => false],
    ];

    protected $fillable = ['company_id', 'code', 'name', 'days_per_year', 'paid', 'status'];

    protected $casts = ['days_per_year' => 'decimal:2', 'paid' => 'boolean', 'status' => 'boolean'];
}
