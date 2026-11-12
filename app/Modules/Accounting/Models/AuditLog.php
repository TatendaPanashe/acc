<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'accounting_audit_logs';

    protected $fillable = [
        'company_id', 'user_id', 'auditable_type', 'auditable_id', 'event',
        'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];
}
