<?php

namespace App\Modules\Accounting\Models;

use App\Models\User;

class ReportDefinition extends AccountingModel
{
    protected $table = 'accounting_report_definitions';

    protected $fillable = ['company_id', 'code', 'name', 'description', 'type', 'format', 'filters', 'status', 'created_by', 'last_run_at'];

    protected $casts = ['filters' => 'array', 'status' => 'boolean', 'last_run_at' => 'datetime'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
