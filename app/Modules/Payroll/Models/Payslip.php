<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Accounting\Models\AccountingModel;

/** Calculated pay for one employee in one currency in one run. */
class Payslip extends AccountingModel
{
    protected static bool $audited = false; // recalculated in bulk; the run itself is audited

    protected $table = 'payroll_payslips';

    protected $fillable = [
        'company_id', 'payroll_run_id', 'employee_id', 'currency', 'gross', 'taxable', 'paye', 'aids_levy', 'nssa_employee',
        'nssa_employer', 'other_deductions', 'employer_levies', 'net', 'breakdown',
    ];

    protected $casts = [
        'gross' => 'decimal:2', 'taxable' => 'decimal:2', 'paye' => 'decimal:2', 'aids_levy' => 'decimal:2', 'nssa_employee' => 'decimal:2',
        'nssa_employer' => 'decimal:2', 'other_deductions' => 'decimal:2', 'employer_levies' => 'decimal:2', 'net' => 'decimal:2', 'breakdown' => 'array',
    ];

    public function run() { return $this->belongsTo(PayrollRun::class, 'payroll_run_id'); }
    public function employee() { return $this->belongsTo(Employee::class); }
}
