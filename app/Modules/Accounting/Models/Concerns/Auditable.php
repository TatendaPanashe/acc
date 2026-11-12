<?php

namespace App\Modules\Accounting\Models\Concerns;

use App\Modules\Accounting\Services\AuditTrailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Records create / update / delete of a model in the audit trail automatically.
 * A model opts out with `protected static bool $audited = false;` (line items and ledgers that are
 * themselves append-only, such as stock movements).
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => self::writeAudit($model, 'created', [], self::auditableValues($model, $model->getAttributes())));

        static::updated(function (Model $model) {
            $changes = self::auditableValues($model, $model->getChanges());
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key(self::auditableValues($model, $model->getRawOriginal()), $changes);
            self::writeAudit($model, 'updated', $old, $changes);
        });

        static::deleted(fn (Model $model) => self::writeAudit($model, 'deleted', self::auditableValues($model, $model->getRawOriginal()), []));
    }

    public static function auditEvent(Model $model, string $action): string
    {
        return Str::snake(class_basename($model)).'.'.$action;
    }

    private static function writeAudit(Model $model, string $action, array $old, array $new): void
    {
        if (!$model::$audited || !$model->company_id) {
            return;
        }

        app(AuditTrailService::class)->record(self::auditEvent($model, $action), $model, $old, $new, app()->runningInConsole() ? null : request());
    }

    private static function auditableValues(Model $model, array $values): array
    {
        $skip = array_merge($model->getHidden(), ['created_at', 'updated_at', 'password', 'remember_token', 'token']);

        return collect($values)
            ->except($skip)
            ->reject(fn ($value, $key) => Str::contains($key, ['password', 'secret', 'token']))
            ->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : (is_scalar($value) || $value === null ? $value : json_encode($value)))
            ->all();
    }
}
