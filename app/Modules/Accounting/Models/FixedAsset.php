<?php

namespace App\Modules\Accounting\Models;

class FixedAsset extends AccountingModel
{
    protected $table = 'accounting_fixed_assets';

    protected $fillable = [
        'company_id', 'asset_number', 'name', 'category', 'acquired_on', 'cost',
        'salvage_value', 'accumulated_depreciation', 'depreciated_to', 'useful_life_months', 'depreciation_method', 'status',
    ];

    protected $casts = [
        'acquired_on' => 'date',
        'depreciated_to' => 'date',
        'cost' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
    ];

    public function netBookValue(): float
    {
        return round((float) $this->cost - (float) $this->accumulated_depreciation, 2);
    }
}
