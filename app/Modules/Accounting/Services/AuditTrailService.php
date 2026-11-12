<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\AuditLog;
use App\Modules\Accounting\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditTrailService
{
    public function record(string $event, ?Model $model = null, array $oldValues = [], array $newValues = [], ?Request $request = null, ?int $companyId = null): AuditLog
    {
        $user = auth()->user();
        $request ??= app()->runningInConsole() ? null : request();

        return AuditLog::create([
            'company_id' => $companyId ?? $model?->company_id ?? $user?->company_id,
            'user_id' => $user?->id,
            'auditable_type' => $model ? $model::class : null,
            'auditable_id' => $model?->getKey(),
            'event' => $event,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? substr($request->userAgent(), 0, 500) : null,
        ]);
    }

    /** User who created the record, from the audit trail (falls back to a created_by column). */
    public function creatorOf(Model $model): ?int
    {
        if (isset($model->created_by)) {
            return (int) $model->created_by;
        }

        $userId = AuditLog::where('company_id', $model->company_id)
            ->where('auditable_type', $model::class)
            ->where('auditable_id', $model->getKey())
            ->where('event', Auditable::auditEvent($model, 'created'))
            ->value('user_id');

        return $userId ? (int) $userId : null;
    }

    /**
     * Recomputes the checksum chain for a company.
     * @return array{checked: int, valid: bool, broken_at: ?int}
     */
    public function verify(int $companyId): array
    {
        $previous = '';
        $checked = 0;

        foreach (AuditLog::where('company_id', $companyId)->orderBy('id')->lazy(500) as $log) {
            if ($log->checksum === null) { // written before checksums existed
                $previous = '';
                continue;
            }
            $checked++;
            if (!hash_equals(AuditLog::checksumFor($log, $previous), $log->checksum)) {
                return ['checked' => $checked, 'valid' => false, 'broken_at' => $log->id];
            }
            $previous = $log->checksum;
        }

        return ['checked' => $checked, 'valid' => true, 'broken_at' => null];
    }
}
