<?php

namespace App\Modules\Accounting\Services\Ai;

use App\Modules\Accounting\Models\AiInsight;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Models\SupplierInvoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Finds transactions worth a second look. Each finding is stored once (by fingerprint); findings a
 * person has dismissed or confirmed are never reopened.
 *
 *  - unusual_amount:    expense lines whose robust z-score against the account's history exceeds 3.5
 *                       (z = 0.6745 × (x − median) / MAD, needs 8+ earlier lines)
 *  - duplicate_supplier_invoice: same supplier and total within 14 days, or the same invoice number
 *  - round_amount_journal: manual journals of 1,000 or more in exact thousands
 *  - weekend_posting:   manual journals posted on a Saturday or Sunday
 *  - self_posted_journal: manual journals posted by the person who prepared them
 *  - benford_deviation: first digits of supplier invoice totals vs Benford's law (MAD > 0.015, 50+ invoices)
 */
class AnomalyDetectionService
{
    private const LOOKBACK_DAYS = 120;

    public function detect(?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $since = $asOf->copy()->subDays(self::LOOKBACK_DAYS);
        $found = collect()
            ->merge($this->unusualAmounts($since, $asOf))
            ->merge($this->duplicateSupplierInvoices($since))
            ->merge($this->manualJournalChecks($since))
            ->merge($this->benford());

        $created = 0;
        $companyId = (int) auth()->user()->company_id;
        foreach ($found as $finding) {
            $fingerprint = hash('sha256', $finding['type'].'|'.($finding['key'] ?? ($finding['subject_type'] ?? '').':'.($finding['subject_id'] ?? '')));
            unset($finding['key']);
            $insight = AiInsight::firstOrNew(['fingerprint' => $fingerprint]);
            if (!$insight->exists) {
                $insight->fill($finding + ['status' => 'open']);
                $insight->company_id = $companyId;
                $insight->save();
                $created++;
            } elseif ($insight->status === 'open') {
                $insight->update(array_intersect_key($finding, array_flip(['score', 'severity', 'details', 'title'])));
            }
        }

        return ['checked_since' => $since->toDateString(), 'found' => $found->count(), 'new' => $created];
    }

    private function unusualAmounts(Carbon $since, Carbon $asOf): Collection
    {
        $lines = JournalLine::query()
            ->join('accounting_journal_entries as e', 'e.id', '=', 'accounting_journal_lines.journal_entry_id')
            ->join('accounting_accounts as a', 'a.id', '=', 'accounting_journal_lines.account_id')
            ->where('e.status', 'posted')->where('a.type', 'expense')->where('accounting_journal_lines.debit', '>', 0)
            ->whereDate('e.entry_date', '<=', $asOf->toDateString())
            ->orderBy('e.entry_date')->orderBy('accounting_journal_lines.id')
            ->get(['accounting_journal_lines.id', 'accounting_journal_lines.debit', 'accounting_journal_lines.description', 'e.id as entry_id', 'e.entry_number', 'e.entry_date', 'a.id as account_id', 'a.code', 'a.name'])
            ->groupBy('account_id');

        $findings = collect();
        foreach ($lines as $accountLines) {
            $history = [];
            foreach ($accountLines as $line) {
                $amount = (float) $line->debit;
                if (count($history) >= 8 && Carbon::parse($line->entry_date)->gte($since)) {
                    $median = self::median($history);
                    $mad = self::median(array_map(fn ($v) => abs($v - $median), $history));
                    $z = $mad > 0 ? 0.6745 * ($amount - $median) / $mad : ($amount > $median * 3 && $median > 0 ? 10.0 : 0.0);
                    if ($z > 3.5) {
                        $findings->push([
                            'type' => 'unusual_amount', 'severity' => $z > 8 ? 'high' : ($z > 5 ? 'medium' : 'low'), 'score' => round(min($z, 99999), 2),
                            'title' => number_format($amount, 2).' to '.$line->code.' '.$line->name.' is '.round($amount / max($median, 0.01), 1).'× the usual amount',
                            'subject_type' => JournalEntry::class, 'subject_id' => $line->entry_id, 'subject_label' => $line->entry_number, 'amount' => $amount,
                            'details' => ['account' => $line->code.' '.$line->name, 'median' => round($median, 2), 'robust_z' => round($z, 2), 'history' => count($history), 'description' => $line->description, 'date' => Carbon::parse($line->entry_date)->toDateString()],
                            'key' => 'line:'.$line->id,
                        ]);
                    }
                }
                $history[] = $amount;
            }
        }

        return $findings;
    }

    private function duplicateSupplierInvoices(Carbon $since): Collection
    {
        $invoices = SupplierInvoice::with('supplier')->where('status', '!=', 'void')->whereDate('invoice_date', '>=', $since->copy()->subDays(14)->toDateString())->orderBy('invoice_date')->get();
        $findings = collect();
        $normal = fn ($n) => preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $n));

        foreach ($invoices->groupBy('supplier_id') as $group) {
            $list = $group->values();
            for ($i = 0; $i < $list->count(); $i++) {
                for ($j = $i + 1; $j < $list->count(); $j++) {
                    [$a, $b] = [$list[$i], $list[$j]];
                    $sameNumber = $normal($a->invoice_number) !== '' && $normal($a->invoice_number) === $normal($b->invoice_number);
                    $sameAmount = abs((float) $a->total - (float) $b->total) < 0.005 && $a->invoice_date->diffInDays($b->invoice_date) <= 14;
                    if (!$sameNumber && !$sameAmount) {
                        continue;
                    }
                    $findings->push([
                        'type' => 'duplicate_supplier_invoice', 'severity' => $sameNumber ? 'high' : 'medium', 'score' => $sameNumber ? 100 : 70,
                        'title' => ($a->supplier?->name ?? 'Supplier').': '.$b->invoice_number.' looks like a duplicate of '.$a->invoice_number,
                        'subject_type' => SupplierInvoice::class, 'subject_id' => $b->id, 'subject_label' => $b->invoice_number, 'amount' => (float) $b->total,
                        'details' => ['first' => $a->invoice_number.' ('.$a->invoice_date->toDateString().', '.$a->status.')', 'second' => $b->invoice_number.' ('.$b->invoice_date->toDateString().', '.$b->status.')', 'reason' => $sameNumber ? 'same invoice number' : 'same total within 14 days', 'first_id' => $a->id],
                        'key' => 'pair:'.$a->id.':'.$b->id,
                    ]);
                }
            }
        }

        return $findings;
    }

    private function manualJournalChecks(Carbon $since): Collection
    {
        $journals = JournalEntry::with('lines')->whereNull('source_type')->where('status', 'posted')->whereDate('entry_date', '>=', $since->toDateString())->get();
        $findings = collect();

        foreach ($journals as $journal) {
            $total = round((float) $journal->lines->sum('debit'), 2);
            $base = ['subject_type' => JournalEntry::class, 'subject_id' => $journal->id, 'subject_label' => $journal->entry_number, 'amount' => $total];
            if ($total >= 1000 && fmod($total, 1000.0) == 0.0) {
                $findings->push($base + ['type' => 'round_amount_journal', 'severity' => 'low', 'score' => 30, 'title' => 'Manual journal '.$journal->entry_number.' is a round '.number_format($total, 0), 'details' => ['memo' => $journal->memo]]);
            }
            if ($journal->posted_at && $journal->posted_at->isWeekend()) {
                $findings->push($base + ['type' => 'weekend_posting', 'severity' => 'low', 'score' => 20, 'title' => 'Manual journal '.$journal->entry_number.' was posted on a '.$journal->posted_at->format('l'), 'details' => ['posted_at' => $journal->posted_at->toDateTimeString()]]);
            }
            if ($journal->created_by && (int) $journal->created_by === (int) $journal->posted_by) {
                $findings->push($base + ['type' => 'self_posted_journal', 'severity' => 'medium', 'score' => 50, 'title' => 'Manual journal '.$journal->entry_number.' was prepared and posted by the same person', 'details' => ['user_id' => $journal->created_by]]);
            }
        }

        return $findings;
    }

    private function benford(): Collection
    {
        $totals = SupplierInvoice::whereIn('status', ['posted', 'part_paid', 'paid'])->where('total', '>=', 10)->pluck('total')->map(fn ($t) => (int) substr(ltrim(str_replace('.', '', (string) $t), '0'), 0, 1))->filter();
        $n = $totals->count();
        if ($n < 50) {
            return collect();
        }
        $observed = $totals->countBy();
        $mad = 0.0;
        $digits = [];
        for ($d = 1; $d <= 9; $d++) {
            $expected = log10(1 + 1 / $d);
            $actual = ($observed[$d] ?? 0) / $n;
            $mad += abs($actual - $expected);
            $digits[$d] = ['expected' => round($expected * 100, 1), 'actual' => round($actual * 100, 1)];
        }
        $mad /= 9;
        if ($mad <= 0.015) {
            return collect();
        }

        return collect([[
            'type' => 'benford_deviation', 'severity' => $mad > 0.022 ? 'medium' : 'low', 'score' => round($mad * 1000, 2),
            'title' => 'First digits of '.$n.' supplier invoice totals deviate from Benford\'s law (MAD '.number_format($mad, 3).')',
            'details' => ['invoices' => $n, 'mad' => round($mad, 4), 'digits' => $digits], 'key' => 'benford:'.now()->format('Y-m'),
        ]]);
    }

    private static function median(array $values): float
    {
        sort($values);
        $n = count($values);
        if ($n === 0) {
            return 0.0;
        }

        return $n % 2 ? (float) $values[intdiv($n, 2)] : ((float) $values[$n / 2 - 1] + (float) $values[$n / 2]) / 2;
    }
}
