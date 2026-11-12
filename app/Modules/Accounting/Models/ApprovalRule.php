<?php

namespace App\Modules\Accounting\Models;

class ApprovalRule extends AccountingModel
{
    protected $table = 'accounting_approval_rules';

    protected $fillable = ['company_id', 'document_type', 'min_amount', 'approver_role', 'require_different_user', 'status'];

    protected $casts = ['min_amount' => 'decimal:2', 'require_different_user' => 'boolean', 'status' => 'boolean'];

    public const ROLES = ['finance_manager' => 'Finance Manager or Company Admin', 'admin' => 'Company Admin only'];
}
