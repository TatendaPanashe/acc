<?php

namespace App\Modules\Accounting\Models;

class Warehouse extends AccountingModel
{
    protected $table = 'accounting_warehouses';

    protected $fillable = ['company_id', 'code', 'name', 'location', 'status'];

    protected $casts = ['status' => 'boolean'];
}
