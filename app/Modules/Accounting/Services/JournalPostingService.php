<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only way a journal entry becomes "posted". Enforces:
 *  - row lock so the same entry cannot be posted twice concurrently
 *  - the entry date falls in an open period of an open fiscal year (period is assigned automatically)
 *  - every line uses an active, postable account of the same company
 *  - each line is either a debit or a credit, and the entry balances
 *  - amounts are converted to the base currency (debit/credit) with the transaction
 *    amount kept in fx_debit/fx_credit
 *
 * Lines created without fx amounts (manual journals) are treated as transaction-currency
 * amounts and converted at the rate on the entry date. Lines that already carry fx amounts
 * (automatic postings) are taken as converted.
 */
class JournalPostingService
{
    public function __construct(
        private readonly AuditTrailService $auditTrail,
        private readonly PeriodService $periods,
        private readonly ExchangeRateService $rates,
        private readonly AccountMappingService $mappings,
    ) {
    }

    public function post(JournalEntry $entry): JournalEntry
    {
        return DB::transaction(function () use ($entry) {
            $entry = JournalEntry::withoutGlobalScopes()->lockForUpdate()->findOrFail($entry->getKey());

            if ($entry->status === 'posted') {
                return $entry->load('lines');
            }

            $entry->load(['lines.account' => fn ($query) => $query->withoutGlobalScopes()]);

            $period = $this->periods->openPeriodFor($entry->entry_date, $entry->company_id);

            if ($entry->accounting_period_id && (int) $entry->accounting_period_id !== $period->id) {
                throw ValidationException::withMessages([
                    'accounting_period_id' => 'The entry date '.$entry->entry_date->format('d M Y').' falls in period '.$period->name.', not the period selected on the journal.',
                ]);
            }

            $this->validateLines($entry);
            $this->convertToBase($entry);
            $this->assertBalanced($entry);

            $oldValues = $entry->only(['status', 'posted_by', 'posted_at', 'accounting_period_id']);
            $entry->update([
                'status' => 'posted',
                'accounting_period_id' => $period->id,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            $this->auditTrail->record('journal.posted', $entry, $oldValues, $entry->only(['status', 'posted_by', 'posted_at', 'accounting_period_id']));

            return $entry->fresh('lines');
        });
    }

    private function validateLines(JournalEntry $entry): void
    {
        if ($entry->lines->count() < 2) {
            throw ValidationException::withMessages(['lines' => 'A journal entry needs at least two lines.']);
        }

        foreach ($entry->lines as $index => $line) {
            $number = $index + 1;
            $debit = (float) $line->debit;
            $credit = (float) $line->credit;

            if ($debit < 0 || $credit < 0) {
                throw ValidationException::withMessages(['lines' => "Line {$number}: amounts cannot be negative."]);
            }

            if (($debit > 0) === ($credit > 0)) {
                throw ValidationException::withMessages(['lines' => "Line {$number}: enter either a debit or a credit, not both or neither."]);
            }

            /** @var Account|null $account */
            $account = $line->account;

            if (!$account || (int) $account->company_id !== (int) $entry->company_id) {
                throw ValidationException::withMessages(['lines' => "Line {$number}: the account does not belong to this company."]);
            }

            if (!$account->status || !$account->allow_posting) {
                throw ValidationException::withMessages(['lines' => "Line {$number}: account {$account->code} {$account->name} is inactive or does not allow posting."]);
            }
        }
    }

    private function convertToBase(JournalEntry $entry): void
    {
        $pending = $entry->lines->filter(fn ($line) => $line->fx_debit === null && $line->fx_credit === null);

        if ($pending->isEmpty()) {
            return;
        }

        if ($pending->count() !== $entry->lines->count()) {
            throw ValidationException::withMessages(['lines' => 'Journal lines mix converted and unconverted amounts.']);
        }

        // The entry must balance in its own currency before conversion.
        $this->assertBalanced($entry);

        $currency = strtoupper($entry->currency ?: $this->rates->baseCurrency($entry->company_id));
        $rate = $this->rates->rate($currency, $entry->company_id, $entry->entry_date);

        foreach ($entry->lines as $line) {
            $fxDebit = (float) $line->debit;
            $fxCredit = (float) $line->credit;
            $line->update([
                'fx_debit' => $fxDebit,
                'fx_credit' => $fxCredit,
                'debit' => $this->rates->toBase($fxDebit, $rate),
                'credit' => $this->rates->toBase($fxCredit, $rate),
            ]);
        }

        $entry->update(['currency' => $currency, 'exchange_rate' => $rate]);
        $this->absorbRounding($entry);
        $entry->load(['lines.account' => fn ($query) => $query->withoutGlobalScopes()]);
    }

    /** Conversion can leave a cent or two of imbalance; post it to exchange gains/losses. */
    public function absorbRounding(JournalEntry $entry): void
    {
        $entry->load('lines');
        $difference = round($entry->lines->sum(fn ($l) => (float) $l->debit) - $entry->lines->sum(fn ($l) => (float) $l->credit), 2);

        if ($difference == 0.0) {
            return;
        }

        if (abs($difference) > max(0.05, 0.01 * $entry->lines->count())) {
            throw ValidationException::withMessages(['lines' => 'Base-currency amounts are out of balance by '.number_format($difference, 2).'.']);
        }

        $account = $this->mappings->account($difference > 0 ? 'fx_gain' : 'fx_loss', $entry->company_id);
        $entry->lines()->create([
            'company_id' => $entry->company_id,
            'account_id' => $account->id,
            'description' => 'Currency rounding',
            'debit' => $difference < 0 ? abs($difference) : 0,
            'credit' => $difference > 0 ? $difference : 0,
            'fx_debit' => 0,
            'fx_credit' => 0,
        ]);
    }

    private function assertBalanced(JournalEntry $entry): void
    {
        $debits = (int) round($entry->lines->sum(fn ($line) => (float) $line->debit) * 100);
        $credits = (int) round($entry->lines->sum(fn ($line) => (float) $line->credit) * 100);

        if ($debits <= 0 || $debits !== $credits) {
            throw ValidationException::withMessages([
                'lines' => sprintf('Debits (%s) and credits (%s) must be equal.', number_format($debits / 100, 2), number_format($credits / 100, 2)),
            ]);
        }
    }
}
