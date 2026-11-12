<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'name',
        'logo_path',
        'registration_number',
        'vat_number',
        'tin',
        'nssa_number',
        'email',
        'phone',
        'address',
        'country',
        'slug',
        'status',
        'onboarding_completed_at',
    ];

    protected $casts = [
        'status' => 'boolean',
        'onboarding_completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $company) {
            if (!$company->slug) {
                $base = \Illuminate\Support\Str::slug($company->name) ?: 'company';
                $slug = $base;
                for ($i = 2; static::where('slug', $slug)->exists(); $i++) {
                    $slug = $base.'-'.$i;
                }
                $company->slug = $slug;
            }
        });

        // Every business gets a platform subscription, starting with a trial of the default plan.
        static::created(function (self $company) {
            if (\App\Models\Platform\Plan::query()->exists()) {
                app(\App\Services\Platform\SubscriptionService::class)->startTrial($company);
            }
        });
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function bankAccounts()
    {
        return $this->hasMany(CompanyBankAccount::class);
    }

    public function subscription()
    {
        return $this->hasOne(\App\Models\Platform\Subscription::class);
    }

    public function platformInvoices()
    {
        return $this->hasMany(\App\Models\Platform\PlatformInvoice::class);
    }
}
