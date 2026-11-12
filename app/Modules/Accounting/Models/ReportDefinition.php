<?php

namespace App\Modules\Accounting\Models;

class ReportDefinition extends AccountingModel
{
    protected $table = 'accounting_report_definitions';

    protected $fillable = ['company_id', 'code', 'name', 'type', 'format', 'filters', 'status'];

    protected $casts = ['filters' => 'array', 'status' => 'boolean'];
}
