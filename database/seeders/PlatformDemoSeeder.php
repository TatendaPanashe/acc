<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Platform\Plan;
use App\Models\Platform\Payment;
use App\Models\Platform\PlatformInvoice;
use App\Models\Platform\Subscription;
use App\Services\Platform\SubscriptionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Sample tenants for the platform dashboard: twelve months of registrations, trials,
 * conversions, upgrades, renewals, failed payments, cancellations, suspensions and promotions.
 *
 * Every change goes through SubscriptionService with the clock moved to the date it happens,
 * so the MRR history is consistent with the subscriptions.
 *
 *   php artisan db:seed --class=PlatformDemoSeeder
 *
 * Sample companies use example.com addresses and have no users.
 */
class PlatformDemoSeeder extends Seeder
{
    private const FIRST = ['Kopje', 'Msasa', 'Baobab', 'Granite', 'Mopane', 'Riverbend', 'Highveld', 'Sunrise', 'Ironwood', 'Lakeside', 'Bluegum', 'Savanna', 'Summit', 'Copperline', 'Amber', 'Jacaranda', 'Northgate', 'Clearwater', 'Redstone', 'Silverleaf'];
    private const SECOND = ['Traders', 'Logistics', 'Pharmacy', 'Hardware', 'Foods', 'Consulting', 'Motors', 'Agro', 'Clinic', 'Studios', 'Builders', 'Textiles', 'Electrical', 'Supplies', 'Bakery', 'Printers', 'Dairy', 'Solar', 'Security', 'Travel'];
    private const COUNTRIES = ['ZW' => 40, 'ZA' => 18, 'BW' => 9, 'ZM' => 9, 'MZ' => 5, 'KE' => 5, 'NG' => 4, 'NA' => 4, 'GB' => 3, 'US' => 3];
    private const PLANS = ['essential' => 45, 'professional' => 30, 'business' => 18, 'enterprise' => 7];

    private int $invoiceNumber = 1000;

    public function run(SubscriptionService $subscriptions): void
    {
        mt_srand(263);
        $plans = Plan::orderBy('sort_order')->get()->keyBy('code');
        $realNow = Carbon::now();
        $start = $realNow->copy()->subMonths(12)->startOfMonth();
        $names = $this->names(120);

        foreach ($names as $i => $name) {
            // Sign-ups accelerate over the year.
            $registered = $start->copy()->addSeconds((int) (sqrt(mt_rand(0, 1000) / 1000) * $start->diffInSeconds($realNow)));
            $country = $this->pick(self::COUNTRIES);

            Carbon::setTestNow($registered);
            $company = Company::create([
                'name' => $name,
                'email' => 'accounts@'.str($name)->slug().'.example.com',
                'country' => $country,
                'status' => true,
                'onboarding_completed_at' => $registered,
            ]);
            $sub = $company->subscription;

            $this->simulate($subscriptions, $sub, $plans, $country, $realNow, $i);

            // A few paying tenants are suspended for review.
            if ($i % 29 === 11 && in_array($sub->fresh()->status, Subscription::PAYING, true)) {
                Carbon::setTestNow($realNow->copy()->subDays(mt_rand(1, 20)));
                $subscriptions->suspend($sub->fresh(), 'Account under compliance review');
            }
        }

        Carbon::setTestNow();
    }

    private function simulate(SubscriptionService $service, Subscription $sub, $plans, string $country, Carbon $realNow, int $index): void
    {
        $trialEnd = $sub->trial_ends_at->copy();

        // Some trials get extended by the platform team.
        if (mt_rand(1, 100) <= 8 && $trialEnd->copy()->subDays(2)->lt($realNow)) {
            Carbon::setTestNow($trialEnd->copy()->subDays(2));
            $sub = $service->extendTrial($sub, 14, 'Requested more time to evaluate');
            $trialEnd = $sub->trial_ends_at->copy();
        }

        if ($trialEnd->gt($realNow)) {
            return; // still on trial
        }

        // A handful of businesses get a promotional subscription instead of paying.
        if ($index % 23 === 5) {
            Carbon::setTestNow($trialEnd);
            $service->grantPromotion($sub, $plans['professional'], 6, 'Launch partner programme');

            return;
        }

        if (mt_rand(1, 100) > 62) {
            return; // trial expired without converting
        }

        $cycle = mt_rand(1, 100) <= 20 ? 'annual' : 'monthly';
        $provider = $this->provider($country);
        $planCode = $this->pick(self::PLANS);

        Carbon::setTestNow($trialEnd->copy()->addHours(mt_rand(1, 48)));
        $sub = $service->activate($sub, $plans[$planCode], $cycle, $provider);

        $periodStart = Carbon::now()->copy();
        $order = array_keys(self::PLANS);
        $first = true;

        while ($periodStart->lt($realNow)) {
            $periodEnd = $cycle === 'annual' ? $periodStart->copy()->addYear() : $periodStart->copy()->addMonth();
            Carbon::setTestNow($periodStart);
            $sub->refresh();

            if (!in_array($sub->status, Subscription::PAYING, true)) {
                return;
            }

            // Bill the period; about 7% of payment attempts fail.
            $invoice = $this->invoice($sub, $periodStart, $periodEnd, $first ? 'activation' : 'renewal');
            if (mt_rand(1, 100) <= 7) {
                $this->payment($invoice, 'failed', $provider);
                $retry = $periodStart->copy()->addDays(3);
                if ($retry->gt($realNow)) {
                    return; // still awaiting payment
                }
                Carbon::setTestNow($retry);
                if (mt_rand(1, 100) <= 70) {
                    $this->payment($invoice, 'succeeded', $provider);
                } else {
                    if (!$first) {
                        $service->markPastDue($sub);
                    }
                    Carbon::setTestNow($periodStart->copy()->addDays(config('billing.grace_days') + 1));
                    if (Carbon::now()->gt($realNow)) {
                        return;
                    }
                    mt_rand(1, 2) === 1
                        ? $service->suspend($sub->fresh(), 'Payment not received within the grace period', 'billing')
                        : $service->cancel($sub->fresh(), 'Payment failed and not resolved');

                    return;
                }
            } else {
                $this->payment($invoice, 'succeeded', $provider);
            }

            if (!$first) {
                $sub = $service->renew($sub->fresh(), $provider);
            }
            $first = false;

            // During the period: upgrade, downgrade or cancel.
            $during = $periodStart->copy()->addDays(mt_rand(5, 25));
            if ($during->lt($realNow) && $during->lt($periodEnd)) {
                Carbon::setTestNow($during);
                $roll = mt_rand(1, 1000);
                $position = array_search($sub->plan->code, $order, true);

                if ($roll <= 70 && $position < count($order) - 2) {
                    $sub = $service->changePlan($sub, $plans[$order[$position + 1]], $cycle, 'Needed more users');
                } elseif ($roll <= 85 && $position > 0) {
                    $sub = $service->changePlan($sub, $plans[$order[$position - 1]], $cycle, 'Reducing costs');
                } elseif ($roll <= 115) {
                    $service->cancel($sub, $this->pick([
                        'Switched to another accounting system' => 3, 'Business closed' => 2,
                        'Too expensive' => 3, 'Only needed it for a project' => 1,
                    ]));

                    return;
                }
            }

            $periodStart = $periodEnd;
        }
    }

    private function invoice(Subscription $sub, Carbon $from, Carbon $to, string $purpose): PlatformInvoice
    {
        return PlatformInvoice::create([
            'company_id' => $sub->company_id,
            'platform_subscription_id' => $sub->id,
            'number' => 'ACC-'.(++$this->invoiceNumber),
            'purpose' => $purpose,
            'platform_plan_id' => $sub->platform_plan_id,
            'billing_cycle' => $sub->billing_cycle,
            'subtotal' => $sub->price,
            'amount' => $sub->price,
            'currency' => $sub->currency,
            'status' => 'pending',
            'lines' => [['description' => $sub->plan->name.' plan, '.$sub->billing_cycle, 'amount' => (float) $sub->price]],
            'period_start' => $from->toDateString(),
            'period_end' => $to->copy()->subDay()->toDateString(),
            'issued_at' => Carbon::now(),
            'due_at' => Carbon::now(),
        ]);
    }

    private function payment(PlatformInvoice $invoice, string $status, string $provider): void
    {
        $failures = ['Card declined', 'Insufficient funds', 'Mobile wallet timed out', 'Bank rejected the debit'];

        Payment::create([
            'platform_invoice_id' => $invoice->id,
            'company_id' => $invoice->company_id,
            'provider' => $provider,
            'transaction_reference' => 'PAY-'.$invoice->id.'-'.strtoupper(bin2hex(random_bytes(4))),
            'provider_reference' => strtoupper(substr($provider, 0, 3)).'-'.mt_rand(100000, 999999),
            'amount' => $invoice->amount,
            'currency' => $invoice->currency,
            'status' => $status,
            'failure_reason' => $status === 'failed' ? $failures[array_rand($failures)] : null,
            'paid_at' => $status === 'succeeded' ? Carbon::now() : null,
        ]);

        if ($status === 'succeeded') {
            $invoice->update(['status' => 'paid', 'paid_at' => Carbon::now(), 'provider' => $provider]);
        }
    }

    private function provider(string $country): string
    {
        return $this->pick(match ($country) {
            'ZW' => ['paynow' => 45, 'ecocash' => 30, 'innbucks' => 10, 'bank_transfer' => 15],
            'ZA', 'NA', 'BW' => ['stripe' => 55, 'paypal' => 15, 'bank_transfer' => 30],
            'GB', 'US' => ['stripe' => 75, 'paypal' => 25],
            default => ['stripe' => 40, 'paypal' => 25, 'bank_transfer' => 35],
        });
    }

    private function names(int $count): array
    {
        $names = [];
        foreach (self::FIRST as $first) {
            foreach (self::SECOND as $second) {
                $names[] = $first.' '.$second;
            }
        }
        for ($i = count($names) - 1; $i > 0; $i--) { // deterministic Fisher–Yates (seeded mt_rand)
            $j = mt_rand(0, $i);
            [$names[$i], $names[$j]] = [$names[$j], $names[$i]];
        }
        $existing = Company::pluck('name')->all();

        return array_slice(array_values(array_diff($names, $existing)), 0, $count);
    }

    /** Weighted random choice: [value => weight]. */
    private function pick(array $weights): string
    {
        $roll = mt_rand(1, array_sum($weights));
        foreach ($weights as $value => $weight) {
            if (($roll -= $weight) <= 0) {
                return (string) $value;
            }
        }

        return (string) array_key_first($weights);
    }
}
