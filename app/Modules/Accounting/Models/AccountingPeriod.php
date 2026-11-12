<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPeriod extends AccountingModel
{
    protected $table = 'accounting_periods';

    protected $fillable = ['company_id', 'fiscal_year_id', 'name', 'starts_on', 'ends_on', 'status'];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }
}
