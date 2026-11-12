<?php

namespace App\Modules\Accounting\Services\Ai;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\SupplierInvoiceLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Suggests the GL account for a supplier invoice line from how the company coded similar lines before.
 *
 * Model: multinomial naive Bayes with Laplace smoothing over words of the line description plus a
 * "supplier" token, trained on lines of posted supplier invoices. The model is cached per company for
 * 10 minutes. Suggestions are advisory: people always choose the account.
 */
class AutoCodingService
{
    private const STOP = ['the', 'and', 'for', 'of', 'to', 'in', 'on', 'at', 'by', 'with', 'from', 'per', 'inv', 'invoice', 'no', 'ref'];

    /** @return Collection<int, array{text: string, supplier: ?int, account: int, date: string}> */
    public function samples(): Collection
    {
        return SupplierInvoiceLine::query()
            ->join('accounting_supplier_invoices as i', 'i.id', '=', 'accounting_supplier_invoice_lines.supplier_invoice_id')
            ->whereIn('i.status', ['posted', 'part_paid', 'paid'])
            ->orderBy('i.invoice_date')
            ->get(['accounting_supplier_invoice_lines.description', 'accounting_supplier_invoice_lines.expense_account_id', 'i.supplier_id', 'i.invoice_date'])
            ->map(fn ($r) => ['text' => (string) $r->description, 'supplier' => $r->supplier_id ? (int) $r->supplier_id : null, 'account' => (int) $r->expense_account_id, 'date' => (string) $r->invoice_date]);
    }

    public function train(Collection $samples): array
    {
        $model = ['docs' => 0, 'classes' => [], 'vocab' => []];
        foreach ($samples as $sample) {
            $class = $sample['account'];
            $model['docs']++;
            $model['classes'][$class] ??= ['docs' => 0, 'words' => 0, 'counts' => []];
            $model['classes'][$class]['docs']++;
            foreach (self::tokens($sample['text'], $sample['supplier']) as $token) {
                $model['classes'][$class]['counts'][$token] = ($model['classes'][$class]['counts'][$token] ?? 0) + 1;
                $model['classes'][$class]['words']++;
                $model['vocab'][$token] = true;
            }
        }
        $model['vocab_size'] = count($model['vocab']);
        unset($model['vocab']);

        return $model;
    }

    /** @return array<int, array{account_id: int, probability: float}> best first */
    public function predict(array $model, string $text, ?int $supplierId = null, int $limit = 3): array
    {
        if ($model['docs'] === 0) {
            return [];
        }
        $tokens = self::tokens($text, $supplierId);
        $scores = [];
        foreach ($model['classes'] as $class => $stats) {
            $score = log($stats['docs'] / $model['docs']);
            $denominator = $stats['words'] + $model['vocab_size'] + 1;
            foreach ($tokens as $token) {
                $score += log((($stats['counts'][$token] ?? 0) + 1) / $denominator);
            }
            $scores[$class] = $score;
        }
        // Softmax into probabilities.
        $max = max($scores);
        $exp = array_map(fn ($s) => exp($s - $max), $scores);
        $sum = array_sum($exp);
        arsort($exp);

        return collect($exp)->take($limit)->map(fn ($v, $class) => ['account_id' => (int) $class, 'probability' => round($v / $sum, 4)])->values()->all();
    }

    public function model(): array
    {
        $companyId = (int) auth()->user()->company_id;

        return Cache::remember('ai.autocoding.'.$companyId, 600, fn () => $this->train($this->samples()));
    }

    public function forget(): void
    {
        Cache::forget('ai.autocoding.'.(int) auth()->user()->company_id);
    }

    /** Suggestions with account names; only suggestions with at least $minimum probability. */
    public function suggest(string $text, ?int $supplierId = null, float $minimum = 0.15): array
    {
        $predictions = array_filter($this->predict($this->model(), $text, $supplierId), fn ($p) => $p['probability'] >= $minimum);
        $accounts = Account::whereIn('id', array_column($predictions, 'account_id'))->where('status', true)->get()->keyBy('id');

        return collect($predictions)->filter(fn ($p) => isset($accounts[$p['account_id']]))
            ->map(fn ($p) => $p + ['code' => $accounts[$p['account_id']]->code, 'name' => $accounts[$p['account_id']]->name])->values()->all();
    }

    /** Hold-out accuracy: train on the oldest 80% of lines, test on the newest 20%. */
    public function evaluate(): array
    {
        $samples = $this->samples();
        $n = $samples->count();
        if ($n < 10) {
            return ['samples' => $n, 'tested' => 0, 'accuracy' => null, 'top3' => null, 'accounts' => $samples->pluck('account')->unique()->count()];
        }
        $cut = (int) floor($n * 0.8);
        $model = $this->train($samples->take($cut));
        $test = $samples->slice($cut);
        $hits = 0;
        $top3 = 0;
        foreach ($test as $sample) {
            $predicted = array_column($this->predict($model, $sample['text'], $sample['supplier']), 'account_id');
            $hits += ($predicted[0] ?? null) === $sample['account'] ? 1 : 0;
            $top3 += in_array($sample['account'], $predicted, true) ? 1 : 0;
        }

        return ['samples' => $n, 'tested' => $test->count(), 'accuracy' => round($hits / $test->count() * 100, 1), 'top3' => round($top3 / $test->count() * 100, 1), 'accounts' => $samples->pluck('account')->unique()->count()];
    }

    /** @return string[] */
    public static function tokens(string $text, ?int $supplierId = null): array
    {
        $words = preg_split('/[^a-z]+/', Str::lower(Str::ascii($text)), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_values(array_filter($words, fn ($w) => strlen($w) >= 3 && !in_array($w, self::STOP, true)));
        if ($supplierId) {
            $words[] = '__supplier_'.$supplierId;
            $words[] = '__supplier_'.$supplierId; // supplier counts double: it is a strong signal
        }

        return $words;
    }
}
