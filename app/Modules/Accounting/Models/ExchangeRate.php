<?php

namespace App\Modules\Accounting\Models;

/** 1 unit of from_currency = rate units of to_currency, effective from rate_date. */
class ExchangeRate extends AccountingModel
{
    protected $table = 'accounting_exchange_rates';

    protected $fillable = ['company_id', 'from_currency', 'to_currency', 'rate_date', 'rate', 'source'];

    protected $casts = ['rate_date' => 'date', 'rate' => 'decimal:8'];
}
