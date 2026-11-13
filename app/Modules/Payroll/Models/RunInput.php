<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Accounting\Models\AccountingModel;

/** A variable earning or deduction for one employee in one run (overtime hours, a bonus...). */
class RunInput extends AccountingModel
{
    protected $table = 'payroll_run_inputs';

    protected $fillable = ['company_id', 'payroll_run_id', 'employee_id', 'pay_item_id', 'quantity', 'amount', 'overtime_request_id'];

    protected $casts = ['quantity' => 'decimal:4', 'amount' => 'decimal:4'];

    public function payItem() { return $this->belongsTo(PayItem::class); }
    public function employee() { return $this->belongsTo(Employee::class); }
}
