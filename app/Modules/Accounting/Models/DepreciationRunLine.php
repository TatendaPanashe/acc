<?php

namespace App\Modules\Accounting\Models;

class DepreciationRunLine extends AccountingModel
{
    protected static bool $audited = false;

    protected $table = 'accounting_depreciation_run_lines';

    protected $fillable = ['company_id', 'depreciation_run_id', 'fixed_asset_id', 'months', 'amount'];

    protected $casts = ['amount' => 'decimal:2'];

    public function asset()
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }
}
