<?php

namespace App\Modules\Accounting\Services\Ai;

use App\Modules\Accounting\Models\CustomerReceipt;
use App\Modules\Accounting\Models\SalesInvoice;
use App\Modules\Accounting\Models\SupplierInvoice;
use App\Modules\Accounting\Models\SupplierPayment;
use App\Modules\Accounting\Services\ExchangeRateService;
use App\Modules\Accounting\Services\FinancialStatementService;
use App\Modules\Accounting\Services\TrialBalanceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Predicts when open invoices will be paid and builds a 13-week cash outlook.
 *
 * Each customer's (and supplier's) payment behaviour is learned from settled invoices: the median number
 * of days between the due date and the final payment. Invoices with no history use the company median.
 * Predicted dates in the past (already late) move to this week.
 */
class CashCollectionsService
{
    public function __construct(
        private readonly ExchangeRateService $rates,
        private readonly FinancialStatementService $statements,
        private readonly TrialBalanceService $trialBalance,
    ) {
    }

    /** @return array{by_party: array<int, float>, company: float, samples: int} days late (negative = early) */
    public function behaviour(string $side): array
    {
        if ($side === 'ar') {
            $paid = SalesInvoice::where('status', 'paid')->get(['id', 'customer_id', 'due_date']);
            $lastPayment = CustomerReceipt::where('status', 'posted')->whereIn('invoice_id', $paid->pluck('id'))->groupBy('invoice_id')->selectRaw('invoice_id, MAX(receipt_date) as paid_on')->pluck('paid_on', 'invoice_id');
            $party = 'customer_id';
        } else {
            $paid = SupplierInvoice::where('status', 'paid')->get(['id', 'supplier_id', 'due_date']);
            $lastPayment = SupplierPayment::where('status', 'posted')->whereIn('supplier_invoice_id', $paid->pluck('id'))->groupBy('supplier_invoice_id')->selectRaw('supplier_invoice_id, MAX(payment_date) as paid_on')->pluck('paid_on', 'supplier_invoice_id');
            $party = 'supplier_id';
        }

        $delays = $paid->filter(fn ($i) => isset($lastPayment[$i->id]))
            ->map(fn ($i) => ['party' => (int) $i->{$party}, 'days' => (int) $i->due_date->diffInDays(Carbon::parse($lastPayment[$i->id]), false)]);

        return [
            'by_party' => $delays->groupBy('party')->map(fn ($g) => self::median($g->pluck('days')->all()))->all(),
            'company' => $delays->isEmpty() ? 0.0 : self::median($delays->pluck('days')->all()),
            'samples' => $delays->count(),
        ];
    }

    /** Open invoices with predicted payment dates (base currency). */
    public function predictions(string $side, ?Carbon $today = null): Collection
    {
        $today ??= today();
        $behaviour = $this->behaviour($side);
        $model = $side === 'ar' ? SalesInvoice::class : SupplierInvoice::class;
        $party = $side === 'ar' ? 'customer' : 'supplier';

        return $model::with($party)->whereIn('status', ['posted', 'part_paid'])->get()
            ->map(function ($invoice) use ($behaviour, $today, $party) {
                $balance = round((float) $invoice->total - (float) $invoice->amount_paid, 2);
                if ($balance <= 0) {
                    return null;
                }
                $partyId = (int) $invoice->{$party.'_id'};
                $known = array_key_exists($partyId, $behaviour['by_party']);
                $delay = $known ? $behaviour['by_party'][$partyId] : $behaviour['company'];
                $expected = $invoice->due_date->copy()->addDays((int) round($delay));
                try {
                    $rate = $this->rates->rate($invoice->currency, (int) $invoice->company_id, $today);
                } catch (\Throwable) {
                    $rate = 1.0;
                }

                return [
                    'invoice' => $invoice, 'party' => $invoice->{$party}?->name, 'balance' => $balance, 'base' => round($balance * $rate, 2),
                    'due' => $invoice->due_date, 'delay' => round($delay, 1), 'expected' => $expected->lt($today) ? $today->copy() : $expected,
                    'late' => $expected->lt($today), 'basis' => $known ? 'This '.$party.'\'s history' : 'Company average',
                ];
            })->filter()->sortBy('expected')->values();
    }

    /** 13-week outlook: opening cash, expected receipts and payments per week. */
    public function outlook(int $weeks = 13, ?Carbon $today = null): array
    {
        $today ??= today();
        $start = $today->copy()->startOfWeek();
        $cash = round((float) $this->trialBalance->between(null, $today)->whereIn('id', $this->statements->cashAccountIds())->sum('balance'), 2);
        $in = $this->predictions('ar', $today);
        $out = $this->predictions('ap', $today);

        $rows = [];
        $running = $cash;
        for ($w = 0; $w < $weeks; $w++) {
            $from = $start->copy()->addWeeks($w);
            $to = $from->copy()->endOfWeek();
            $last = $w === $weeks - 1;
            $inside = fn ($p) => $p['expected']->gte($from) && ($last || $p['expected']->lte($to));
            $receipts = round($in->filter($inside)->sum('base'), 2);
            $payments = round($out->filter($inside)->sum('base'), 2);
            $running = round($running + $receipts - $payments, 2);
            $rows[] = ['label' => 'w/c '.$from->format('d M'), 'from' => $from, 'receipts' => $receipts, 'payments' => $payments, 'closing' => $running];
        }

        return ['opening' => $cash, 'weeks' => $rows, 'lowest' => collect($rows)->min('closing')];
    }

    private static function median(array $values): float
    {
        sort($values);
        $n = count($values);

        return $n === 0 ? 0.0 : ($n % 2 ? (float) $values[intdiv($n, 2)] : ((float) $values[$n / 2 - 1] + (float) $values[$n / 2]) / 2);
    }
}
