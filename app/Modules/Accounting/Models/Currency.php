<?php

namespace App\Modules\Accounting\Models;

class Currency extends AccountingModel
{
    protected $table = 'accounting_currencies';

    protected $fillable = ['company_id', 'code', 'name', 'symbol', 'is_base', 'status'];

    protected $casts = ['is_base' => 'boolean', 'status' => 'boolean'];
}
