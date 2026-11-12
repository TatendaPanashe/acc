<?php

namespace App\Modules\Accounting\Models;

class ApprovalRequest extends AccountingModel
{
    protected $table = 'accounting_approval_requests';

    protected $fillable = [
        'company_id', 'approvable_type', 'approvable_id', 'workflow_key', 'status',
        'requested_by', 'resolved_by', 'resolved_at', 'resolution_note',
    ];

    protected $casts = ['resolved_at' => 'datetime'];
}
