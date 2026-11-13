<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Accounting\Models\AccountingModel;

class TaxBand extends AccountingModel
{
    protected static bool $audited = false; // changes are audited through the settings screen

    protected $table = 'payroll_tax_bands';

    protected $fillable = ['company_id', 'statutory_setting_id', 'lower', 'upper', 'rate', 'deduct'];

    protected $casts = ['lower' => 'decimal:2', 'upper' => 'decimal:2', 'rate' => 'decimal:3', 'deduct' => 'decimal:2'];
}
