<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class TenantModel extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            $user = auth()->user();
            $portalCompany = self::portalCompanyId();

            if ($user && !$user->is_superuser) {
                if ($user->company_id) {
                    $builder->where($builder->qualifyColumn('company_id'), $user->company_id);
                } else {
                    $builder->whereRaw('1 = 0');
                }
            } elseif (!$user && $portalCompany) {
                // Self-service portals (e.g. the employee portal) set the company after verifying the person.
                $builder->where($builder->qualifyColumn('company_id'), $portalCompany);
            } elseif (!$user && !app()->runningInConsole()) {
                $builder->whereRaw('1 = 0');
            }
        });

        static::creating(function (Model $model) {
            $user = auth()->user();

            if ($user && !$user->is_superuser) {
                $model->company_id = $user->company_id;
            } elseif ($user && !$model->company_id) {
                $model->company_id = $user->company_id;
            } elseif (!$user && self::portalCompanyId()) {
                $model->company_id = self::portalCompanyId();
            }
        });
    }

    /** Company set by a self-service portal for this request (never set when a staff user is signed in). */
    public static function portalCompanyId(): ?int
    {
        return app()->bound('tenant.portal_company_id') ? (int) app('tenant.portal_company_id') : null;
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
