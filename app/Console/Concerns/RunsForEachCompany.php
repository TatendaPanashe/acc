<?php

namespace App\Console\Concerns;

use App\Models\Company;
use App\Models\Platform\Subscription;
use App\Models\User;
use App\Services\Billing\SubscriptionAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Accounting services are scoped to the signed-in user's company, so scheduled jobs run once per
 * company as that company's first Company Admin (or Finance Manager if it has no admin). Suspended companies and locked workspaces are skipped.
 */
trait RunsForEachCompany
{
    protected function forEachCompany(callable $callback, ?int $onlyCompanyId = null): void
    {
        $access = app(SubscriptionAccess::class);
        $companies = Company::query()->where('status', true)->whereNotNull('onboarding_completed_at')
            ->when($onlyCompanyId, fn ($q) => $q->whereKey($onlyCompanyId))->get();

        foreach ($companies as $company) {
            if (($access->check(Subscription::where('company_id', $company->id)->first())['state'] ?? 'open') === 'locked') {
                continue;
            }
            $actor = User::where('company_id', $company->id)->whereIn('role', ['admin', 'finance_manager'])->where('is_superuser', false)
                ->orderByRaw("CASE role WHEN 'admin' THEN 0 ELSE 1 END")->orderBy('id')->first();
            if (!$actor) {
                continue;
            }

            Auth::setUser($actor);
            try {
                $callback($company, $actor);
            } catch (\Throwable $e) {
                Log::error(static::class.' failed for company '.$company->id.': '.$e->getMessage());
                $this->error($company->name.': '.$e->getMessage());
            } finally {
                Auth::forgetUser();
            }
        }
    }
}
