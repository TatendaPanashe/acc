<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditTrailService
{
    public function record(string $event, ?Model $model = null, array $oldValues = [], array $newValues = [], ?Request $request = null): AuditLog
    {
        $user = auth()->user();

        return AuditLog::create([
            'company_id' => $model?->company_id ?? $user?->company_id,
            'user_id' => $user?->id,
            'auditable_type' => $model ? $model::class : null,
            'auditable_id' => $model?->getKey(),
            'event' => $event,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
