<?php

namespace App\Services\CompanySetup;

use App\Models\Company;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\TaxCode;

/**
 * Tracks the company admin's onboarding wizard:
 * profile → currencies → tax & statutory → fiscal year & chart of accounts.
 */
class CompanySetupProgress
{
    public function __construct(private readonly StandardChartOfAccounts $chart)
    {
    }

    /** @return array<string, array{label: string, route: string, done: bool}> */
    public function steps(Company $company): array
    {
        return [
            'profile' => [
                'label' => 'Company profile',
                'route' => 'company.setup.edit',
                'done' => filled($company->vat_number) && $company->bankAccounts()->exists(),
            ],
            'currencies' => [
                'label' => 'Currencies',
                'route' => 'company.setup.currencies',
                'done' => Currency::withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->where('is_base', true)
                    ->where('status', true)
                    ->exists(),
            ],
            'tax' => [
                'label' => 'Tax & statutory',
                'route' => 'company.setup.tax',
                'done' => filled($company->tin) && TaxCode::withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->where('status', true)
                    ->exists(),
            ],
            'ledger' => [
                'label' => 'Fiscal year & accounts',
                'route' => 'company.setup.ledger',
                'done' => FiscalYear::withoutGlobalScopes()->where('company_id', $company->id)->exists()
                    && $this->chart->missingRequired($company) === [],
            ],
        ];
    }

    public function nextStepRoute(Company $company): ?string
    {
        foreach ($this->steps($company) as $step) {
            if (!$step['done']) {
                return $step['route'];
            }
        }

        return null;
    }

    /** Marks onboarding complete once every step is done. Returns true when the company is complete. */
    public function completeIfReady(Company $company): bool
    {
        if ($company->onboarding_completed_at) {
            return true;
        }

        if ($this->nextStepRoute($company) !== null) {
            return false;
        }

        $company->forceFill(['onboarding_completed_at' => now(), 'status' => true])->save();

        return true;
    }
}
