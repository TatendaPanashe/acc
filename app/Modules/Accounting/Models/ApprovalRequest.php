<?php

namespace App\Modules\Accounting\Models;

use App\Models\User;

/** Approval decision log: one row per approval or rejection of a document. */
class ApprovalRequest extends AccountingModel
{
    protected $table = 'accounting_approval_requests';

    protected $fillable = [
        'company_id', 'approvable_type', 'approvable_id', 'workflow_key', 'amount', 'reference', 'status',
        'requested_by', 'resolved_by', 'resolved_at', 'resolution_note',
    ];

    protected $casts = ['resolved_at' => 'datetime', 'amount' => 'decimal:2'];

    public function approvable()
    {
        return $this->morphTo();
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
