<?php

namespace App\Modules\Accounting\Models;

class Supplier extends AccountingModel
{
    protected $table = 'accounting_suppliers';

    protected $fillable = [
        'company_id', 'supplier_number', 'name', 'email', 'phone',
        'remittance_address', 'currency', 'status',
    ];

    protected $casts = ['status' => 'boolean'];
}
