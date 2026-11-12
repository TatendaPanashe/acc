<?php

namespace App\Modules\Accounting\Services\Reporting;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Budget;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\JobCost;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Models\SalesInvoice;
use App\Modules\Accounting\Models\Subscription;
use App\Modules\Accounting\Models\SupplierInvoice;
use App\Modules\Accounting\Services\AccountMappingService;
use App\Modules\Accounting\Services\ExchangeRateService;
use App\Modules\Accounting\Services\FinancialStatementService;
use App\Modules\Accounting\Services\InventoryStockService;
use App\Modules\Accounting\Services\TrialBalanceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Management analytics and the report library. All amounts are in the company's base currency
 * (ledger amounts); document-based reports convert with each document's posting rate.
 *
 * Classification used for analysis: expense accounts coded 5xxx are cost of sales; asset accounts below
 * 1500 and liability accounts below 2500 are current.
 */
class ReportingService
{
    public const REPORTS = [
        'profit_and_loss' => ['Profit & loss', 'Income and expenses by account, with comparison and variance.', 'Financial statements', ['range', 'compare']],
        'balance_sheet' => ['Balance sheet', 'Assets, liabilities and equity at a date, against a comparison date.', 'Financial statements', ['as_at', 'compare']],
        'trial_balance' => ['Trial balance', 'Debit and credit totals per account for the range.', 'Financial statements', ['range']],
        'general_ledger' => ['General ledger detail', 'Every posted line for an account with running balance.', 'Financial statements', ['range', 'account']],
        'budget_vs_actual' => ['Budget vs actual', 'Budgets for the periods in the range against posted actuals.', 'Management', ['range']],
        'expense_analysis' => ['Expense analysis', 'Expenses by account with share of total and change on the comparison period.', 'Management', ['range', 'compare']],
        'sales_by_customer' => ['Sales by customer', 'Invoiced sales, tax, receipts and balance per customer.', 'Sales & receivables', ['range']],
        'sales_by_month' => ['Revenue by month', 'Posted revenue, cost of sales and gross margin per month.', 'Sales & receivables', ['range']],
        'ar_aging' => ['Receivables aging', 'Open customer invoices by days overdue.', 'Sales & receivables', ['as_at']],
        'ap_aging' => ['Payables aging', 'Open supplier invoices by days overdue.', 'Purchases & payables', ['as_at']],
        'inventory_valuation' => ['Inventory valuation', 'Quantity, average cost and value on hand per item and warehouse.', 'Inventory & operations', []],
        'job_profitability' => ['Job profitability', 'Budget, actual cost, invoiced revenue and margin per job.', 'Inventory & operations', []],
        'recurring_revenue' => ['Recurring revenue', 'Active contracts with monthly and annual recurring value.', 'Sales & receivables', []],
    ];

    public const AGING_BUCKETS = ['current' => 'Current', '1_30' => '1–30 days', '31_60' => '31–60 days', '61_90' => '61–90 days', '90_plus' => 'Over 90 days'];

    public function __construct(
        private readonly TrialBalanceService $trialBalance,
        private readonly FinancialStatementService $statements,
        private readonly AccountMappingService $mappings,
        private readonly InventoryStockService $stock,
        private readonly ExchangeRateService $rates,
    ) {
    }

    private function companyId(): int
    {
        return (int) auth()->user()->company_id;
    }

    // ---- date windows -------------------------------------------------------------

    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    public static function window(?string $preset, ?string $from = null, ?string $to = null): array
    {
        $today = now()->startOfDay();

        return match ($preset) {
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth(), $today->format('F Y')],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth(), $today->copy()->subMonthNoOverflow()->format('F Y')],
            'this_quarter' => [$today->copy()->firstOfQuarter(), $today->copy()->lastOfQuarter(), 'Q'.$today->quarter.' '.$today->year],
            'last_year' => [$today->copy()->subYear()->startOfYear(), $today->copy()->subYear()->endOfYear(), (string) ($today->year - 1)],
            'last_12' => [$today->copy()->subMonths(11)->startOfMonth(), $today->copy()->endOfMonth(), 'Last 12 months'],
            'custom' => [
                Carbon::parse($from ?: $today->copy()->startOfYear())->startOfDay(),
                Carbon::parse($to ?: $today)->startOfDay(),
                Carbon::parse($from ?: $today->copy()->startOfYear())->format('d M Y').' – '.Carbon::parse($to ?: $today)->format('d M Y'),
            ],
            default => [$today->copy()->startOfYear(), $today->copy(), 'Year to date '.$today->year],
        };
    }

    /** Comparison window: the same length immediately before, or the same dates a year earlier. */
    public static function comparison(Carbon $from, Carbon $to, string $mode): ?array
    {
        return match ($mode) {
            'previous_period' => [$from->copy()->subDays($from->diffInDays($to) + 1), $from->copy()->subDay(), 'Previous period'],
            'previous_year' => [$from->copy()->subYear(), $to->copy()->subYear(), 'Same period last year'],
            default => null,
        };
    }

    // ---- dashboard --------------------------------------------------------------

    public function kpis(Carbon $from, Carbon $to): array
    {
        $pl = $this->plSummary($from, $to);
        $balances = $this->trialBalance->between(null, $to)->keyBy('id');
        $cashIds = $this->statements->cashAccountIds();
        $ar = $this->mappedBalance('accounts_receivable', $balances);
        $ap = $this->mappedBalance('accounts_payable', $balances);
        $currentAssets = $balances->filter(fn ($a) => $a->type === 'asset' && (int) $a->code < 1500)->sum('balance');
        $currentLiabilities = $balances->filter(fn ($a) => $a->type === 'liability' && (int) $a->code < 2500)->sum('balance');
        $days = $from->diffInDays($to) + 1;
        $spend = $pl['cost_of_sales'] + $pl['operating_expenses'];

        return $pl + [
            'cash' => round((float) $balances->only($cashIds)->sum('balance'), 2),
            'receivables' => $ar,
            'payables' => $ap,
            'current_ratio' => $currentLiabilities > 0 ? round($currentAssets / $currentLiabilities, 2) : null,
            'dso' => $pl['revenue'] > 0 ? (int) round($ar / $pl['revenue'] * $days) : null,
            'dpo' => $spend > 0 ? (int) round($ap / $spend * $days) : null,
        ];
    }

    /** @return array{revenue: float, cost_of_sales: float, gross_profit: float, gross_margin: ?float, operating_expenses: float, net_profit: float, net_margin: ?float} */
    public function plSummary(Carbon $from, Carbon $to): array
    {
        $rows = $this->trialBalance->between($from, $to);
        $revenue = round((float) $rows->where('type', 'revenue')->sum('balance'), 2);
        $cos = round((float) $rows->filter(fn ($a) => $a->type === 'expense' && self::isCostOfSales($a))->sum('balance'), 2);
        $opex = round((float) $rows->filter(fn ($a) => $a->type === 'expense' && !self::isCostOfSales($a))->sum('balance'), 2);
        $gross = round($revenue - $cos, 2);
        $net = round($gross - $opex, 2);

        return [
            'revenue' => $revenue, 'cost_of_sales' => $cos, 'gross_profit' => $gross,
            'gross_margin' => $revenue != 0.0 ? round($gross / $revenue * 100, 1) : null,
            'operating_expenses' => $opex, 'net_profit' => $net,
            'net_margin' => $revenue != 0.0 ? round($net / $revenue * 100, 1) : null,
        ];
    }

    public static function isCostOfSales(Account $account): bool
    {
        return str_starts_with((string) $account->code, '5');
    }

    /** Monthly revenue / expenses / net from posted journals. */
    public function monthly(Carbon $from, Carbon $to): array
    {
        $start = $from->copy()->startOfMonth();
        $months = [];
        for ($m = $start->copy(); $m->lte($to); $m->addMonthNoOverflow()) {
            $months[$m->format('Y-m')] = ['label' => $m->format('M y'), 'revenue' => 0.0, 'cost_of_sales' => 0.0, 'expenses' => 0.0];
        }

        $rows = JournalLine::query()
            ->join('accounting_journal_entries as e', 'e.id', '=', 'accounting_journal_lines.journal_entry_id')
            ->join('accounting_accounts as a', 'a.id', '=', 'accounting_journal_lines.account_id')
            ->where('e.status', 'posted')
            ->whereIn('a.type', ['revenue', 'expense'])
            ->whereDate('e.entry_date', '>=', $start->toDateString())
            ->whereDate('e.entry_date', '<=', $to->toDateString())
            ->groupBy('e.entry_date', 'a.type', 'a.code')
            ->selectRaw('e.entry_date as day, a.type as type, a.code as code, SUM(accounting_journal_lines.debit) as d, SUM(accounting_journal_lines.credit) as c')
            ->get();

        foreach ($rows as $row) {
            $key = Carbon::parse($row->day)->format('Y-m');
            if (!isset($months[$key])) {
                continue;
            }
            if ($row->type === 'revenue') {
                $months[$key]['revenue'] += (float) $row->c - (float) $row->d;
            } elseif (str_starts_with((string) $row->code, '5')) {
                $months[$key]['cost_of_sales'] += (float) $row->d - (float) $row->c;
            } else {
                $months[$key]['expenses'] += (float) $row->d - (float) $row->c;
            }
        }

        return collect($months)->map(fn ($m) => $m + [
            'total_expenses' => round($m['cost_of_sales'] + $m['expenses'], 2),
            'net' => round($m['revenue'] - $m['cost_of_sales'] - $m['expenses'], 2),
        ])->all();
    }

    public function topCustomers(Carbon $from, Carbon $to, int $limit = 8): Collection
    {
        return $this->salesByCustomer($from, $to)->sortByDesc('net')->take($limit)
            ->map(fn ($r) => ['label' => $r['customer'], 'value' => $r['net'], 'sub' => $r['invoices'].' '.str('invoice')->plural($r['invoices'])])->values();
    }

    public function topExpenses(Carbon $from, Carbon $to, int $limit = 8): Collection
    {
        return $this->trialBalance->between($from, $to)->where('type', 'expense')->filter(fn ($a) => $a->balance > 0)
            ->sortByDesc('balance')->take($limit)
            ->map(fn ($a) => ['label' => $a->code.' '.$a->name, 'value' => (float) $a->balance])->values();
    }

    /** @return array{buckets: array<string, float>, rows: Collection} */
    public function aging(string $side, Carbon $asOf): array
    {
        $model = $side === 'ap' ? SupplierInvoice::class : SalesInvoice::class;
        $buckets = array_fill_keys(array_keys(self::AGING_BUCKETS), 0.0);
        $rows = $model::with($side === 'ap' ? 'supplier' : 'customer')
            ->whereIn('status', ['posted', 'part_paid'])
            ->whereDate('invoice_date', '<=', $asOf->toDateString())
            ->orderBy('due_date')->get()
            ->map(function ($invoice) use ($asOf, &$buckets, $side) {
                $balance = round((float) $invoice->total - (float) $invoice->amount_paid, 2);
                if ($balance <= 0) {
                    return null;
                }
                $base = round($balance * $this->rateFor($invoice->currency, $invoice->invoice_date), 2);
                $days = $invoice->due_date->lt($asOf) ? (int) $invoice->due_date->diffInDays($asOf) : 0;
                $bucket = match (true) { $days === 0 => 'current', $days <= 30 => '1_30', $days <= 60 => '31_60', $days <= 90 => '61_90', default => '90_plus' };
                $buckets[$bucket] += $base;

                return ['invoice' => $invoice, 'party' => $side === 'ap' ? $invoice->supplier?->name : $invoice->customer?->name, 'balance' => $balance, 'base' => $base, 'days' => $days, 'bucket' => $bucket];
            })->filter()->values();

        return ['buckets' => array_map(fn ($v) => round($v, 2), $buckets), 'rows' => $rows];
    }

    // ---- report library ------------------------------------------------------------

    /**
     * Runs a report. Result: title, subtitle, columns (key => [label, format]), rows (cells + style + link), notes.
     * Formats: text, money, percent, number, date. Row styles: heading, subtotal, total.
     */
    public function run(string $type, array $filters): array
    {
        [$from, $to, $label] = self::window($filters['range'] ?? 'ytd', $filters['from'] ?? null, $filters['to'] ?? null);
        $asAt = isset($filters['as_at']) ? Carbon::parse($filters['as_at'])->startOfDay() : now()->startOfDay();
        $compare = $filters['compare'] ?? 'none';
        [$title, $description] = self::REPORTS[$type];

        $report = match ($type) {
            'profit_and_loss' => $this->profitAndLoss($from, $to, $compare),
            'balance_sheet' => $this->balanceSheet($asAt, $compare),
            'trial_balance' => $this->trialBalanceReport($from, $to),
            'general_ledger' => $this->generalLedger($from, $to, isset($filters['account_id']) ? (int) $filters['account_id'] : null),
            'budget_vs_actual' => $this->budgetVsActual($from, $to),
            'expense_analysis' => $this->expenseAnalysis($from, $to, $compare),
            'sales_by_customer' => $this->salesByCustomerReport($from, $to),
            'sales_by_month' => $this->salesByMonth($from, $to),
            'ar_aging', 'ap_aging' => $this->agingReport($type === 'ap_aging' ? 'ap' : 'ar', $asAt),
            'inventory_valuation' => $this->inventoryValuation(),
            'job_profitability' => $this->jobProfitability(),
            'recurring_revenue' => $this->recurringRevenue(),
        };

        $usesRange = in_array('range', self::REPORTS[$type][3], true);
        $usesAsAt = in_array('as_at', self::REPORTS[$type][3], true);

        return $report + [
            'title' => $title,
            'subtitle' => $report['subtitle'] ?? ($usesRange ? $label : ($usesAsAt ? 'As at '.$asAt->format('d M Y') : 'As at '.now()->format('d M Y'))),
            'description' => $description,
            'notes' => $report['notes'] ?? [],
        ];
    }

    private function drill(Account $account, Carbon $from, Carbon $to): string
    {
        return route('accounting.reporting.run', ['type' => 'general_ledger', 'range' => 'custom', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'account_id' => $account->id]);
    }

    private function profitAndLoss(Carbon $from, Carbon $to, string $compare): array
    {
        $current = $this->trialBalance->between($from, $to)->keyBy('id');
        $cmp = self::comparison($from, $to, $compare);
        $prior = $cmp ? $this->trialBalance->between($cmp[0], $cmp[1])->keyBy('id') : collect();
        $columns = ['account' => ['Account', 'text'], 'current' => ['Current', 'money']];
        if ($cmp) {
            $columns += ['prior' => [$cmp[2], 'money'], 'variance' => ['Variance', 'money'], 'variance_pct' => ['Var %', 'percent']];
        }

        $rows = [];
        $sum = ['current' => 0.0, 'prior' => 0.0];
        $sections = [
            'Revenue' => fn ($a) => $a->type === 'revenue',
            'Cost of sales' => fn ($a) => $a->type === 'expense' && self::isCostOfSales($a),
            'Operating expenses' => fn ($a) => $a->type === 'expense' && !self::isCostOfSales($a),
        ];
        $totals = [];
        foreach ($sections as $name => $filter) {
            $rows[] = ['cells' => ['account' => $name], 'style' => 'heading'];
            $accounts = $current->filter($filter)->filter(fn ($a) => $a->balance != 0 || (float) ($prior[$a->id]->balance ?? 0) != 0);
            $sectionTotal = ['current' => 0.0, 'prior' => 0.0];
            foreach ($accounts as $account) {
                $p = (float) ($prior[$account->id]->balance ?? 0);
                $rows[] = ['cells' => $this->compareCells($account->code.' '.$account->name, (float) $account->balance, $p, $cmp !== null), 'link' => $this->drill($account, $from, $to)];
                $sectionTotal['current'] += (float) $account->balance;
                $sectionTotal['prior'] += $p;
            }
            $rows[] = ['cells' => $this->compareCells('Total '.strtolower($name), $sectionTotal['current'], $sectionTotal['prior'], $cmp !== null), 'style' => 'subtotal'];
            $totals[$name] = $sectionTotal;
            if ($name === 'Cost of sales') {
                $rows[] = ['cells' => $this->compareCells('Gross profit', $totals['Revenue']['current'] - $sectionTotal['current'], $totals['Revenue']['prior'] - $sectionTotal['prior'], $cmp !== null), 'style' => 'total'];
            }
        }
        $net = fn ($k) => $totals['Revenue'][$k] - $totals['Cost of sales'][$k] - $totals['Operating expenses'][$k];
        $rows[] = ['cells' => $this->compareCells('Net profit', $net('current'), $net('prior'), $cmp !== null), 'style' => 'total'];

        return ['columns' => $columns, 'rows' => $rows];
    }

    private function compareCells(string $label, float $current, float $prior, bool $withPrior): array
    {
        $cells = ['account' => $label, 'current' => round($current, 2)];
        if ($withPrior) {
            $cells += ['prior' => round($prior, 2), 'variance' => round($current - $prior, 2), 'variance_pct' => $prior != 0.0 ? round(($current - $prior) / abs($prior) * 100, 1) : null];
        }

        return $cells;
    }

    private function balanceSheet(Carbon $asAt, string $compare): array
    {
        $priorDate = match ($compare) {
            'previous_year' => $asAt->copy()->subYear(),
            'previous_period' => $asAt->copy()->subMonthNoOverflow()->endOfMonth(),
            default => null,
        };
        $sheet = fn (Carbon $date) => $this->statements->balanceSheet($date);
        $current = $sheet($asAt);
        $prior = $priorDate ? $sheet($priorDate) : null;
        $columns = ['account' => ['Account', 'text'], 'current' => [$asAt->format('d M Y'), 'money']];
        if ($prior) {
            $columns += ['prior' => [$priorDate->format('d M Y'), 'money'], 'variance' => ['Change', 'money']];
        }

        $rows = [];
        $totalKey = ['assets' => 'asset_total', 'liabilities' => 'liability_total', 'equity' => 'equity_total'];
        $withChange = function (string $label, float $now, ?float $before) use ($prior) {
            $cells = ['account' => $label, 'current' => round($now, 2)];

            return $prior ? $cells + ['prior' => round((float) $before, 2), 'variance' => round($now - (float) $before, 2)] : $cells;
        };
        foreach (['assets' => 'Assets', 'liabilities' => 'Liabilities', 'equity' => 'Equity'] as $key => $name) {
            $rows[] = ['cells' => ['account' => $name], 'style' => 'heading'];
            $priorById = $prior ? collect($prior[$key])->keyBy('id') : collect();
            $ids = collect($current[$key])->pluck('id')->merge($priorById->keys())->unique();
            $byId = collect($current[$key])->keyBy('id');
            foreach ($ids as $id) {
                $account = $byId[$id] ?? $priorById[$id];
                $rows[] = ['cells' => $withChange($account->code.' '.$account->name, (float) ($byId[$id]->balance ?? 0), (float) ($priorById[$id]->balance ?? 0)),
                    'link' => $this->drill($account, $asAt->copy()->startOfYear(), $asAt)];
            }
            if ($key === 'equity') {
                $rows[] = ['cells' => $withChange('Retained earnings (prior years)', (float) $current['prior_year_earnings'], $prior['prior_year_earnings'] ?? null)];
                $rows[] = ['cells' => $withChange('Current year earnings', (float) $current['current_year_earnings'], $prior['current_year_earnings'] ?? null)];
            }
            $rows[] = ['cells' => $withChange('Total '.strtolower($name), (float) $current[$totalKey[$key]], $prior[$totalKey[$key]] ?? null), 'style' => 'subtotal'];
        }
        $rows[] = ['cells' => $withChange('Total liabilities and equity', (float) $current['liability_total'] + (float) $current['equity_total'], $prior ? (float) $prior['liability_total'] + (float) $prior['equity_total'] : null), 'style' => 'total'];

        return ['columns' => $columns, 'rows' => $rows, 'subtitle' => 'As at '.$asAt->format('d M Y'),
            'notes' => abs((float) $current['difference']) < 0.01 ? [] : ['The balance sheet is out of balance by '.number_format((float) $current['difference'], 2).'.']];
    }

    private function trialBalanceReport(Carbon $from, Carbon $to): array
    {
        $rows = $this->trialBalance->between($from, $to)->filter(fn ($a) => $a->debit_total != 0 || $a->credit_total != 0)
            ->map(fn ($a) => ['cells' => ['code' => $a->code, 'account' => $a->name, 'type' => ucfirst($a->type), 'debit' => $a->debit_total, 'credit' => $a->credit_total, 'balance' => $a->balance], 'link' => $this->drill($a, $from, $to)])->values()->all();
        $rows[] = ['cells' => ['account' => 'Totals', 'debit' => round(collect($rows)->sum('cells.debit'), 2), 'credit' => round(collect($rows)->sum('cells.credit'), 2)], 'style' => 'total'];

        return ['columns' => ['code' => ['Code', 'text'], 'account' => ['Account', 'text'], 'type' => ['Type', 'text'], 'debit' => ['Debits', 'money'], 'credit' => ['Credits', 'money'], 'balance' => ['Net movement', 'money']], 'rows' => $rows];
    }

    private function generalLedger(Carbon $from, Carbon $to, ?int $accountId): array
    {
        $columns = ['date' => ['Date', 'date'], 'entry' => ['Entry', 'text'], 'description' => ['Description', 'text'], 'debit' => ['Debit', 'money'], 'credit' => ['Credit', 'money'], 'balance' => ['Balance', 'money']];
        $account = $accountId ? Account::find($accountId) : null;
        if (!$account) {
            return ['columns' => $columns, 'rows' => [], 'notes' => ['Choose an account to see its ledger detail.']];
        }

        $signed = fn ($d, $c) => in_array($account->type, ['asset', 'expense'], true) ? $d - $c : $c - $d;
        $base = JournalLine::query()->join('accounting_journal_entries as e', 'e.id', '=', 'accounting_journal_lines.journal_entry_id')
            ->where('e.status', 'posted')->where('accounting_journal_lines.account_id', $account->id);
        $opening = (clone $base)->whereDate('e.entry_date', '<', $from->toDateString())
            ->selectRaw('SUM(accounting_journal_lines.debit) as d, SUM(accounting_journal_lines.credit) as c')->first();
        $running = in_array($account->type, ['revenue', 'expense'], true) ? 0.0 : round($signed((float) $opening->d, (float) $opening->c), 2);

        $rows = [['cells' => ['description' => in_array($account->type, ['revenue', 'expense'], true) ? 'Opening balance (income statement accounts start at zero)' : 'Opening balance', 'balance' => $running], 'style' => 'subtotal']];
        $lines = (clone $base)->whereDate('e.entry_date', '>=', $from->toDateString())->whereDate('e.entry_date', '<=', $to->toDateString())
            ->orderBy('e.entry_date')->orderBy('e.id')
            ->get(['e.id as entry_id', 'e.entry_date', 'e.entry_number', 'e.memo', 'accounting_journal_lines.description', 'accounting_journal_lines.debit', 'accounting_journal_lines.credit']);
        foreach ($lines as $line) {
            $running = round($running + $signed((float) $line->debit, (float) $line->credit), 2);
            $rows[] = ['cells' => ['date' => Carbon::parse($line->entry_date)->toDateString(), 'entry' => $line->entry_number, 'description' => $line->description ?: $line->memo, 'debit' => (float) $line->debit, 'credit' => (float) $line->credit, 'balance' => $running],
                'link' => route('accounting.gl.journals.show', $line->entry_id)];
        }
        $rows[] = ['cells' => ['description' => 'Closing balance', 'debit' => round($lines->sum('debit'), 2), 'credit' => round($lines->sum('credit'), 2), 'balance' => $running], 'style' => 'total'];

        return ['columns' => $columns, 'rows' => $rows, 'subtitle' => $account->code.' '.$account->name.' · '.$from->format('d M Y').' – '.$to->format('d M Y')];
    }

    private function budgetVsActual(Carbon $from, Carbon $to): array
    {
        $budgets = Budget::with(['account', 'period'])
            ->whereHas('period', fn ($q) => $q->whereDate('starts_on', '<=', $to->toDateString())->whereDate('ends_on', '>=', $from->toDateString()))
            ->get()->groupBy('account_id');
        $actuals = $this->trialBalance->between($from, $to)->keyBy('id');
        $rows = [];
        foreach ($budgets as $accountId => $items) {
            $account = $items->first()->account;
            $budget = round((float) $items->sum('amount'), 2);
            $actual = round((float) ($actuals[$accountId]->balance ?? 0), 2);
            $favourable = $account->type === 'revenue' ? $actual >= $budget : $actual <= $budget;
            $rows[] = ['cells' => ['account' => $account->code.' '.$account->name, 'budget' => $budget, 'actual' => $actual, 'variance' => round($actual - $budget, 2),
                'variance_pct' => $budget != 0.0 ? round(($actual - $budget) / abs($budget) * 100, 1) : null, 'status' => $favourable ? 'Favourable' : 'Adverse'],
                'link' => $this->drill($account, $from, $to)];
        }

        return ['columns' => ['account' => ['Account', 'text'], 'budget' => ['Budget', 'money'], 'actual' => ['Actual', 'money'], 'variance' => ['Variance', 'money'], 'variance_pct' => ['Var %', 'percent'], 'status' => ['', 'text']],
            'rows' => $rows, 'notes' => $rows ? [] : ['No budgets overlap this range. Add budgets under General Ledger → Budgets.']];
    }

    private function expenseAnalysis(Carbon $from, Carbon $to, string $compare): array
    {
        $current = $this->trialBalance->between($from, $to)->where('type', 'expense')->filter(fn ($a) => $a->balance != 0);
        $cmp = self::comparison($from, $to, $compare === 'none' ? 'previous_period' : $compare);
        $prior = $this->trialBalance->between($cmp[0], $cmp[1])->keyBy('id');
        $total = max(0.01, (float) $current->sum('balance'));
        $rows = $current->sortByDesc('balance')->map(fn ($a) => ['cells' => [
            'account' => $a->code.' '.$a->name, 'amount' => (float) $a->balance, 'share' => round($a->balance / $total * 100, 1),
            'prior' => (float) ($prior[$a->id]->balance ?? 0),
            'change_pct' => (float) ($prior[$a->id]->balance ?? 0) != 0.0 ? round(($a->balance - $prior[$a->id]->balance) / abs($prior[$a->id]->balance) * 100, 1) : null,
        ], 'link' => $this->drill($a, $from, $to)])->values()->all();
        $rows[] = ['cells' => ['account' => 'Total expenses', 'amount' => round((float) $current->sum('balance'), 2), 'share' => 100.0, 'prior' => round((float) collect($rows)->sum('cells.prior'), 2)], 'style' => 'total'];

        return ['columns' => ['account' => ['Account', 'text'], 'amount' => ['Amount', 'money'], 'share' => ['Share', 'percent'], 'prior' => [$cmp[2], 'money'], 'change_pct' => ['Change', 'percent']], 'rows' => $rows];
    }

    /** Per customer: invoices, net, tax, gross, received, outstanding (base currency). */
    public function salesByCustomer(Carbon $from, Carbon $to): Collection
    {
        return SalesInvoice::with(['customer', 'journalEntry'])
            ->whereNotIn('status', ['draft', 'void', 'cancelled'])
            ->whereDate('invoice_date', '>=', $from->toDateString())->whereDate('invoice_date', '<=', $to->toDateString())
            ->get()->groupBy('customer_id')
            ->map(function ($invoices) {
                $rate = fn ($i) => (float) ($i->journalEntry?->exchange_rate ?: 1);

                return [
                    'customer_id' => $invoices->first()->customer_id,
                    'customer' => $invoices->first()->customer?->name ?? 'Unknown',
                    'invoices' => $invoices->count(),
                    'net' => round($invoices->sum(fn ($i) => (float) $i->subtotal * $rate($i)), 2),
                    'tax' => round($invoices->sum(fn ($i) => (float) $i->tax_total * $rate($i)), 2),
                    'gross' => round($invoices->sum(fn ($i) => (float) $i->total * $rate($i)), 2),
                    'paid' => round($invoices->sum(fn ($i) => (float) $i->amount_paid * $rate($i)), 2),
                    'outstanding' => round($invoices->sum(fn ($i) => ((float) $i->total - (float) $i->amount_paid) * $rate($i)), 2),
                ];
            })->values();
    }

    private function salesByCustomerReport(Carbon $from, Carbon $to): array
    {
        $data = $this->salesByCustomer($from, $to)->sortByDesc('net');
        $rows = $data->map(fn ($r) => ['cells' => ['customer' => $r['customer'], 'invoices' => $r['invoices'], 'net' => $r['net'], 'tax' => $r['tax'], 'gross' => $r['gross'], 'paid' => $r['paid'], 'outstanding' => $r['outstanding']]])->values()->all();
        $rows[] = ['cells' => ['customer' => 'Total'] + collect(['invoices', 'net', 'tax', 'gross', 'paid', 'outstanding'])->mapWithKeys(fn ($k) => [$k => round($data->sum($k), 2)])->all(), 'style' => 'total'];

        return ['columns' => ['customer' => ['Customer', 'text'], 'invoices' => ['Invoices', 'number'], 'net' => ['Net sales', 'money'], 'tax' => ['Tax', 'money'], 'gross' => ['Gross', 'money'], 'paid' => ['Received', 'money'], 'outstanding' => ['Outstanding', 'money']],
            'rows' => $rows, 'notes' => ['Sales invoices only; POS sales are included in revenue reports but not by customer.']];
    }

    private function salesByMonth(Carbon $from, Carbon $to): array
    {
        $rows = collect($this->monthly($from, $to))->map(fn ($m) => ['cells' => [
            'month' => $m['label'], 'revenue' => round($m['revenue'], 2), 'cost_of_sales' => round($m['cost_of_sales'], 2),
            'gross' => round($m['revenue'] - $m['cost_of_sales'], 2), 'margin' => $m['revenue'] != 0.0 ? round(($m['revenue'] - $m['cost_of_sales']) / $m['revenue'] * 100, 1) : null,
            'expenses' => round($m['expenses'], 2), 'net' => $m['net'],
        ]])->values()->all();
        $sum = fn ($k) => round(collect($rows)->sum('cells.'.$k), 2);
        $rows[] = ['cells' => ['month' => 'Total', 'revenue' => $sum('revenue'), 'cost_of_sales' => $sum('cost_of_sales'), 'gross' => $sum('gross'),
            'margin' => $sum('revenue') != 0.0 ? round($sum('gross') / $sum('revenue') * 100, 1) : null, 'expenses' => $sum('expenses'), 'net' => $sum('net')], 'style' => 'total'];

        return ['columns' => ['month' => ['Month', 'text'], 'revenue' => ['Revenue', 'money'], 'cost_of_sales' => ['Cost of sales', 'money'], 'gross' => ['Gross profit', 'money'], 'margin' => ['Margin', 'percent'], 'expenses' => ['Operating expenses', 'money'], 'net' => ['Net profit', 'money']], 'rows' => $rows];
    }

    private function agingReport(string $side, Carbon $asAt): array
    {
        $aging = $this->aging($side, $asAt);
        $rows = $aging['rows']->groupBy('party')->map(function ($items, $party) {
            $cells = ['party' => $party ?: 'Unknown'] + array_fill_keys(array_keys(self::AGING_BUCKETS), 0.0);
            foreach ($items as $item) {
                $cells[$item['bucket']] += $item['base'];
            }
            $cells['total'] = round($items->sum('base'), 2);

            return ['cells' => array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $cells)];
        })->sortByDesc('cells.total')->values()->all();
        $rows[] = ['cells' => ['party' => 'Total'] + $aging['buckets'] + ['total' => round(array_sum($aging['buckets']), 2)], 'style' => 'total'];
        $columns = ['party' => [$side === 'ap' ? 'Supplier' : 'Customer', 'text']];
        foreach (self::AGING_BUCKETS as $key => $label) {
            $columns[$key] = [$label, 'money'];
        }

        return ['columns' => $columns + ['total' => ['Total', 'money']], 'rows' => $rows, 'subtitle' => 'As at '.$asAt->format('d M Y')];
    }

    private function inventoryValuation(): array
    {
        $balances = $this->stock->balances()->filter(fn ($r) => (float) $r->quantity_on_hand != 0);
        $rows = $balances->map(fn ($r) => ['cells' => ['sku' => $r->item?->sku, 'item' => $r->item?->name, 'warehouse' => $r->warehouse?->name, 'quantity' => (float) $r->quantity_on_hand, 'cost' => (float) $r->average_cost, 'value' => (float) $r->stock_value]])->values()->all();
        $rows[] = ['cells' => ['item' => 'Total', 'value' => round($balances->sum('stock_value'), 2)], 'style' => 'total'];

        return ['columns' => ['sku' => ['SKU', 'text'], 'item' => ['Item', 'text'], 'warehouse' => ['Warehouse', 'text'], 'quantity' => ['On hand', 'number'], 'cost' => ['Average cost', 'money'], 'value' => ['Value', 'money']], 'rows' => $rows];
    }

    private function jobProfitability(): array
    {
        $jobs = JobCost::with('customer')->orderByDesc('id')->get();
        $rows = $jobs->map(function (JobCost $job) {
            $revenue = (float) $job->invoiced_amount;
            $cost = (float) $job->actual_cost;

            return ['cells' => [
                'job' => $job->job_number.' '.$job->name, 'customer' => $job->customer?->name, 'status' => str_replace('_', ' ', ucfirst($job->status)),
                'budget' => (float) $job->estimated_cost, 'actual' => $cost, 'budget_used' => (float) $job->estimated_cost > 0 ? round($cost / (float) $job->estimated_cost * 100, 1) : null,
                'invoiced' => $revenue, 'margin' => round($revenue - $cost, 2), 'margin_pct' => $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 1) : null,
            ], 'link' => route('accounting.bom-job-costing.jobs.show', $job)];
        })->all();
        $rows[] = ['cells' => ['job' => 'Total', 'budget' => round($jobs->sum('estimated_cost'), 2), 'actual' => round($jobs->sum('actual_cost'), 2), 'invoiced' => round($jobs->sum('invoiced_amount'), 2), 'margin' => round($jobs->sum('invoiced_amount') - $jobs->sum('actual_cost'), 2)], 'style' => 'total'];

        return ['columns' => ['job' => ['Job', 'text'], 'customer' => ['Customer', 'text'], 'status' => ['Status', 'text'], 'budget' => ['Budget', 'money'], 'actual' => ['Actual cost', 'money'], 'budget_used' => ['Budget used', 'percent'], 'invoiced' => ['Invoiced', 'money'], 'margin' => ['Margin', 'money'], 'margin_pct' => ['Margin %', 'percent']], 'rows' => $rows];
    }

    private function recurringRevenue(): array
    {
        $contracts = Subscription::with('customer')->whereIn('status', ['active', 'paused'])->orderBy('contract_number')->get();
        $rows = $contracts->map(fn (Subscription $s) => ['cells' => [
            'contract' => $s->contract_number, 'customer' => $s->customer?->name, 'cycle' => ucfirst($s->billing_cycle), 'status' => ucfirst($s->status),
            'amount' => (float) $s->recurring_amount, 'currency' => $s->currency, 'mrr' => $s->monthlyValueInBase(), 'arr' => round($s->monthlyValueInBase() * 12, 2),
            'next' => $s->next_invoice_on?->toDateString(), 'ends' => $s->ends_on?->toDateString(),
        ], 'link' => route('accounting.annuity-billing.contracts.show', $s)])->all();
        $active = $contracts->where('status', 'active');
        $rows[] = ['cells' => ['contract' => 'Total active', 'mrr' => round($active->sum(fn ($s) => $s->monthlyValueInBase()), 2), 'arr' => round($active->sum(fn ($s) => $s->monthlyValueInBase()) * 12, 2)], 'style' => 'total'];

        return ['columns' => ['contract' => ['Contract', 'text'], 'customer' => ['Customer', 'text'], 'cycle' => ['Billing', 'text'], 'status' => ['Status', 'text'], 'amount' => ['Per cycle', 'money'], 'currency' => ['Cur.', 'text'], 'mrr' => ['MRR (base)', 'money'], 'arr' => ['ARR (base)', 'money'], 'next' => ['Next invoice', 'date'], 'ends' => ['Term ends', 'date']], 'rows' => $rows];
    }

    // ---- helpers ------------------------------------------------------------------

    private function mappedBalance(string $key, Collection $balances): float
    {
        try {
            return round((float) ($balances[$this->mappings->account($key, $this->companyId())->id]->balance ?? 0), 2);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function rateFor(?string $currency, mixed $date): float
    {
        try {
            return $currency ? $this->rates->rate($currency, $this->companyId(), $date) : 1.0;
        } catch (\Throwable) {
            return 1.0;
        }
    }

    public static function customers(): Collection
    {
        return Customer::orderBy('name')->get(['id', 'name']);
    }
}
