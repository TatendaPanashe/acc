<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingModel;

class PayItem extends AccountingModel
{
    protected $table = 'payroll_pay_items';

    public const EARNING_CATEGORIES = ['allowance' => 'Allowance', 'overtime' => 'Overtime', 'bonus' => 'Bonus', 'commission' => 'Commission', 'benefit' => 'Benefit'];
    public const DEDUCTION_CATEGORIES = ['pension' => 'Pension', 'medical_aid' => 'Medical aid', 'loan' => 'Loan / advance', 'union' => 'Union dues', 'other' => 'Other'];
    public const CALCULATIONS = ['fixed' => 'Fixed amount', 'percent_of_basic' => '% of basic salary', 'hourly' => 'Hours × rate'];

    protected $fillable = ['company_id', 'code', 'name', 'type', 'category', 'calculation', 'default_amount', 'taxable', 'pre_tax', 'gl_account_id', 'status'];

    protected $casts = ['default_amount' => 'decimal:4', 'taxable' => 'boolean', 'pre_tax' => 'boolean', 'status' => 'boolean'];

    public function account() { return $this->belongsTo(Account::class, 'gl_account_id'); }
}
