<?php

namespace App\Services\Billing;

use App\Models\Platform\Subscription;

/**
 * Whether a business may use the app, decided from dates on every request (so access is
 * correct even if the scheduler has not run yet).
 *
 *  open    trial running, active, or past due inside the grace period
 *  locked  trial ended, suspended for non-payment, cancelled, or past due beyond grace
 */
class SubscriptionAccess
{
    /** @return array{state: string, reason: string, message: string, days_left: ?int} */
    public function check(?Subscription $sub): array
    {
        if (!$sub) {
            return $this->result('open', 'none', '');
        }

        $now = now();

        return match (true) {
            $sub->status === 'trialing' && $sub->trial_ends_at && $sub->trial_ends_at->gt($now) => $this->result('open', 'trial',
                'Your free trial ends '.$sub->trial_ends_at->format('d M Y').'.', (int) ceil($now->diffInHours($sub->trial_ends_at) / 24)),
            $sub->status === 'trialing', $sub->status === 'expired' => $this->result('locked', 'trial_ended',
                'Your 14-day free trial has ended. Choose a plan to keep using acc263. Your data is safe.'),
            $sub->status === 'active' => $this->result('open', 'active', ''),
            $sub->status === 'past_due' && (!$sub->grace_ends_at || $sub->grace_ends_at->gt($now)) => $this->result('open', 'grace',
                'Your subscription payment is overdue. Pay by '.($sub->grace_ends_at ?? $now)->format('d M Y').' to avoid suspension.',
                $sub->grace_ends_at ? (int) ceil($now->diffInHours($sub->grace_ends_at) / 24) : null),
            $sub->status === 'past_due', $sub->status === 'suspended' && $sub->suspension_type !== 'admin' => $this->result('locked', 'unpaid',
                'Your subscription is suspended because a payment was not received. Pay the open invoice to restore access.'),
            $sub->status === 'cancelled' => $this->result('locked', 'cancelled', 'Your subscription has been cancelled. Choose a plan to reactivate your workspace.'),
            default => $this->result('locked', 'suspended', 'Your workspace has been suspended. Contact acc263 support.'),
        };
    }

    private function result(string $state, string $reason, string $message, ?int $daysLeft = null): array
    {
        return ['state' => $state, 'reason' => $reason, 'message' => $message, 'days_left' => $daysLeft];
    }
}
