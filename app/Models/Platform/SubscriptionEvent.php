<?php

namespace App\Models\Platform;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** One row per subscription change, with its effect on MRR (the MRR ledger). */
class SubscriptionEvent extends Model
{
    protected $table = 'platform_subscription_events';

    protected $fillable = ['company_id', 'platform_subscription_id', 'type', 'movement', 'mrr_before', 'mrr_after', 'mrr_delta', 'details', 'user_id', 'occurred_at'];

    protected $casts = [
        'details' => 'array',
        'mrr_before' => 'decimal:2',
        'mrr_after' => 'decimal:2',
        'mrr_delta' => 'decimal:2',
        'occurred_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return match ($this->type) {
            'trial_started' => 'Trial started',
            'trial_extended' => 'Trial extended',
            'activated' => 'Became a paying customer',
            'plan_changed' => 'Plan changed',
            'promo_granted' => 'Promotional subscription granted',
            'suspended' => 'Account suspended',
            'reactivated' => 'Account reactivated',
            'cancelled' => 'Subscription cancelled',
            'trial_expired' => 'Trial ended (workspace locked)',
            'past_due' => 'Renewal unpaid (grace period started)',
            'renewed' => 'Renewed',
            'promo_ended' => 'Promotion ended',
            default => ucfirst(str_replace('_', ' ', $this->type)),
        };
    }
}
