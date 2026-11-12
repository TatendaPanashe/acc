<?php

namespace App\Modules\Accounting\Models;

class TaxCode extends AccountingModel
{
    protected $table = 'accounting_tax_codes';

    protected $fillable = ['company_id', 'code', 'name', 'type', 'rate', 'control_account_id', 'status'];

    protected $casts = ['rate' => 'decimal:4', 'status' => 'boolean'];
}
