<?php

namespace App\Modules\Accounting\Services\Annuity;

use App\Modules\Accounting\Models\BillingRun;
use App\Modules\Accounting\Models\SalesInvoice;
use App\Modules\Accounting\Models\Subscription;
use App\Modules\Accounting\Models\SubscriptionEvent;
use App\Modules\Accounting\Services\CommercialPostingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Annuity (recurring) billing: contracts are billed in advance, one invoice per billing period.
 *
 * A billing run invoices every active contract whose next invoice date has arrived, catching up missed
 * periods (at most 24 per run). At the end of the term a contract renews automatically for another term
 * (applying its price escalation) or expires. Invoices are dated on the run date and name the service period.
 */
class RecurringBillingService
{
    private const MAX_PERIODS_PER_RUN = 24;

    public function __construct(private readonly CommercialPostingService $posting)
    {
    }

    /** Creates or updates a contract with its lines. */
    public function save(Subscription $contract, array $data, array $lines): Subscription
    {
        return DB::transaction(function () use ($contract, $data, $lines) {
            $isNew = !$contract->exists;
            $amount = round(collect($lines)->sum(fn ($l) => round((float) $l['quantity'] * (float) $l['unit_price'], 2)), 2);

            $contract->fill($data + ['recurring_amount' => $amount]);
            if ($isNew) {
                $contract->next_invoice_on = $contract->starts_on;
                $contract->status = $data['status'] ?? 'active';
                if ($contract->term_months && !$contract->ends_on) {
                    $contract->ends_on = $contract->starts_on->copy()->addMonthsNoOverflow($contract->term_months)->subDay();
                }
            }
            $contract->save();

            $contract->lines()->delete();
            foreach ($lines as $line) {
                $contract->lines()->create($line + ['company_id' => $contract->company_id]);
            }
            $this->event($contract, $isNew ? 'created' : 'updated', ['amount' => $amount]);

            return $contract->fresh('lines');
        });
    }

    /** Contracts with an invoice due on or before $asOf. */
    public function due(Carbon $asOf): Collection
    {
        return Subscription::with(['customer', 'lines'])
            ->where('status', 'active')
            ->whereNotNull('next_invoice_on')
            ->whereDate('next_invoice_on', '<=', $asOf->toDateString())
            ->orderBy('next_invoice_on')
            ->get();
    }

    /** What a run on $asOf would invoice, without changing anything. */
    public function preview(Carbon $asOf): Collection
    {
        return $this->due($asOf)->map(function (Subscription $contract) use ($asOf) {
            $periods = $this->periodsDue($contract->replicate()->setRelation('lines', $contract->lines->map(fn ($l) => clone $l)), $asOf, dryRun: true);
            $tax = round($contract->lines->sum(fn ($l) => round($l->amount() * (float) $l->tax_rate / 100, 2)), 2);

            return [
                'contract' => $contract,
                'periods' => $periods,
                'net' => round($periods->sum('net'), 2),
                'tax' => round($periods->count() * $tax, 2),
                'renewal' => $periods->contains(fn ($p) => $p['renewed']),
                'expires' => $periods->isEmpty(),
            ];
        });
    }

    /**
     * Invoices all due contracts. $post: true = post every invoice, false = leave drafts,
     * null = follow each contract's "post invoices automatically" setting.
     */
    public function run(Carbon $asOf, ?bool $post = null, ?int $userId = null): BillingRun
    {
        return DB::transaction(function () use ($asOf, $post, $userId) {
            $companyId = (int) auth()->user()->company_id;
            $run = new BillingRun([
                'run_number' => $this->nextRunNumber($asOf),
                'run_date' => $asOf->toDateString(),
                'created_by' => $userId ?? auth()->id(),
            ]);
            $run->company_id = $companyId;
            $run->save();

            $total = 0.0;
            $count = 0;
            $postedAny = false;
            foreach ($this->due($asOf) as $due) {
                $contract = Subscription::lockForUpdate()->with(['customer', 'lines'])->findOrFail($due->id);
                if ($contract->status !== 'active' || !$contract->next_invoice_on || $contract->next_invoice_on->gt($asOf)) {
                    continue; // changed since the list was read
                }

                $periods = $this->periodsDue($contract, $asOf);
                foreach ($periods as $period) {
                    $invoice = $this->invoice($contract, $period, $asOf, $run);
                    if ($post ?? $contract->post_invoices) {
                        $this->posting->postSalesInvoice($invoice);
                        $postedAny = true;
                    }
                    $total += (float) $invoice->total;
                    $count++;
                }
                if ($periods->isNotEmpty()) {
                    $contract->last_invoiced_on = $asOf->toDateString();
                }
                $contract->save();
            }

            $run->update(['invoice_count' => $count, 'total' => round($total, 2), 'posted' => $postedAny]);

            return $run->fresh();
        });
    }

    /**
     * Billing periods due for a contract up to $asOf. Advances the contract (next invoice date,
     * renewals, escalation, expiry) unless $dryRun.
     */
    private function periodsDue(Subscription $contract, Carbon $asOf, bool $dryRun = false): Collection
    {
        $periods = collect();
        $guard = 0;

        while ($contract->status === 'active' && $contract->next_invoice_on && $contract->next_invoice_on->lte($asOf) && $guard++ < self::MAX_PERIODS_PER_RUN) {
            $start = $contract->next_invoice_on->copy();
            $renewed = false;

            if ($contract->ends_on && $start->gt($contract->ends_on)) {
                if (!$contract->auto_renew) {
                    $contract->status = 'expired';
                    if (!$dryRun) {
                        $contract->save();
                        $this->event($contract, 'expired', ['ended_on' => $contract->ends_on->toDateString()]);
                    }
                    break;
                }
                $this->renew($contract, $dryRun);
                $renewed = true;
            }

            $end = $contract->periodEnd($start);
            $net = round($contract->lines->sum(fn ($l) => $l->amount()), 2);
            // Prices are captured per period so a catch-up run across a renewal bills each period at its own price.
            $prices = $contract->lines->map(fn ($l) => (float) $l->unit_price)->values()->all();
            $periods->push(['start' => $start, 'end' => $end, 'net' => $net, 'renewed' => $renewed, 'prices' => $prices]);
            $contract->next_invoice_on = $start->copy()->addMonthsNoOverflow($contract->cycleMonths());
        }

        return $periods;
    }

    private function renew(Subscription $contract, bool $dryRun): void
    {
        $termMonths = $contract->term_months ?: 12;
        $previousEnd = $contract->ends_on->copy();
        $contract->ends_on = $previousEnd->copy()->addMonthsNoOverflow($termMonths);
        $pct = (float) $contract->escalation_percent;

        if ($pct > 0) {
            foreach ($contract->lines as $line) {
                $line->unit_price = round((float) $line->unit_price * (1 + $pct / 100), 2);
                if (!$dryRun) {
                    $line->save();
                }
            }
            $contract->recurring_amount = round($contract->lines->sum(fn ($l) => $l->amount()), 2);
        }

        if (!$dryRun) {
            $contract->save();
            $this->event($contract, 'renewed', ['previous_end' => $previousEnd->toDateString(), 'new_end' => $contract->ends_on->toDateString(), 'escalation_percent' => $pct, 'new_amount' => (float) $contract->recurring_amount]);
        }
    }

    private function invoice(Subscription $contract, array $period, Carbon $asOf, BillingRun $run): SalesInvoice
    {
        $periodText = $period['start']->format('d M Y').' – '.$period['end']->format('d M Y');
        $lines = $contract->lines->values()->map(function ($line, $i) use ($period) {
            $price = $period['prices'][$i] ?? (float) $line->unit_price;
            $amount = round((float) $line->quantity * $price, 2);

            return ['line' => $line, 'price' => $price, 'amount' => $amount, 'tax' => round($amount * (float) $line->tax_rate / 100, 2)];
        });
        $subtotal = round($lines->sum('amount'), 2);
        $tax = round($lines->sum('tax'), 2);

        $invoice = new SalesInvoice([
            'customer_id' => $contract->customer_id,
            'invoice_number' => $this->invoiceNumber($contract, $period['start']),
            'invoice_date' => $asOf->toDateString(),
            'due_date' => $asOf->copy()->addDays((int) $contract->payment_terms_days)->toDateString(),
            'currency' => $contract->currency,
            'subtotal' => $subtotal,
            'tax_total' => $tax,
            'total' => round($subtotal + $tax, 2),
            'status' => 'draft',
            'subscription_id' => $contract->id,
            'billing_run_id' => $run->id,
        ]);
        $invoice->company_id = $contract->company_id;
        $invoice->save();

        foreach ($lines as $row) {
            $invoice->lines()->create([
                'company_id' => $contract->company_id,
                'revenue_account_id' => $row['line']->revenue_account_id,
                'description' => $row['line']->description.' ('.$periodText.')',
                'quantity' => $row['line']->quantity,
                'unit_price' => $row['price'],
                'tax_rate' => $row['line']->tax_rate,
                'line_total' => $row['amount'],
            ]);
        }
        $this->event($contract, 'invoiced', ['invoice' => $invoice->invoice_number, 'period' => $periodText, 'total' => (float) $invoice->total]);

        return $invoice;
    }

    private function invoiceNumber(Subscription $contract, Carbon $start): string
    {
        $base = $contract->contract_number.'-'.$start->format('Ym');
        $number = $base;
        for ($i = 2; SalesInvoice::where('invoice_number', $number)->exists(); $i++) {
            $number = $base.'-'.$i;
        }

        return $number;
    }

    private function nextRunNumber(Carbon $asOf): string
    {
        $prefix = 'BR-'.$asOf->format('Ymd').'-';
        $count = BillingRun::where('run_number', 'like', $prefix.'%')->count();

        return $prefix.str_pad((string) ($count + 1), 2, '0', STR_PAD_LEFT);
    }

    public function pause(Subscription $contract): void
    {
        $this->assertStatus($contract, ['active']);
        $contract->update(['status' => 'paused']);
        $this->event($contract, 'paused');
    }

    /** Resuming does not back-bill the paused months: billing restarts from today at the latest. */
    public function resume(Subscription $contract): void
    {
        $this->assertStatus($contract, ['paused', 'draft']);
        $next = $contract->next_invoice_on && $contract->next_invoice_on->gte(today()) ? $contract->next_invoice_on : today();
        $contract->update(['status' => 'active', 'next_invoice_on' => $next]);
        $this->event($contract, 'resumed', ['next_invoice_on' => $next->toDateString()]);
    }

    public function cancel(Subscription $contract, string $reason): void
    {
        $this->assertStatus($contract, ['active', 'paused', 'draft']);
        $contract->update(['status' => 'cancelled', 'cancelled_on' => today(), 'cancellation_reason' => $reason, 'next_invoice_on' => null]);
        $this->event($contract, 'cancelled', ['reason' => $reason]);
    }

    private function assertStatus(Subscription $contract, array $allowed): void
    {
        if (!in_array($contract->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'This contract is '.$contract->status.'.']);
        }
    }

    private function event(Subscription $contract, string $type, array $details = []): void
    {
        $event = new SubscriptionEvent(['subscription_id' => $contract->id, 'type' => $type, 'details' => $details ?: null, 'user_id' => auth()->id()]);
        $event->company_id = $contract->company_id;
        $event->save();
    }
}
