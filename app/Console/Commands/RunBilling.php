<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Platform\PlatformInvoice;
use App\Models\Platform\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionPaymentReminder;
use App\Services\Billing\BillingService;
use App\Services\Billing\Entitlements;
use App\Services\Platform\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Subscription lifecycle housekeeping (scheduled hourly):
 * trial expiry → renewal invoices & reminders → past due → suspension after the grace period.
 * Access is also checked on every request, so a late run never grants extra access.
 */
class RunBilling extends Command
{
    protected $signature = 'billing:run';

    protected $description = 'Expire trials, issue renewal invoices and reminders, and suspend unpaid subscriptions';

    public function handle(SubscriptionService $subscriptions, BillingService $billing, Entitlements $entitlements): int
    {
        $counts = array_fill_keys(['trials_expired', 'promos_ended', 'renewals_issued', 'reminders', 'past_due', 'suspended'], 0);

        Subscription::where('status', 'trialing')->where('trial_ends_at', '<=', now())->each(function ($sub) use ($subscriptions, &$counts) {
            $subscriptions->expireTrial($sub);
            $counts['trials_expired']++;
        });

        Subscription::whereIn('status', Subscription::PAYING)->where('is_promotional', true)->where('promo_ends_at', '<=', now())->each(function ($sub) use ($subscriptions, &$counts) {
            $subscriptions->endPromotion($sub);
            $counts['promos_ended']++;
        });

        // Renewal invoices, issued ahead of the period end.
        Subscription::with(['plan', 'company'])->whereIn('status', Subscription::PAYING)->where('is_promotional', false)
            ->where('current_period_ends_at', '<=', now()->addDays(config('billing.renewal_lead_days')))
            ->each(function (Subscription $sub) use ($billing, &$counts) {
                $invoice = $billing->renewalInvoice($sub);
                if ($invoice && $invoice->wasRecentlyCreated) {
                    $counts['renewals_issued']++;
                }
                if ($invoice && $invoice->isOpen() && !$invoice->reminder_sent_at) {
                    $this->remind($invoice, 'upcoming');
                    $counts['reminders']++;
                }
            });

        // Period ended without payment: past due, with a grace period.
        Subscription::where('status', 'active')->where('is_promotional', false)->where('current_period_ends_at', '<', now())
            ->each(function (Subscription $sub) use ($subscriptions, &$counts) {
                $subscriptions->markPastDue($sub);
                $open = $sub->invoices()->where('status', 'pending')->latest('due_at')->first();
                if ($open) {
                    $this->remind($open, 'overdue');
                }
                $counts['past_due']++;
            });

        // Grace period over: suspend for non-payment (users can still sign in to pay).
        Subscription::where('status', 'past_due')->where('grace_ends_at', '<', now())->each(function ($sub) use ($subscriptions, &$counts) {
            $subscriptions->suspend($sub, 'Payment not received within the grace period', 'billing');
            $counts['suspended']++;
        });

        Company::where('status', true)->each(fn (Company $company) => $entitlements->recordUsage($company));

        foreach ($counts as $name => $count) {
            $this->line(str_pad(str_replace('_', ' ', $name), 18).$count);
        }

        return self::SUCCESS;
    }

    private function remind(PlatformInvoice $invoice, string $kind): void
    {
        $admins = User::where('company_id', $invoice->company_id)->where('role', 'admin')->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new SubscriptionPaymentReminder($invoice, $kind));
        }
        $invoice->update(['reminder_sent_at' => now()]);
    }
}
