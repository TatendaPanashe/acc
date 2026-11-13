<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Accounting\Models\AccountingModel;

/** NSSA, AIDS levy, credits, levies and the PAYE table for one tax year and currency. */
class StatutorySetting extends AccountingModel
{
    protected $table = 'payroll_statutory_settings';

    protected $fillable = [
        'company_id', 'tax_year', 'currency', 'nssa_employee_rate', 'nssa_employer_rate', 'nssa_ceiling', 'nssa_ceiling_currency', 'nssa_deductible',
        'aids_levy_rate', 'medical_aid_credit_rate', 'credits', 'levies',
    ];

    protected $casts = [
        'nssa_employee_rate' => 'decimal:3', 'nssa_employer_rate' => 'decimal:3', 'nssa_ceiling' => 'decimal:2', 'nssa_deductible' => 'boolean',
        'aids_levy_rate' => 'decimal:3', 'medical_aid_credit_rate' => 'decimal:3', 'credits' => 'array', 'levies' => 'array',
    ];

    public function bands() { return $this->hasMany(TaxBand::class)->orderBy('lower'); }
}
