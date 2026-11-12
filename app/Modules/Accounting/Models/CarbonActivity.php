<?php

namespace App\Modules\Accounting\Models;

class CarbonActivity extends AccountingModel
{
    protected $table = 'accounting_carbon_activities';

    protected $fillable = [
        'company_id', 'activity_code', 'scope', 'category', 'quantity', 'unit',
        'emission_factor', 'co2e', 'activity_date', 'evidence_reference', 'status',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'emission_factor' => 'decimal:6',
        'co2e' => 'decimal:4',
        'activity_date' => 'date',
    ];
}
