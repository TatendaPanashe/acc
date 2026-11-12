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

            if ($user && !$user->is_superuser) {
                if ($user->company_id) {
                    $builder->where($builder->qualifyColumn('company_id'), $user->company_id);
                } else {
                    $builder->whereRaw('1 = 0');
                }
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
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
