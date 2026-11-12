<?php

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\Platform\Plan;
use App\Models\Platform\Subscription;
use App\Models\Platform\UsageRecord;
use App\Models\User;
use App\Modules\Accounting\Models\Warehouse;
use Illuminate\Validation\ValidationException;

/** What a business's plan allows: features and limits (users, branches, companies). */
class Entitlements
{
    public function plan(Company $company): ?Plan
    {
        return $this->subscription($company)?->plan?->loadMissing('features');
    }

    /** Always read fresh: a plan change earlier in the same process must take effect. */
    private function subscription(Company $company): ?Subscription
    {
        return $company->subscription()->with('plan.features')->first();
    }

    public function has(Company $company, string $feature): bool
    {
        return (bool) $this->plan($company)?->hasFeature($feature);
    }

    /** Null = unlimited. Custom (Enterprise) overrides on the subscription win. */
    public function limit(Company $company, string $metric): ?int
    {
        $overrides = $this->subscription($company)?->limit_overrides ?? [];
        if (array_key_exists($metric, $overrides)) {
            return $overrides[$metric] === null ? null : (int) $overrides[$metric];
        }

        return $this->plan($company)?->limit($metric);
    }

    public function usage(Company $company, string $metric): int
    {
        return match ($metric) {
            'users' => User::where('company_id', $company->id)->where('is_superuser', false)->count(),
            'branches' => Warehouse::withoutGlobalScopes()->where('company_id', $company->id)->where('status', true)->count(),
            'companies' => 1,
            default => 0,
        };
    }

    /** @return array<string, array{label: string, used: int, limit: ?int}> */
    public function usageSummary(Company $company): array
    {
        return collect(['users', 'branches', 'companies'])->mapWithKeys(fn ($metric) => [$metric => [
            'label' => config('billing.features.'.$metric)[0],
            'used' => $this->usage($company, $metric),
            'limit' => $this->limit($company, $metric),
        ]])->all();
    }

    /** Throws a validation error when adding $adding more would exceed the plan limit. */
    public function assertWithinLimit(Company $company, string $metric, int $adding = 1, string $field = 'limit'): void
    {
        $limit = $this->limit($company, $metric);
        if ($limit === null || $this->usage($company, $metric) + $adding <= $limit) {
            return;
        }

        $plan = $this->plan($company);
        throw ValidationException::withMessages([
            $field => 'Your '.($plan?->name ?? 'current').' plan includes '.$limit.' '.str(strtolower(config('billing.features.'.$metric)[0]))->singular()->plural($limit).'. Upgrade your plan under Billing to add more.',
        ]);
    }

    public function assertFeature(Company $company, string $feature, string $field = 'feature'): void
    {
        if ($this->has($company, $feature)) {
            return;
        }

        throw ValidationException::withMessages([$field => $this->upgradeMessage($feature)]);
    }

    public function upgradeMessage(string $feature): string
    {
        $label = config('billing.features.'.$feature)[0] ?? $feature;
        $plan = Plan::cheapestWith($feature);

        return $label.' is available on the '.($plan?->name ?? 'higher').' plan and above. Upgrade under Billing to use it.';
    }

    public function recordUsage(Company $company): void
    {
        foreach (['users', 'branches', 'companies'] as $metric) {
            UsageRecord::updateOrCreate(
                ['company_id' => $company->id, 'metric' => $metric, 'period' => now()->format('Y-m')],
                ['quantity' => $this->usage($company, $metric)],
            );
        }
    }
}
