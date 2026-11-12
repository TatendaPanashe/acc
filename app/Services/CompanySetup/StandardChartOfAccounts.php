<?php

namespace App\Services\CompanySetup;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;

/**
 * Default chart of accounts loaded during company setup.
 * The codes in REQUIRED are used by automatic posting (invoices, receipts, POS, AP).
 */
class StandardChartOfAccounts
{
    public const REQUIRED = ['1000', '1100', '1200', '2000', '2100', '4000', '5000'];

    /** @var array<int, array{0: string, 1: string, 2: string, 3: string, 4: bool}> code, name, type, normal balance, control */
    public const ACCOUNTS = [
        ['1000', 'Cash and Bank', 'asset', 'debit', true],
        ['1100', 'Accounts Receivable', 'asset', 'debit', true],
        ['1200', 'Inventory', 'asset', 'debit', true],
        ['1250', 'Work in Progress', 'asset', 'debit', false],
        ['1300', 'Prepayments', 'asset', 'debit', false],
        ['1500', 'Property, Plant and Equipment', 'asset', 'debit', true],
        ['1550', 'Accumulated Depreciation', 'asset', 'credit', false],
        ['2000', 'Accounts Payable', 'liability', 'credit', true],
        ['2050', 'Goods Received Not Invoiced', 'liability', 'credit', false],
        ['2100', 'VAT Payable', 'liability', 'credit', true],
        ['2200', 'PAYE Payable', 'liability', 'credit', false],
        ['2210', 'NSSA Payable', 'liability', 'credit', false],
        ['2300', 'Accrued Expenses', 'liability', 'credit', false],
        ['2400', 'Income Tax Payable', 'liability', 'credit', false],
        ['3000', 'Retained Earnings', 'equity', 'credit', false],
        ['3100', 'Share Capital', 'equity', 'credit', false],
        ['3900', 'Opening Balance Equity', 'equity', 'credit', false],
        ['4000', 'Sales Revenue', 'revenue', 'credit', false],
        ['4100', 'Other Income', 'revenue', 'credit', false],
        ['4200', 'Exchange Gains', 'revenue', 'credit', false],
        ['5000', 'Cost of Goods Sold', 'expense', 'debit', false],
        ['6000', 'Operating Expenses', 'expense', 'debit', false],
        ['6100', 'Salaries and Wages', 'expense', 'debit', false],
        ['6200', 'Rent', 'expense', 'debit', false],
        ['6300', 'Utilities', 'expense', 'debit', false],
        ['6400', 'Bank Charges', 'expense', 'debit', false],
        ['6500', 'Depreciation', 'expense', 'debit', false],
        ['6190', 'Labour & Overhead Applied', 'expense', 'credit', false],
        ['6600', 'Exchange Losses', 'expense', 'debit', false],
    ];

    /**
     * Creates any standard accounts the company does not have yet; never overwrites existing ones.
     * Pass $only to limit which codes are created.
     */
    public function install(Company $company, ?array $only = null): int
    {
        $existing = Account::withoutGlobalScopes()->where('company_id', $company->id)->pluck('code')->all();
        $created = 0;

        foreach (self::ACCOUNTS as [$code, $name, $type, $normalBalance, $control]) {
            if (in_array($code, $existing, true) || ($only !== null && !in_array($code, $only, true))) {
                continue;
            }

            $account = new Account([
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'normal_balance' => $normalBalance,
                'is_control_account' => $control,
                'allow_posting' => true,
                'status' => true,
            ]);
            $account->company_id = $company->id;
            $account->save();
            $created++;
        }

        return $created;
    }

    public function missingRequired(Company $company): array
    {
        $existing = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('code', self::REQUIRED)
            ->pluck('code')
            ->all();

        return array_values(array_diff(self::REQUIRED, $existing));
    }
}
