<?php

namespace App\Modules\Accounting\Models;

class JobCost extends AccountingModel
{
    protected $table = 'accounting_job_costs';

    protected $fillable = [
        'company_id', 'job_number', 'name', 'type', 'estimated_cost',
        'actual_cost', 'due_on', 'status', 'notes',
    ];

    protected $casts = ['estimated_cost' => 'decimal:2', 'actual_cost' => 'decimal:2', 'due_on' => 'date'];
}
