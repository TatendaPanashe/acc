<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalYear extends AccountingModel
{
    protected $table = 'accounting_fiscal_years';

    protected $fillable = ['company_id', 'name', 'starts_on', 'ends_on', 'status'];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function periods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class, 'fiscal_year_id');
    }
}
