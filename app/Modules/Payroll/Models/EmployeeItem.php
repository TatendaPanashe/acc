<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Accounting\Models\AccountingModel;

/** A standing earning or deduction for an employee. */
class EmployeeItem extends AccountingModel
{
    protected $table = 'payroll_employee_items';

    protected $fillable = ['company_id', 'employee_id', 'pay_item_id', 'amount', 'quantity', 'starts_on', 'ends_on'];

    protected $casts = ['amount' => 'decimal:4', 'quantity' => 'decimal:4', 'starts_on' => 'date', 'ends_on' => 'date'];

    public function payItem() { return $this->belongsTo(PayItem::class); }
    public function employee() { return $this->belongsTo(Employee::class); }

    public function activeIn(\Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end): bool
    {
        return (!$this->starts_on || $this->starts_on->lte($end)) && (!$this->ends_on || $this->ends_on->gte($start));
    }
}
