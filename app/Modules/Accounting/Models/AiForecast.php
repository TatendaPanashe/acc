<?php

namespace App\Modules\Accounting\Models;

use App\Models\User;

class AiForecast extends AccountingModel
{
    protected static bool $audited = false;

    protected $table = 'accounting_ai_forecasts';

    protected $fillable = ['company_id', 'model_key', 'type', 'source_module', 'forecast_date', 'predicted_value', 'lower_value', 'upper_value', 'confidence', 'features', 'status', 'reviewed_by', 'reviewed_at'];

    protected $casts = [
        'forecast_date' => 'date', 'predicted_value' => 'decimal:2', 'lower_value' => 'decimal:2', 'upper_value' => 'decimal:2',
        'confidence' => 'decimal:2', 'features' => 'array', 'reviewed_at' => 'datetime',
    ];

    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
