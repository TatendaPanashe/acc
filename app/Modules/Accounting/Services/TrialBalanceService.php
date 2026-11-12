<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\JournalLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Account balances from posted journal lines, by entry date.
 *
 * `balance` uses the statement sign for the account type:
 *   asset, expense            => debit - credit
 *   liability, equity, revenue => credit - debit
 * so contra accounts (e.g. accumulated depreciation) show as negative within their section.
 */
class TrialBalanceService
{
    /** Cumulative balances as at the end of the period (or today). */
    public function forPeriod(?AccountingPeriod $period = null): Collection
    {
        return $this->between(null, $period ? $period->ends_on : now());
    }

    /** Totals of posted lines dated from $from (inclusive, null = beginning) to $to (inclusive). */
    public function between(?Carbon $from, Carbon $to): Collection
    {
        $sums = JournalLine::query()
            ->join('accounting_journal_entries as entries', 'entries.id', '=', 'accounting_journal_lines.journal_entry_id')
            ->where('entries.status', 'posted')
            ->whereDate('entries.entry_date', '<=', $to->toDateString())
            ->when($from, fn ($query) => $query->whereDate('entries.entry_date', '>=', $from->toDateString()))
            ->groupBy('accounting_journal_lines.account_id')
            ->selectRaw('accounting_journal_lines.account_id, SUM(accounting_journal_lines.debit) as debit_total, SUM(accounting_journal_lines.credit) as credit_total')
            ->get()
            ->keyBy('account_id');

        return Account::orderBy('code')->get()->map(function (Account $account) use ($sums) {
            $debit = round((float) ($sums[$account->id]->debit_total ?? 0), 2);
            $credit = round((float) ($sums[$account->id]->credit_total ?? 0), 2);

            $account->debit_total = $debit;
            $account->credit_total = $credit;
            $account->balance = self::signed($account->type, $debit, $credit);

            return $account;
        });
    }

    public static function signed(string $type, float $debit, float $credit): float
    {
        return round(in_array($type, ['asset', 'expense'], true) ? $debit - $credit : $credit - $debit, 2);
    }
}
