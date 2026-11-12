<?php

namespace App\Models\Platform;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    public const STATUSES = [
        'trialing' => 'Trial',
        'expired' => 'Trial ended',
        'active' => 'Active',
        'past_due' => 'Past due',
        'suspended' => 'Suspended',
        'cancelled' => 'Cancelled',
    ];

    /** Statuses that count towards MRR. */
    public const PAYING = ['active', 'past_due'];

    protected $table = 'platform_subscriptions';

    protected $fillable = [
        'company_id', 'platform_plan_id', 'status', 'billing_cycle', 'price', 'currency', 'payment_provider',
        'is_promotional', 'promo_ends_at', 'promo_note', 'trial_ends_at', 'current_period_ends_at', 'activated_at',
        'cancelled_at', 'cancellation_reason', 'suspended_at', 'suspension_reason', 'status_before_suspension',
        'starts_at', 'grace_ends_at', 'suspension_type', 'coupon_id', 'limit_overrides',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_promotional' => 'boolean',
        'promo_ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'current_period_ends_at' => 'datetime',
        'activated_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'suspended_at' => 'datetime',
        'starts_at' => 'datetime',
        'grace_ends_at' => 'datetime',
        'limit_overrides' => 'array',
    ];

    public function items()
    {
        return $this->hasMany(SubscriptionItem::class, 'platform_subscription_id');
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function invoices()
    {
        return $this->hasMany(PlatformInvoice::class, 'platform_subscription_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'platform_plan_id');
    }

    public function events()
    {
        return $this->hasMany(SubscriptionEvent::class, 'platform_subscription_id')->latest('occurred_at')->latest('id');
    }

    /** Monthly recurring revenue this subscription contributes right now. */
    public function mrr(): float
    {
        if (!in_array($this->status, self::PAYING, true) || $this->is_promotional) {
            return 0.0;
        }

        return round($this->billing_cycle === 'annual' ? (float) $this->price / 12 : (float) $this->price, 2);
    }

    public function trialExpired(): bool
    {
        return $this->status === 'trialing' && $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    public function statusLabel(): string
    {
        if ($this->trialExpired()) {
            return 'Trial expired';
        }

        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
