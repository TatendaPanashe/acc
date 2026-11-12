<?php

namespace App\Modules\Accounting\Models;

class EnterpriseRecord extends AccountingModel
{
    protected $table = 'accounting_enterprise_records';

    protected $fillable = [
        'company_id',
        'module_key',
        'code',
        'title',
        'owner',
        'record_date',
        'amount',
        'currency',
        'status',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'record_date' => 'date',
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];
}
