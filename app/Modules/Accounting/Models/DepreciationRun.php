<?php

namespace App\Modules\Accounting\Models;

class DepreciationRun extends AccountingModel
{
    protected $table = 'accounting_depreciation_runs';

    protected $fillable = ['company_id', 'accounting_period_id', 'run_date', 'total', 'journal_entry_id', 'created_by'];

    protected $casts = ['run_date' => 'date', 'total' => 'decimal:2'];

    public function period()
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    public function lines()
    {
        return $this->hasMany(DepreciationRunLine::class);
    }
}
