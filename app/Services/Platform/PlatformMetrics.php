<?php

namespace App\Services\Platform;

use App\Models\Company;
use App\Models\Platform\Payment;
use App\Models\Platform\Subscription;
use App\Models\Platform\SubscriptionEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Platform KPIs for one calendar month. Money is in the plans' currency (USD).
 *
 *  - MRR at a date = sum of the MRR deltas recorded up to that date (the subscription event ledger).
 *  - ARR = MRR × 12.
 *  - Revenue = invoices paid in the period.
 *  - Account counts (active, trial, suspended) are a snapshot of right now.
 */
class PlatformMetrics
{
    public readonly Carbon $start;
    public readonly Carbon $end;

    public function __construct(Carbon $month)
    {
        $this->start = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $this->end = $monthEnd->isFuture() ? now() : $monthEnd;
    }

    public static function forMonth(?string $yyyymm): self
    {
        $month = $yyyymm && preg_match('/^\d{4}-\d{2}$/', $yyyymm) ? Carbon::createFromFormat('Y-m-d', $yyyymm.'-01') : now();

        return new self($month->isFuture() ? now() : $month);
    }

    public function previous(): self
    {
        return new self($this->start->copy()->subMonthNoOverflow());
    }

    public function label(): string
    {
        return $this->start->format('M j').' – '.$this->end->format('M j, Y');
    }

    // ---- recurring revenue ------------------------------------------------

    public function mrrAt(Carbon $at): float
    {
        return round((float) SubscriptionEvent::where('occurred_at', '<=', $at)->sum('mrr_delta'), 2);
    }

    public function mrr(): float
    {
        return $this->mrrAt($this->end);
    }

    public function arr(): float
    {
        return round($this->mrr() * 12, 2);
    }

    /** @return Collection<int, array{label: string, month: string, mrr: float, current: bool}> */
    public function mrrHistory(int $months = 12): Collection
    {
        return collect(range($months - 1, 0))->map(function (int $back) {
            $month = $this->start->copy()->subMonthsNoOverflow($back);
            $end = $month->copy()->endOfMonth();

            return [
                'label' => $month->format("M 'y"),
                'month' => $month->format('Y-m'),
                'mrr' => $this->mrrAt($end->isFuture() ? now() : $end),
                'current' => $back === 0,
            ];
        });
    }

    /** @return array{new: float, expansion: float, contraction: float, churn: float, reactivation: float, net: float} */
    public function movements(): array
    {
        $sums = SubscriptionEvent::whereBetween('occurred_at', [$this->start, $this->end])
            ->whereNotNull('movement')
            ->selectRaw('movement, SUM(mrr_delta) as total')
            ->groupBy('movement')
            ->pluck('total', 'movement');

        $get = fn (string $key) => round((float) ($sums[$key] ?? 0), 2);

        return [
            'new' => $get('new'),
            'expansion' => $get('expansion'),
            'contraction' => abs($get('contraction')),
            'churn' => abs($get('churn')),
            'reactivation' => $get('reactivation'),
            'net' => round((float) $sums->sum(), 2),
        ];
    }

    // ---- accounts -----------------------------------------------------------

    public function totalBusinesses(): int
    {
        return Company::where('created_at', '<=', $this->end)->count();
    }

    public function newRegistrations(): int
    {
        return Company::whereBetween('created_at', [$this->start, $this->end])->count();
    }

    /** @return array{active: int, paying: int, promotional: int, past_due: int, trial: int, trial_expired: int, suspended: int, cancelled: int} */
    public function accountSnapshot(): array
    {
        $byStatus = Subscription::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $promotional = Subscription::whereIn('status', Subscription::PAYING)->where('is_promotional', true)->count();
        $trialExpired = Subscription::where(fn ($q) => $q->where('status', 'expired')
            ->orWhere(fn ($q) => $q->where('status', 'trialing')->where('trial_ends_at', '<', now())))->count();
        $active = (int) ($byStatus['active'] ?? 0) + (int) ($byStatus['past_due'] ?? 0);

        return [
            'active' => $active,
            'paying' => $active - $promotional,
            'promotional' => $promotional,
            'past_due' => (int) ($byStatus['past_due'] ?? 0),
            'trial' => Subscription::where('status', 'trialing')->where('trial_ends_at', '>=', now())->count(),
            'trial_expired' => $trialExpired,
            'suspended' => (int) ($byStatus['suspended'] ?? 0),
            'cancelled' => (int) ($byStatus['cancelled'] ?? 0),
        ];
    }

    /** Highest-value paying subscriptions, for the side list. */
    public function topSubscriptions(int $limit = 6): Collection
    {
        return Subscription::with(['company', 'plan'])
            ->whereIn('status', Subscription::PAYING)
            ->get()
            ->sortByDesc(fn (Subscription $s) => $s->mrr())
            ->take($limit)
            ->values();
    }

    // ---- billing ------------------------------------------------------------

    /** @return array{count: int, amount: float, recent: Collection} */
    public function failedPayments(int $recent = 5): array
    {
        $query = Payment::where('status', 'failed')->whereBetween('created_at', [$this->start, $this->end]);

        return [
            'count' => (clone $query)->count(),
            'amount' => round((float) (clone $query)->sum('amount'), 2),
            'recent' => (clone $query)->with('company')->latest('created_at')->take($recent)->get(),
        ];
    }

    /** @return array{count: int, lost_mrr: float, recent: Collection} */
    public function cancellations(int $recent = 5): array
    {
        $query = SubscriptionEvent::where('type', 'cancelled')->whereBetween('occurred_at', [$this->start, $this->end]);

        return [
            'count' => (clone $query)->count(),
            'lost_mrr' => round(abs((float) (clone $query)->sum('mrr_delta')), 2),
            'recent' => (clone $query)->with('company')->latest('occurred_at')->take($recent)->get(),
        ];
    }

    public function revenue(): float
    {
        return round((float) $this->paidInvoices()->sum('amount'), 2);
    }

    /** @return Collection<int, array{key: string, label: string, amount: float, share: float}> */
    public function revenueByCountry(): Collection
    {
        $rows = $this->paidInvoices()
            ->join('companies', 'companies.id', '=', 'platform_payments.company_id')
            ->selectRaw('companies.country as dimension, SUM(platform_payments.amount) as amount')
            ->groupBy('companies.country')
            ->get();

        return $this->ranked($rows, fn (?string $code) => config('platform.countries.'.$code) ?? ($code ?: 'Not set'));
    }

    /** @return Collection<int, array{key: string, label: string, amount: float, share: float}> */
    public function revenueByProvider(): Collection
    {
        $rows = $this->paidInvoices()
            ->selectRaw('provider as dimension, SUM(amount) as amount')
            ->groupBy('provider')
            ->get();

        return $this->ranked($rows, fn (?string $key) => config('platform.payment_providers.'.$key) ?? ($key ? ucfirst($key) : 'Not recorded'));
    }

    // ---- registrations & conversion ----------------------------------------

    /**
     * Monthly registration cohorts. Conversion = converted ÷ (converted + did not convert); businesses
     * still inside their trial are excluded until they decide.
     *
     * @return Collection<int, array{label: string, month: string, registered: int, converted: int, lapsed: int, pending: int, rate: ?float, current: bool}>
     */
    public function registrationHistory(int $months = 12): Collection
    {
        return collect(range($months - 1, 0))->map(function (int $back) {
            $from = $this->start->copy()->subMonthsNoOverflow($back)->startOfMonth();
            $to = $from->copy()->endOfMonth();
            $cohort = Company::whereBetween('created_at', [$from, $to]);
            $registered = (clone $cohort)->count();
            $converted = (clone $cohort)->whereHas('subscription', fn ($q) => $q->whereNotNull('activated_at'))->count();
            $pending = (clone $cohort)->whereHas('subscription', fn ($q) => $q->whereNull('activated_at')->where('status', 'trialing')->where('trial_ends_at', '>=', now()))->count();
            $lapsed = max(0, $registered - $converted - $pending);
            $decided = $converted + $lapsed;

            return [
                'label' => $from->format("M 'y"),
                'month' => $from->format('Y-m'),
                'registered' => $registered,
                'converted' => $converted,
                'lapsed' => $lapsed,
                'pending' => $pending,
                'rate' => $decided > 0 ? round($converted / $decided * 100, 1) : null,
                'current' => $back === 0,
            ];
        });
    }

    /** Share of businesses registered in this month that have become paying customers. */
    public function conversionRate(): ?float
    {
        return $this->registrationHistory(1)->first()['rate'];
    }

    /** Percentage change, null when there is no base to compare with. */
    public static function change(float $current, float $previous): ?float
    {
        if ($previous == 0.0) {
            return $current == 0.0 ? 0.0 : null;
        }

        return round(($current - $previous) / abs($previous) * 100, 1);
    }

    /** Money actually collected: successful payments in the period. */
    private function paidInvoices()
    {
        return Payment::where('platform_payments.status', 'succeeded')
            ->whereBetween('platform_payments.paid_at', [$this->start, $this->end]);
    }

    private function ranked(Collection $rows, callable $label): Collection
    {
        $total = (float) $rows->sum('amount');

        return $rows
            ->map(fn ($row) => [
                'key' => (string) ($row->dimension ?? ''),
                'label' => $label($row->dimension),
                'amount' => round((float) $row->amount, 2),
                'share' => $total > 0 ? round((float) $row->amount / $total * 100, 1) : 0.0,
            ])
            ->sortByDesc('amount')
            ->values();
    }
}
