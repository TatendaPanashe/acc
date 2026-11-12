<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\AccountMapping;
use App\Modules\Accounting\Models\BankStatement;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\TaxCode;
use App\Services\CompanySetup\CompanySetupProgress;
use App\Services\CompanySetup\StandardChartOfAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Company admin onboarding wizard:
 * 1. profile & bank accounts  2. currencies  3. tax & statutory  4. fiscal year & chart of accounts.
 */
class CompanySetupController extends Controller
{
    public const CURRENCIES = [
        'USD' => ['United States Dollar', '$'],
        'ZWG' => ['Zimbabwe Gold', 'ZiG'],
        'ZAR' => ['South African Rand', 'R'],
        'BWP' => ['Botswana Pula', 'P'],
        'GBP' => ['British Pound', '£'],
        'EUR' => ['Euro', '€'],
    ];

    public function __construct(private readonly CompanySetupProgress $progress)
    {
    }

    // Step 1: profile -----------------------------------------------------

    public function edit(Request $request): View
    {
        $company = $this->company($request);
        $bankAccounts = $company->bankAccounts()->where('status', true)->orderByDesc('is_primary')->orderBy('bank_name')->get();

        return view('company.setup', $this->wizard($company, 'profile') + compact('company', 'bankAccounts'));
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $this->company($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('companies')->ignore($company)],
            'vat_number' => ['required', 'string', 'max:100', Rule::unique('companies')->ignore($company)],
            'registration_number' => ['nullable', 'string', 'max:255', Rule::unique('companies')->ignore($company)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'country' => ['required', Rule::in(array_keys(config('platform.countries')))],
            'bank_accounts' => ['required', 'array', 'min:1'],
            'bank_accounts.*.bank_name' => ['required', 'string', 'max:255'],
            'bank_accounts.*.account_name' => ['required', 'string', 'max:255'],
            'bank_accounts.*.account_number' => ['required', 'string', 'max:100'],
            'bank_accounts.*.branch_code' => ['nullable', 'string', 'max:100'],
            'bank_accounts.*.swift_code' => ['nullable', 'string', 'max:100'],
            'bank_accounts.*.currency' => ['required', 'string', 'size:3'],
            'primary_bank_account' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($company, $data) {
            $company->update([
                'name' => $data['name'],
                'vat_number' => $data['vat_number'],
                'registration_number' => $data['registration_number'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'country' => $data['country'],
                'status' => true,
            ]);

            // Update in place so GL links and imported statements survive; match on account number.
            $keep = [];
            foreach ($data['bank_accounts'] as $index => $bankAccount) {
                $attributes = [
                    'bank_name' => $bankAccount['bank_name'],
                    'account_name' => $bankAccount['account_name'],
                    'branch_code' => $bankAccount['branch_code'] ?? null,
                    'swift_code' => $bankAccount['swift_code'] ?? null,
                    'currency' => strtoupper($bankAccount['currency']),
                    'is_primary' => (int) ($data['primary_bank_account'] ?? 0) === $index,
                    'status' => true,
                ];
                $existing = $company->bankAccounts()->where('account_number', $bankAccount['account_number'])->first();
                $existing
                    ? $existing->update($attributes)
                    : $existing = $company->bankAccounts()->create($attributes + ['account_number' => $bankAccount['account_number']]);
                $keep[] = $existing->id;
            }

            foreach ($company->bankAccounts()->whereNotIn('id', $keep)->get() as $removed) {
                BankStatement::where('company_bank_account_id', $removed->id)->exists()
                    ? $removed->update(['status' => false, 'is_primary' => false])
                    : $removed->delete();
            }
        });

        return $this->continue($company, 'Company profile saved.');
    }

    // Step 2: currencies --------------------------------------------------

    public function currencies(Request $request): View|RedirectResponse
    {
        $company = $this->company($request);

        if ($redirect = $this->guardStep($company, 'currencies')) {
            return $redirect;
        }

        $existing = Currency::where('status', true)->pluck('code')->all();
        $base = Currency::where('is_base', true)->value('code')
            ?? $company->bankAccounts()->where('is_primary', true)->value('currency')
            ?? 'USD';
        $selected = $existing ?: $company->bankAccounts()->pluck('currency')->unique()->values()->all();

        return view('company.setup.currencies', $this->wizard($company, 'currencies') + [
            'options' => self::CURRENCIES,
            'base' => $base,
            'selected' => $selected,
        ]);
    }

    public function storeCurrencies(Request $request): RedirectResponse
    {
        $company = $this->company($request);

        if ($redirect = $this->guardStep($company, 'currencies')) {
            return $redirect;
        }

        $codes = array_keys(self::CURRENCIES);
        $data = $request->validate([
            'base_currency' => ['required', Rule::in($codes)],
            'currencies' => ['nullable', 'array'],
            'currencies.*' => [Rule::in($codes)],
        ]);

        $selected = array_unique([$data['base_currency'], ...($data['currencies'] ?? [])]);

        DB::transaction(function () use ($selected, $data) {
            Currency::query()->update(['is_base' => false]);

            foreach ($selected as $code) {
                [$name, $symbol] = self::CURRENCIES[$code];
                Currency::updateOrCreate(
                    ['code' => $code],
                    ['name' => $name, 'symbol' => $symbol, 'is_base' => $code === $data['base_currency'], 'status' => true],
                );
            }
        });

        return $this->continue($company, 'Currencies saved. Base currency: '.$data['base_currency'].'.');
    }

    // Step 3: tax & statutory ---------------------------------------------

    public function tax(Request $request): View|RedirectResponse
    {
        $company = $this->company($request);

        if ($redirect = $this->guardStep($company, 'tax')) {
            return $redirect;
        }

        $standard = TaxCode::where('code', 'VAT-STD')->first();

        return view('company.setup.tax', $this->wizard($company, 'tax') + [
            'company' => $company,
            'standardRate' => $standard ? (float) $standard->rate : 15.5,
            'hasExempt' => TaxCode::where('code', 'VAT-EXEMPT')->exists(),
        ]);
    }

    public function storeTax(Request $request): RedirectResponse
    {
        $company = $this->company($request);

        if ($redirect = $this->guardStep($company, 'tax')) {
            return $redirect;
        }

        $data = $request->validate([
            'tin' => ['required', 'string', 'max:30'],
            'nssa_number' => ['nullable', 'string', 'max:30'],
            'standard_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'include_exempt' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($company, $data, $request) {
            $company->update(['tin' => $data['tin'], 'nssa_number' => $data['nssa_number'] ?? null]);

            $vatAccountId = Account::where('code', '2100')->value('id');
            $codes = [
                'VAT-STD' => ['Standard-rated VAT', (float) $data['standard_rate']],
                'VAT-ZERO' => ['Zero-rated VAT', 0],
            ];
            if ($request->boolean('include_exempt', true)) {
                $codes['VAT-EXEMPT'] = ['VAT exempt', 0];
            }

            foreach ($codes as $code => [$name, $rate]) {
                TaxCode::updateOrCreate(
                    ['code' => $code],
                    ['name' => $name, 'type' => 'both', 'rate' => $rate, 'control_account_id' => $vatAccountId, 'status' => true],
                );
            }
        });

        return $this->continue($company, 'Tax and statutory details saved.');
    }

    // Step 4: fiscal year & chart of accounts -----------------------------

    public function ledger(Request $request, StandardChartOfAccounts $chart): View|RedirectResponse
    {
        $company = $this->company($request);

        if ($redirect = $this->guardStep($company, 'ledger')) {
            return $redirect;
        }

        return view('company.setup.ledger', $this->wizard($company, 'ledger') + [
            'fiscalYear' => FiscalYear::orderByDesc('starts_on')->first(),
            'accounts' => StandardChartOfAccounts::ACCOUNTS,
            'existingCodes' => Account::pluck('code')->all(),
            'missingRequired' => $chart->missingRequired($company),
        ]);
    }

    public function storeLedger(Request $request, StandardChartOfAccounts $chart): RedirectResponse
    {
        $company = $this->company($request);

        if ($redirect = $this->guardStep($company, 'ledger')) {
            return $redirect;
        }

        $data = $request->validate([
            'start_month' => ['required', 'integer', 'between:1,12'],
            'start_year' => ['required', 'integer', 'between:2000,2100'],
        ]);

        DB::transaction(function () use ($company, $data, $chart) {
            $chart->install($company);

            // Link VAT codes captured in step 3 to the VAT control account.
            $vatAccountId = Account::where('code', '2100')->value('id');
            TaxCode::whereNull('control_account_id')->update(['control_account_id' => $vatAccountId]);

            if (!FiscalYear::exists()) {
                $this->createFiscalYear((int) $data['start_year'], (int) $data['start_month']);
            }

            $this->createBankLedgerAccounts($company);
        });

        return $this->continue($company, 'Fiscal year and chart of accounts created.');
    }

    // Shared helpers ------------------------------------------------------

    private function createFiscalYear(int $year, int $month): void
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->addYear()->subDay();
        $name = $month === 1 ? 'FY '.$year : 'FY '.$year.'/'.$end->format('y');

        $fiscalYear = FiscalYear::create([
            'name' => $name,
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
            'status' => 'open',
        ]);

        for ($i = 0; $i < 12; $i++) {
            $periodStart = $start->copy()->addMonthsNoOverflow($i);
            AccountingPeriod::create([
                'fiscal_year_id' => $fiscalYear->id,
                'name' => $periodStart->format('M Y'),
                'starts_on' => $periodStart->toDateString(),
                'ends_on' => $periodStart->copy()->endOfMonth()->toDateString(),
                'status' => 'open',
            ]);
        }
    }

    /**
     * One GL account per company bank account (1010, 1011, …) under Cash and Bank, so each bank can be
     * reconciled on its own. The primary bank becomes the default account for bank receipts and payments.
     */
    private function createBankLedgerAccounts(Company $company): void
    {
        $parent = Account::where('code', '1000')->first();
        $code = 1010;

        foreach ($company->bankAccounts()->orderByDesc('is_primary')->orderBy('id')->get() as $bank) {
            if ($bank->gl_account_id) {
                continue;
            }
            while (Account::where('code', (string) $code)->exists()) {
                $code++;
            }

            $account = Account::create([
                'parent_id' => $parent?->id,
                'code' => (string) $code,
                'name' => 'Bank - '.$bank->bank_name.' '.$bank->currency,
                'type' => 'asset',
                'normal_balance' => 'debit',
                'is_control_account' => false,
                'allow_posting' => true,
                'status' => true,
            ]);
            $bank->update(['gl_account_id' => $account->id]);

            if ($bank->is_primary) {
                AccountMapping::updateOrCreate(['key' => 'bank'], ['account_id' => $account->id]);
            }
        }
    }

    private function company(Request $request): Company
    {
        $company = $request->user()->company;
        abort_unless($company, 403);

        return $company;
    }

    /** Steps 2-4 are only for onboarding; afterwards these are managed from Settings / General Ledger. */
    private function guardStep(Company $company, string $step): ?RedirectResponse
    {
        if ($company->onboarding_completed_at) {
            return redirect()->route('dashboard')
                ->with('warning', 'Setup is already complete. Manage these from Accounting Settings and the General Ledger.');
        }

        $steps = $this->progress->steps($company);
        foreach ($steps as $key => $item) {
            if ($key === $step) {
                return null;
            }
            if (!$item['done']) {
                return redirect()->route($item['route'])->with('warning', 'Please complete "'.$item['label'].'" first.');
            }
        }

        return null;
    }

    private function continue(Company $company, string $message): RedirectResponse
    {
        if ($company->onboarding_completed_at) {
            return redirect()->route('dashboard')->with('success', $message);
        }

        if ($this->progress->completeIfReady($company)) {
            return redirect()->route('company.team.index')
                ->with('success', 'Setup complete. Now create accounts for your team.');
        }

        return redirect()->route($this->progress->nextStepRoute($company))->with('success', $message);
    }

    private function wizard(Company $company, string $current): array
    {
        return ['steps' => $this->progress->steps($company), 'currentStep' => $current];
    }
}
