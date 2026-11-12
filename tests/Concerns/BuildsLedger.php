<?php

namespace Tests\Concerns;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\FiscalYear;
use App\Services\CompanySetup\StandardChartOfAccounts;
use Illuminate\Support\Carbon;

/** Creates a fully set-up company: chart of accounts, base currency and a fiscal year of open monthly periods. */
trait BuildsLedger
{
    protected function createLedgerCompany(string $name = 'Acme Ltd', ?int $year = null): Company
    {
        // New records are stamped with the signed-in user's company, so build the ledger signed out.
        $user = auth()->user();
        auth()->forgetUser();

        try {
            return $this->buildLedgerCompany($name, $year);
        } finally {
            if ($user) {
                auth()->setUser($user);
            }
        }
    }

    private function buildLedgerCompany(string $name, ?int $year): Company
    {
        $company = Company::create(['name' => $name.' '.uniqid(), 'vat_number' => uniqid('V'), 'status' => true, 'onboarding_completed_at' => now()]);
        app(StandardChartOfAccounts::class)->install($company);

        $currency = new Currency(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'is_base' => true, 'status' => true]);
        $currency->company_id = $company->id;
        $currency->save();

        $start = Carbon::create($year ?? now()->year, 1, 1);
        $fiscalYear = new FiscalYear(['name' => 'FY '.$start->year, 'starts_on' => $start, 'ends_on' => $start->copy()->endOfYear(), 'status' => 'open']);
        $fiscalYear->company_id = $company->id;
        $fiscalYear->save();

        for ($m = 0; $m < 12; $m++) {
            $periodStart = $start->copy()->addMonths($m);
            $period = new AccountingPeriod([
                'fiscal_year_id' => $fiscalYear->id, 'name' => $periodStart->format('M Y'),
                'starts_on' => $periodStart, 'ends_on' => $periodStart->copy()->endOfMonth(), 'status' => 'open',
            ]);
            $period->company_id = $company->id;
            $period->save();
        }

        return $company;
    }

    protected function ledgerUser(Company $company, string $role = 'admin'): User
    {
        return User::create([
            'name' => ucfirst($role), 'username' => $role.uniqid(), 'email' => uniqid().'@example.com',
            'password' => 'secret-password', 'company_id' => $company->id, 'role' => $role,
        ]);
    }

    protected function ledgerAccount(Company $company, string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', $code)->firstOrFail();
    }
}
