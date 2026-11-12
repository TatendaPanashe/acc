<?php

namespace App\Modules\Accounting\Services;

use App\Models\CompanyBankAccount;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BankStatement;
use App\Modules\Accounting\Models\BankStatementLine;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bank reconciliation: import a statement, match its lines to posted GL lines on the
 * bank's GL account, post entries for bank-only items (charges, interest) and complete.
 *
 * Amounts are compared in the bank account's currency: base-currency banks use debit/credit,
 * foreign-currency banks use the transaction amounts (fx_debit/fx_credit) of entries in that currency.
 */
class BankReconciliationService
{
    public const MATCH_WINDOW_DAYS = 7;

    public function __construct(
        private readonly JournalPostingService $journalPosting,
        private readonly ExchangeRateService $rates,
    ) {
    }

    /**
     * Parses a CSV with a header row. Recognised columns (any order, case-insensitive):
     * date; description/details/narrative; reference/ref; and either amount (+in/-out)
     * or credit/deposit/money in and debit/withdrawal/money out.
     */
    public function import(CompanyBankAccount $bank, string $csvPath, string $statementDate, float $opening, float $closing): BankStatement
    {
        $this->glAccount($bank);
        $rows = $this->parseCsv($csvPath);

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'No transactions were found in the file.']);
        }

        return DB::transaction(function () use ($bank, $rows, $statementDate, $opening, $closing) {
            $statement = BankStatement::create([
                'company_bank_account_id' => $bank->id,
                'bank_account_name' => $bank->label(),
                'format' => 'CSV',
                'statement_date' => $statementDate,
                'opening_balance' => $opening,
                'closing_balance' => $closing,
                'status' => 'imported',
            ]);

            foreach ($rows as $row) {
                $statement->lines()->create($row + ['company_id' => $statement->company_id]);
            }

            return $statement;
        });
    }

    public function glAccount(CompanyBankAccount $bank): Account
    {
        $account = $bank->gl_account_id ? Account::find($bank->gl_account_id) : null;

        if (!$account) {
            throw ValidationException::withMessages(['bank' => 'Link '.$bank->label().' to a GL account before reconciling it.']);
        }

        return $account;
    }

    /** Posted GL lines on the bank account that are not matched to any statement line. */
    public function unmatchedGlLines(BankStatement $statement): Builder
    {
        $bank = $statement->bankAccount;
        $isBase = strtoupper($bank->currency) === $this->rates->baseCurrency($statement->company_id);

        return JournalLine::query()
            ->with('journalEntry')
            ->where('account_id', $this->glAccount($bank)->id)
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')
                ->when(!$isBase, fn ($q) => $q->where('currency', strtoupper($bank->currency))))
            ->whereNotIn('id', BankStatementLine::whereNotNull('journal_line_id')->select('journal_line_id'));
    }

    /** Signed amount of a GL line in the bank's currency (+ = money in). */
    public function glAmount(JournalLine $line, BankStatement $statement): float
    {
        $isBase = strtoupper($statement->bankAccount->currency) === $this->rates->baseCurrency($statement->company_id);

        return $isBase
            ? round((float) $line->debit - (float) $line->credit, 2)
            : round((float) $line->fx_debit - (float) $line->fx_credit, 2);
    }

    public function candidates(BankStatementLine $line): Collection
    {
        $statement = $line->statement;
        $from = $line->transaction_date->copy()->subDays(self::MATCH_WINDOW_DAYS)->toDateString();
        $to = $line->transaction_date->copy()->addDays(self::MATCH_WINDOW_DAYS)->toDateString();

        return $this->unmatchedGlLines($statement)
            ->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$from, $to]))
            ->get()
            ->filter(fn (JournalLine $gl) => abs($this->glAmount($gl, $statement) - (float) $line->amount) < 0.005)
            ->sortBy(fn (JournalLine $gl) => abs($gl->journalEntry->entry_date->diffInDays($line->transaction_date)))
            ->values();
    }

    /** Matches every statement line that has exactly one candidate. Returns the number matched. */
    public function autoMatch(BankStatement $statement): int
    {
        $this->assertOpen($statement);
        $matched = 0;

        DB::transaction(function () use ($statement, &$matched) {
            foreach ($statement->lines()->whereNull('journal_line_id')->get() as $line) {
                $candidates = $this->candidates($line);
                if ($candidates->count() === 1) {
                    $line->update(['journal_line_id' => $candidates->first()->id]);
                    $matched++;
                }
            }
            $this->refreshStatus($statement);
        });

        return $matched;
    }

    public function match(BankStatementLine $line, int $journalLineId): void
    {
        $this->assertOpen($line->statement);

        if (!$this->candidates($line)->contains('id', $journalLineId)) {
            throw ValidationException::withMessages(['journal_line_id' => 'That GL line is not an unmatched entry for the same amount within '.self::MATCH_WINDOW_DAYS.' days.']);
        }

        $line->update(['journal_line_id' => $journalLineId]);
        $this->refreshStatus($line->statement);
    }

    public function unmatch(BankStatementLine $line): void
    {
        $this->assertOpen($line->statement);
        $line->update(['journal_line_id' => null]);
        $this->refreshStatus($line->statement);
    }

    /** Posts a journal for a bank-only item (charges, interest, direct debits) and matches it. */
    public function createEntry(BankStatementLine $line, Account $counterAccount, ?string $memo = null): JournalEntry
    {
        $statement = $line->statement;
        $this->assertOpen($statement);
        if ($line->journal_line_id) {
            throw ValidationException::withMessages(['line' => 'This statement line is already matched.']);
        }

        return DB::transaction(function () use ($line, $statement, $counterAccount, $memo) {
            $bankAccount = $this->glAccount($statement->bankAccount);
            $amount = abs((float) $line->amount);
            $moneyIn = (float) $line->amount > 0;

            $entry = new JournalEntry([
                'entry_number' => 'BNK-'.$statement->id.'-'.$line->id,
                'entry_date' => $line->transaction_date,
                'source_module' => 'banking',
                'source_type' => BankStatementLine::class,
                'source_id' => $line->id,
                'currency' => strtoupper($statement->bankAccount->currency),
                'memo' => $memo ?: $line->description,
                'created_by' => auth()->id(),
            ]);
            $entry->company_id = $statement->company_id;
            $entry->save();

            // Amounts in the bank's currency; the posting service converts to base.
            $bankLine = $entry->lines()->create([
                'company_id' => $entry->company_id, 'account_id' => $bankAccount->id, 'description' => $line->description,
                'debit' => $moneyIn ? $amount : 0, 'credit' => $moneyIn ? 0 : $amount,
            ]);
            $entry->lines()->create([
                'company_id' => $entry->company_id, 'account_id' => $counterAccount->id, 'description' => $line->description,
                'debit' => $moneyIn ? 0 : $amount, 'credit' => $moneyIn ? $amount : 0,
            ]);

            $this->journalPosting->post($entry);
            $line->update(['journal_line_id' => $bankLine->id]);
            $this->refreshStatus($statement);

            return $entry->fresh('lines');
        });
    }

    /**
     * @return array{statement_movement: float, statement_check: float, gl_balance: float,
     *               unpresented: Collection, unpresented_total: float, unmatched_total: float, difference: float}
     */
    public function summary(BankStatement $statement): array
    {
        $date = $statement->statement_date->toDateString();
        $movement = round((float) $statement->lines->sum('amount'), 2);

        $glLines = JournalLine::with('journalEntry')
            ->where('account_id', $this->glAccount($statement->bankAccount)->id)
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->whereDate('entry_date', '<=', $date))
            ->get();
        $glBalance = round($glLines->sum(fn ($gl) => $this->glAmount($gl, $statement)), 2);

        $unpresented = $this->unmatchedGlLines($statement)
            ->whereHas('journalEntry', fn ($q) => $q->whereDate('entry_date', '<=', $date))
            ->get();
        $unpresentedTotal = round($unpresented->sum(fn ($gl) => $this->glAmount($gl, $statement)), 2);
        $unmatchedTotal = round((float) $statement->lines->whereNull('journal_line_id')->sum('amount'), 2);

        // Cash book adjusted for items not yet on the statement must equal the statement adjusted for items not yet in the books.
        $difference = round(($glBalance - $unpresentedTotal) - ((float) $statement->closing_balance - $unmatchedTotal), 2);

        return [
            'statement_movement' => $movement,
            'statement_check' => round((float) $statement->opening_balance + $movement - (float) $statement->closing_balance, 2),
            'gl_balance' => $glBalance,
            'unpresented' => $unpresented,
            'unpresented_total' => $unpresentedTotal,
            'unmatched_total' => $unmatchedTotal,
            'difference' => $difference,
        ];
    }

    public function complete(BankStatement $statement): void
    {
        $this->assertOpen($statement);
        $statement->load('lines');
        $summary = $this->summary($statement);

        if ($summary['statement_check'] != 0.0) {
            throw ValidationException::withMessages(['statement' => 'Opening balance plus transactions does not equal the closing balance (difference '.number_format($summary['statement_check'], 2).').']);
        }
        if ($statement->lines->whereNull('journal_line_id')->isNotEmpty()) {
            throw ValidationException::withMessages(['statement' => 'Match or post every statement line before completing the reconciliation.']);
        }
        if ($summary['difference'] != 0.0) {
            throw ValidationException::withMessages(['statement' => 'The reconciliation is out by '.number_format($summary['difference'], 2).'.']);
        }

        $statement->update(['status' => 'reconciled', 'reconciled_at' => now(), 'reconciled_by' => auth()->id()]);
    }

    private function refreshStatus(BankStatement $statement): void
    {
        $hasUnmatched = $statement->lines()->whereNull('journal_line_id')->exists();
        $statement->update(['status' => $hasUnmatched ? 'imported' : 'matched']);
    }

    private function assertOpen(BankStatement $statement): void
    {
        if ($statement->isReconciled()) {
            throw ValidationException::withMessages(['statement' => 'This statement has already been reconciled.']);
        }
    }

    /** @return array<int, array{transaction_date: string, description: string, reference: ?string, amount: float}> */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            throw ValidationException::withMessages(['file' => 'The file could not be read.']);
        }

        $header = array_map(fn ($h) => Str::of((string) $h)->lower()->replaceMatches('/[^a-z ]/', '')->trim()->value(), fgetcsv($handle) ?: []);
        $find = function (array $names) use ($header) {
            foreach ($header as $index => $name) {
                if (in_array($name, $names, true)) {
                    return $index;
                }
            }

            return null;
        };

        $date = $find(['date', 'transaction date', 'value date', 'posting date']);
        $description = $find(['description', 'details', 'narrative', 'particulars', 'transaction details']);
        $reference = $find(['reference', 'ref', 'cheque number', 'reference number']);
        $amount = $find(['amount', 'net amount']);
        $in = $find(['credit', 'deposit', 'money in', 'credits', 'paid in']);
        $out = $find(['debit', 'withdrawal', 'money out', 'debits', 'paid out']);

        if ($date === null || $description === null || ($amount === null && $in === null && $out === null)) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'The CSV needs a header row with Date, Description and either Amount or Credit/Debit columns.']);
        }

        $number = fn ($value) => (float) str_replace([',', ' '], '', (string) $value);
        $rows = [];
        $lineNo = 1;

        while (($cells = fgetcsv($handle)) !== false) {
            $lineNo++;
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }

            $value = $amount !== null
                ? $number($cells[$amount] ?? 0)
                : $number($cells[$in] ?? 0) - $number($cells[$out] ?? 0);

            try {
                $parsedDate = $this->parseDate((string) ($cells[$date] ?? ''));
            } catch (\Throwable) {
                fclose($handle);
                throw ValidationException::withMessages(['file' => "Row {$lineNo}: unrecognised date \"".($cells[$date] ?? '').'".']);
            }

            if (round($value, 2) == 0.0) {
                continue;
            }

            $rows[] = [
                'transaction_date' => $parsedDate,
                'description' => Str::limit(trim((string) ($cells[$description] ?? '')), 250, '') ?: 'Bank transaction',
                'reference' => $reference !== null ? (trim((string) ($cells[$reference] ?? '')) ?: null) : null,
                'amount' => round($value, 2),
            ];
        }

        fclose($handle);

        return $rows;
    }

    /** Accepts ISO dates and day-first dates (31/01/2026, 31-01-2026) as used by local banks. */
    private function parseDate(string $value): string
    {
        $value = trim($value);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'd/m/y', 'd M Y', 'j M Y'] as $format) {
            $date = \DateTime::createFromFormat('!'.$format, $value);
            if ($date && $date->format($format) === $value) {
                return Carbon::instance($date)->toDateString();
            }
        }

        return Carbon::parse($value)->toDateString();
    }
}
