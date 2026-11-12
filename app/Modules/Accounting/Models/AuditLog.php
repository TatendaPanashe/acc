<?php

namespace App\Modules\Accounting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Append-only audit trail. Each entry stores a SHA-256 checksum chained to the previous entry of the same
 * company, so editing or deleting a row directly in the database is detectable (see AuditTrailService::verify).
 */
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

    protected static function booted(): void
    {
        static::creating(function (AuditLog $log) {
            $log->created_at = $log->created_at ?? now();
            $previous = DB::table('accounting_audit_logs')
                ->where('company_id', $log->company_id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->value('checksum');
            $log->checksum = self::checksumFor($log, (string) $previous);
        });

        static::updating(fn () => throw new LogicException('Audit log entries cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Audit log entries cannot be deleted.'));
    }

    public static function checksumFor(AuditLog $log, string $previous): string
    {
        return hash('sha256', $previous.'|'.json_encode([
            $log->company_id, $log->user_id, $log->auditable_type, $log->auditable_id, $log->event,
            $log->old_values, $log->new_values, $log->created_at?->format('Y-m-d H:i:s'),
        ]));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subjectLabel(): string
    {
        return $this->auditable_type ? class_basename($this->auditable_type).' #'.$this->auditable_id : '—';
    }
}
