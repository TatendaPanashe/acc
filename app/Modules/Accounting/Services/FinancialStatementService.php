<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\JournalLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the profit & loss, balance sheet and cash flow from posted journal lines.
 */
class FinancialStatementService
{
    public function __construct(
        private readonly TrialBalanceService $trialBalance,
        private readonly PeriodService $periods,
        private readonly AccountMappingService $mappings,
    ) {
    }

    /**
     * Reporting window for income-type reports: the selected period, otherwise the
     * current fiscal year to date (or all activity to date when no fiscal year exists).
     *
     * @return array{from: ?Carbon, to: Carbon, label: string}
     */
    public function window(?AccountingPeriod $period): array
    {
        if ($period) {
            return ['from' => $period->starts_on->copy(), 'to' => $period->ends_on->copy(), 'label' => $period->name];
        }

        $today = now()->startOfDay();
        $year = $this->periods->fiscalYearFor($today, (int) auth()->user()?->company_id);

        return $year
            ? ['from' => $year->starts_on->copy(), 'to' => $today, 'label' => $year->name.' to date']
            : ['from' => null, 'to' => $today, 'label' => 'All activity to date'];
    }

    public function asAt(?AccountingPeriod $period): Carbon
    {
        return $period ? $period->ends_on->copy() : now()->startOfDay();
    }

    /** @return array{revenue: Collection, expenses: Collection, revenue_total: float, expense_total: float, net: float} */
    public function profitAndLoss(?Carbon $from, Carbon $to): array
    {
        $rows = $this->trialBalance->between($from, $to)->filter(fn ($a) => $a->debit_total != 0 || $a->credit_total != 0);
        $revenue = $rows->where('type', 'revenue')->values();
        $expenses = $rows->where('type', 'expense')->values();
        $revenueTotal = round((float) $revenue->sum('balance'), 2);
        $expenseTotal = round((float) $expenses->sum('balance'), 2);

        return [
            'revenue' => $revenue,
            'expenses' => $expenses,
            'revenue_total' => $revenueTotal,
            'expense_total' => $expenseTotal,
            'net' => round($revenueTotal - $expenseTotal, 2),
        ];
    }

    /**
     * Cumulative position as at a date. Revenue and expense accounts are not closed off at year end,
     * so their balances are brought into equity as:
     *  - retained earnings from prior years (everything before the current fiscal year), and
     *  - current year profit (fiscal year start to the as-at date).
     */
    public function balanceSheet(Carbon $asAt): array
    {
        $rows = $this->trialBalance->between(null, $asAt);
        $year = $this->periods->fiscalYearFor($asAt, (int) auth()->user()?->company_id);

        $netIncome = fn (Collection $set) => round((float) $set->where('type', 'revenue')->sum('balance') - (float) $set->where('type', 'expense')->sum('balance'), 2);

        $totalEarnings = $netIncome($rows);
        $priorYears = $year
            ? $netIncome($this->trialBalance->between(null, $year->starts_on->copy()->subDay()))
            : 0.0;
        $currentYear = round($totalEarnings - $priorYears, 2);

        $nonZero = fn (string $type) => $rows->where('type', $type)->filter(fn ($a) => $a->balance != 0)->values();
        $assets = $nonZero('asset');
        $liabilities = $nonZero('liability');
        $equity = $nonZero('equity');

        $assetTotal = round((float) $assets->sum('balance'), 2);
        $liabilityTotal = round((float) $liabilities->sum('balance'), 2);
        $equityTotal = round((float) $equity->sum('balance') + $priorYears + $currentYear, 2);

        return [
            'as_at' => $asAt,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'prior_year_earnings' => $priorYears,
            'current_year_earnings' => $currentYear,
            'asset_total' => $assetTotal,
            'liability_total' => $liabilityTotal,
            'equity_total' => $equityTotal,
            'difference' => round($assetTotal - $liabilityTotal - $equityTotal, 2),
        ];
    }

    /**
     * Opening balance, movements grouped by source and closing balance of cash & bank accounts.
     */
    public function cashFlow(?Carbon $from, Carbon $to): array
    {
        $cashIds = $this->cashAccountIds();
        $accounts = Account::whereIn('id', $cashIds)->orderBy('code')->get();

        $opening = $from
            ? $this->cashBalance($cashIds, $from->copy()->subDay())
            : 0.0;

        $movements = JournalLine::query()
            ->join('accounting_journal_entries as entries', 'entries.id', '=', 'accounting_journal_lines.journal_entry_id')
            ->where('entries.status', 'posted')
            ->whereIn('accounting_journal_lines.account_id', $cashIds ?: [0])
            ->whereDate('entries.entry_date', '<=', $to->toDateString())
            ->when($from, fn ($query) => $query->whereDate('entries.entry_date', '>=', $from->toDateString()))
            ->groupBy('entries.source_module')
            ->selectRaw('entries.source_module, SUM(accounting_journal_lines.debit) as inflow, SUM(accounting_journal_lines.credit) as outflow')
            ->get()
            ->map(fn ($row) => [
                'source' => self::sourceLabel($row->source_module),
                'inflow' => round((float) $row->inflow, 2),
                'outflow' => round((float) $row->outflow, 2),
            ])
            ->sortBy('source')
            ->values();

        $inflow = round((float) $movements->sum('inflow'), 2);
        $outflow = round((float) $movements->sum('outflow'), 2);

        return [
            'accounts' => $accounts,
            'opening' => $opening,
            'movements' => $movements,
            'inflow' => $inflow,
            'outflow' => $outflow,
            'net' => round($inflow - $outflow, 2),
            'closing' => round($opening + $inflow - $outflow, 2),
        ];
    }

    /** Cash and bank accounts: every account mapped to a payment method, plus GL accounts linked to company bank accounts. */
    public function cashAccountIds(): array
    {
        $companyId = (int) auth()->user()?->company_id;
        $ids = $this->mappings->tenderAccountIds($companyId);

        $linked = \App\Models\CompanyBankAccount::where('company_id', $companyId)->whereNotNull('gl_account_id')->pluck('gl_account_id')->all();

        return array_values(array_unique(array_merge($ids, $linked)));
    }

    private function cashBalance(array $accountIds, Carbon $asAt): float
    {
        return round((float) $this->trialBalance->between(null, $asAt)->whereIn('id', $accountIds)->sum('balance'), 2);
    }

    public static function sourceLabel(?string $module): string
    {
        return match ($module) {
            'accounts_receivable' => 'Customer receipts & sales',
            'accounts_payable' => 'Supplier payments & purchases',
            'point_of_sale' => 'Point of sale',
            'inventory' => 'Inventory',
            'banking' => 'Bank adjustments',
            'fixed_assets' => 'Fixed assets',
            null, '' => 'Manual journals',
            default => ucwords(str_replace('_', ' ', $module)),
        };
    }
}
