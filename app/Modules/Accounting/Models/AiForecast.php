<?php

namespace App\Modules\Accounting\Models;

class AiForecast extends AccountingModel
{
    protected $table = 'accounting_ai_forecasts';

    protected $fillable = ['company_id', 'model_key', 'type', 'source_module', 'forecast_date', 'predicted_value', 'confidence', 'features', 'status'];

    protected $casts = ['forecast_date' => 'date', 'predicted_value' => 'decimal:2', 'confidence' => 'decimal:2', 'features' => 'array'];
}
