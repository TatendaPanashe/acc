<?php

namespace App\Services\Platform;

use App\Models\Company;
use App\Models\Platform\Plan;
use App\Models\Platform\Subscription;
use App\Models\Platform\SubscriptionEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every change to a tenant's platform subscription goes through here, so each one is
 * recorded as an event with its effect on MRR. The dashboard's MRR history and
 * new / expansion / churn figures are read from those events.
 */
class SubscriptionService
{
    public function startTrial(Company $company, ?Plan $plan = null): Subscription
    {
        $plan ??= Plan::where('code', config('platform.default_plan'))->first() ?? Plan::where('is_active', true)->orderBy('sort_order')->firstOrFail();

        return DB::transaction(function () use ($company, $plan) {
            $subscription = Subscription::create([
                'company_id' => $company->id,
                'platform_plan_id' => $plan->id,
                'status' => 'trialing',
                'billing_cycle' => 'monthly',
                'price' => $plan->monthly_price,
                'currency' => $plan->currency,
                'trial_ends_at' => now()->addDays($plan->trial_days ?: config('billing.trial_days')),
            ]);
            $this->syncItems($subscription);

            $this->record($subscription, 'trial_started', 0, ['plan' => $plan->code, 'trial_days' => $plan->trial_days]);

            return $subscription;
        });
    }

    /** Trial (or cancelled/lapsed) account starts paying. Used by payment integrations. */
    public function activate(Subscription $subscription, ?Plan $plan = null, string $cycle = 'monthly', ?string $provider = null, ?float $price = null, ?int $couponId = null): Subscription
    {
        return $this->mutate($subscription, function (Subscription $sub) use ($plan, $cycle, $provider, $price, $couponId) {
            $plan ??= $sub->plan;
            $wasCustomer = $sub->activated_at !== null;
            $sub->fill([
                'platform_plan_id' => $plan->id,
                'status' => 'active',
                'billing_cycle' => $cycle,
                'price' => $price ?? $plan->priceFor($cycle),
                'coupon_id' => $couponId,
                'starts_at' => now(),
                'grace_ends_at' => null,
                'suspended_at' => null,
                'suspension_reason' => null,
                'suspension_type' => null,
                'status_before_suspension' => null,
                'currency' => $plan->currency,
                'payment_provider' => $provider ?? $sub->payment_provider,
                'is_promotional' => false,
                'promo_ends_at' => null,
                'trial_ends_at' => null,
                'cancelled_at' => null,
                'cancellation_reason' => null,
                'activated_at' => $sub->activated_at ?? now(),
                'current_period_ends_at' => $cycle === 'annual' ? now()->addYear() : now()->addMonth(),
            ]);
            $this->syncItems($sub);

            return ['activated', $wasCustomer ? 'reactivation' : 'new', ['plan' => $plan->code, 'cycle' => $cycle, 'provider' => $provider]];
        });
    }

    public function changePlan(Subscription $subscription, Plan $plan, string $cycle, ?string $note = null, ?float $customPrice = null, ?array $limitOverrides = null): Subscription
    {
        if (in_array($subscription->status, ['suspended', 'cancelled'], true)) {
            throw ValidationException::withMessages(['plan' => 'Reactivate the account before changing its plan.']);
        }

        return $this->mutate($subscription, function (Subscription $sub) use ($plan, $cycle, $note, $customPrice, $limitOverrides) {
            $from = ['plan' => $sub->plan->code, 'cycle' => $sub->billing_cycle, 'price' => (float) $sub->price];
            $sub->fill([
                'platform_plan_id' => $plan->id,
                'billing_cycle' => $cycle,
                'price' => $sub->is_promotional ? 0 : ($customPrice ?? $plan->priceFor($cycle)),
                'currency' => $plan->currency,
                'limit_overrides' => $limitOverrides,
            ]);
            $this->syncItems($sub);

            return ['plan_changed', null, ['from' => $from, 'to' => ['plan' => $plan->code, 'cycle' => $cycle, 'price' => (float) $sub->price], 'note' => $note]];
        });
    }

    /** Free access on a plan for a number of months (price 0, so it adds no MRR). */
    public function grantPromotion(Subscription $subscription, Plan $plan, int $months, string $note): Subscription
    {
        return $this->mutate($subscription, function (Subscription $sub) use ($plan, $months, $note) {
            $ends = now()->addMonths($months);
            $sub->fill([
                'platform_plan_id' => $plan->id,
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'price' => 0,
                'is_promotional' => true,
                'promo_ends_at' => $ends,
                'promo_note' => $note,
                'trial_ends_at' => null,
                'cancelled_at' => null,
                'current_period_ends_at' => $ends,
            ]);
            $this->reopenCompany($sub);

            return ['promo_granted', null, ['plan' => $plan->code, 'months' => $months, 'ends' => $ends->toDateString(), 'note' => $note]];
        });
    }

    public function extendTrial(Subscription $subscription, int $days, ?string $note = null): Subscription
    {
        if (!in_array($subscription->status, ['trialing', 'expired'], true)) {
            throw ValidationException::withMessages(['days' => 'Only accounts on a trial can have their trial extended.']);
        }

        return $this->mutate($subscription, function (Subscription $sub) use ($days, $note) {
            $from = $sub->trial_ends_at;
            $base = $from && $from->isFuture() ? $from->copy() : now();
            $sub->trial_ends_at = $base->addDays($days);
            $sub->status = 'trialing';

            return ['trial_extended', null, ['from' => $from?->toDateString(), 'to' => $sub->trial_ends_at->toDateString(), 'days' => $days, 'note' => $note]];
        });
    }

    /**
     * Suspends and removes MRR until reactivated.
     *  admin   (platform team) users cannot sign in at all
     *  billing (non-payment)   users can sign in but only reach Billing to pay
     */
    public function suspend(Subscription $subscription, string $reason, string $type = 'admin'): Subscription
    {
        if ($subscription->status === 'suspended') {
            return $subscription;
        }

        return $this->mutate($subscription, function (Subscription $sub) use ($reason, $type) {
            $sub->fill([
                'status_before_suspension' => $sub->status,
                'status' => 'suspended',
                'suspended_at' => now(),
                'suspension_reason' => $reason,
                'suspension_type' => $type,
            ]);
            if ($type === 'admin') {
                $sub->company->forceFill(['status' => false])->save();
            }

            return ['suspended', 'churn', ['reason' => $reason, 'type' => $type]];
        });
    }

    public function reactivate(Subscription $subscription, ?string $note = null): Subscription
    {
        if ($subscription->status !== 'suspended') {
            throw ValidationException::withMessages(['status' => 'Only suspended accounts can be reactivated.']);
        }

        return $this->mutate($subscription, function (Subscription $sub) use ($note) {
            $sub->fill([
                'status' => $sub->status_before_suspension ?: 'active',
                'status_before_suspension' => null,
                'suspended_at' => null,
                'suspension_reason' => null,
                'suspension_type' => null,
            ]);
            $this->reopenCompany($sub);

            return ['reactivated', 'reactivation', ['note' => $note]];
        });
    }

    public function cancel(Subscription $subscription, string $reason): Subscription
    {
        if ($subscription->status === 'cancelled') {
            return $subscription;
        }

        return $this->mutate($subscription, function (Subscription $sub) use ($reason) {
            $sub->fill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => $reason, 'is_promotional' => false]);

            return ['cancelled', 'churn', ['reason' => $reason]];
        });
    }

    /** Renewal not paid by the period end: past due, with a grace period before suspension. Still counts as MRR. */
    public function markPastDue(Subscription $subscription): Subscription
    {
        if ($subscription->status !== 'active') {
            return $subscription;
        }

        return $this->mutate($subscription, function (Subscription $sub) {
            $sub->fill(['status' => 'past_due', 'grace_ends_at' => now()->addDays(config('billing.grace_days'))]);

            return ['past_due', null, ['grace_ends' => $sub->grace_ends_at->toDateString()]];
        });
    }

    public function expireTrial(Subscription $subscription): Subscription
    {
        if ($subscription->status !== 'trialing' || !$subscription->trial_ends_at?->isPast()) {
            return $subscription;
        }

        return $this->mutate($subscription, function (Subscription $sub) {
            $sub->status = 'expired';

            return ['trial_expired', null, ['ended' => $sub->trial_ends_at->toDateString()]];
        });
    }

    /** Promotional period over: the account must choose a paid plan, like an ended trial. */
    public function endPromotion(Subscription $subscription): Subscription
    {
        return $this->mutate($subscription, function (Subscription $sub) {
            $sub->fill(['status' => 'expired', 'is_promotional' => false, 'trial_ends_at' => $sub->promo_ends_at ?? now()]);

            return ['promo_ended', null, []];
        });
    }

    /** A renewal invoice was paid: start the next period and clear any arrears. */
    public function renew(Subscription $subscription, ?string $provider = null): Subscription
    {
        return $this->mutate($subscription, function (Subscription $sub) use ($provider) {
            $wasLocked = in_array($sub->status, ['suspended', 'past_due'], true);
            $from = $sub->current_period_ends_at && $sub->current_period_ends_at->isFuture() ? $sub->current_period_ends_at->copy() : now();
            $sub->fill([
                'status' => 'active',
                'starts_at' => $from,
                'current_period_ends_at' => $sub->billing_cycle === 'annual' ? $from->copy()->addYear() : $from->copy()->addMonth(),
                'grace_ends_at' => null,
                'suspended_at' => null,
                'suspension_reason' => null,
                'suspension_type' => null,
                'status_before_suspension' => null,
                'payment_provider' => $provider ?? $sub->payment_provider,
            ]);
            $this->reopenCompany($sub);

            return ['renewed', $wasLocked ? 'reactivation' : null, ['until' => $sub->current_period_ends_at->toDateString(), 'provider' => $provider]];
        });
    }

    /** Line items billed each period (the plan at the subscription's price). */
    public function syncItems(Subscription $sub): void
    {
        $sub->items()->delete();
        $sub->items()->create([
            'item_type' => 'plan',
            'description' => $sub->plan()->value('name').' plan ('.$sub->billing_cycle.')',
            'quantity' => 1,
            'unit_price' => $sub->is_promotional ? 0 : $sub->price,
        ]);
    }

    // ------------------------------------------------------------------

    /**
     * Locks the row, applies the change, saves and records the event.
     * The callback returns [type, movement|null, details]; movement is inferred from the MRR change when null.
     */
    private function mutate(Subscription $subscription, callable $change): Subscription
    {
        return DB::transaction(function () use ($subscription, $change) {
            $sub = Subscription::with(['plan', 'company'])->lockForUpdate()->findOrFail($subscription->id);
            $before = $sub->mrr();

            [$type, $movement, $details] = $change($sub);
            $sub->save();
            $sub->load('plan');

            $this->record($sub, $type, $before, array_filter($details, fn ($v) => $v !== null), $movement);

            return $sub;
        });
    }

    private function record(Subscription $sub, string $type, float $before, array $details, ?string $movement = null): SubscriptionEvent
    {
        $after = $sub->mrr();
        $delta = round($after - $before, 2);

        $movement ??= match (true) {
            $delta > 0 => 'expansion',
            $delta < 0 => 'contraction',
            default => null,
        };

        return SubscriptionEvent::create([
            'company_id' => $sub->company_id,
            'platform_subscription_id' => $sub->id,
            'type' => $type,
            'movement' => $delta == 0.0 && in_array($movement, ['expansion', 'contraction', 'churn', 'new', 'reactivation'], true) ? null : $movement,
            'mrr_before' => $before,
            'mrr_after' => $after,
            'mrr_delta' => $delta,
            'details' => $details ?: null,
            'user_id' => auth()->id(),
            'occurred_at' => now(),
        ]);
    }

    private function reopenCompany(Subscription $sub): void
    {
        if (!$sub->company->status) {
            $sub->company->forceFill(['status' => true])->save();
        }
    }
}
