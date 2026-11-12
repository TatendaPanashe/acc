<?php

namespace App\Modules\Accounting\Services\Production;

use App\Modules\Accounting\Models\Item;
use App\Modules\Accounting\Models\JobCost;
use App\Modules\Accounting\Models\JobCostEntry;
use App\Modules\Accounting\Models\SalesInvoice;
use App\Modules\Accounting\Services\AccountMappingService;
use App\Modules\Accounting\Services\ExchangeRateService;
use App\Modules\Accounting\Services\InventoryPostingService;
use App\Modules\Accounting\Services\InventoryStockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Project / job costing. Every cost is posted to Work in progress as it is recorded:
 *   materials   Dr WIP / Cr Inventory (issued from stock at issue cost)
 *   labour      Dr WIP / Cr Labour applied          overhead Dr WIP / Cr Overhead applied
 *   subcontract & other  Dr WIP / Cr the chosen account (default: Accrued costs)
 * Customers are invoiced through normal sales invoices (fixed price or cost plus mark-up).
 * Closing the job moves its accumulated cost from WIP to cost of sales.
 */
class JobCostingService
{
    public function __construct(
        private readonly InventoryStockService $stock,
        private readonly InventoryPostingService $ledger,
        private readonly AccountMappingService $mappings,
        private readonly ExchangeRateService $rates,
    ) {
    }

    public function save(JobCost $job, array $data): JobCost
    {
        $data['estimated_cost'] = round((float) ($data['budget_materials'] ?? 0) + (float) ($data['budget_labour'] ?? 0) + (float) ($data['budget_overhead'] ?? 0) + (float) ($data['budget_other'] ?? 0), 2);
        $job->fill($data + ($job->exists ? [] : ['type' => 'project', 'status' => 'planned', 'actual_cost' => 0]));
        $job->save();
        $this->refreshStatus($job);

        return $job->fresh();
    }

    public function addEntry(JobCost $job, array $data): JobCostEntry
    {
        return DB::transaction(function () use ($job, $data) {
            $job = JobCost::lockForUpdate()->findOrFail($job->id);
            if (!$job->isOpen()) {
                throw ValidationException::withMessages(['job' => 'Job '.$job->job_number.' is closed.']);
            }

            $category = $data['category'];
            $entry = new JobCostEntry([
                'job_cost_id' => $job->id, 'entry_date' => $data['entry_date'], 'category' => $category,
                'description' => $data['description'], 'created_by' => auth()->id(),
            ]);
            $entry->company_id = $job->company_id;

            if ($category === 'materials') {
                $item = Item::findOrFail($data['item_id']);
                $movement = $this->stock->move($job->company_id, $item->id, (int) $data['warehouse_id'], 'job_issue', -1 * (float) $data['quantity'], null, $job);
                $entry->fill(['item_id' => $item->id, 'warehouse_id' => $data['warehouse_id'], 'quantity' => $data['quantity'],
                    'unit_cost' => (float) $movement->unit_cost, 'amount' => InventoryStockService::value($movement)]);
                $credit = 'inventory';
            } elseif (in_array($category, ['labour', 'overhead'], true)) {
                $entry->fill(['quantity' => $data['quantity'], 'unit_cost' => $data['unit_cost'], 'amount' => round((float) $data['quantity'] * (float) $data['unit_cost'], 2)]);
                $credit = $category === 'labour' ? 'labour_applied' : 'overhead_applied';
            } else {
                $creditId = (int) ($data['credit_account_id'] ?? 0) ?: $this->mappings->account('accrued_costs', $job->company_id)->id;
                $entry->fill(['quantity' => 1, 'unit_cost' => $data['amount'], 'amount' => round((float) $data['amount'], 2), 'credit_account_id' => $creditId]);
                $credit = $creditId;
            }

            if ((float) $entry->amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'The cost must be more than zero.']);
            }
            $entry->save();

            $journal = $this->ledger->transfer($entry, $job->job_number.'-C'.$entry->id, $entry->entry_date, 'wip', $credit, (float) $entry->amount,
                JobCost::CATEGORIES[$category].': '.$entry->description.' ('.$job->job_number.')', 'job_costing');
            $entry->forceFill(['journal_entry_id' => $journal?->id])->saveQuietly();

            $job->actual_cost = round((float) $job->actual_cost + (float) $entry->amount, 2);
            $job->started_on ??= $entry->entry_date;
            $job->save();
            $this->refreshStatus($job);

            return $entry;
        });
    }

    /** Planned → in progress once costs arrive; flagged for review when spend passes budget + tolerance. */
    public function refreshStatus(JobCost $job): void
    {
        if (in_array($job->status, ['closed', 'completed'], true)) {
            return;
        }
        $limit = (float) $job->estimated_cost * (1 + (float) $job->variance_tolerance / 100);
        $status = (float) $job->actual_cost <= 0 ? 'planned' : ((float) $job->estimated_cost > 0 && (float) $job->actual_cost > $limit ? 'variance_review' : 'wip');
        if ($job->status === 'variance_review' && $status === 'wip') {
            return; // stays flagged until someone acknowledges it
        }
        if ($status !== $job->status) {
            $job->update(['status' => $status]);
        }
    }

    public function acknowledgeVariance(JobCost $job): void
    {
        if ($job->status === 'variance_review') {
            $job->update(['status' => 'wip', 'variance_tolerance' => round(max((float) $job->variance_tolerance, ((float) $job->actual_cost / max(0.01, (float) $job->estimated_cost) - 1) * 100 + 5), 2)]);
        }
    }

    /** Amount a cost-plus job can bill now: un-invoiced cost plus mark-up. */
    public function costPlusBillable(JobCost $job): float
    {
        $billed = (float) $job->invoiced_amount;
        $total = round((float) $job->actual_cost * (1 + (float) $job->markup_percent / 100), 2);

        return max(0, round($total - $billed, 2));
    }

    /** Creates a draft sales invoice for the job. */
    public function invoice(JobCost $job, float $amount, string $description, float $taxRate, int $revenueAccountId, string $currency): SalesInvoice
    {
        return DB::transaction(function () use ($job, $amount, $description, $taxRate, $revenueAccountId, $currency) {
            $job = JobCost::lockForUpdate()->with('customer')->findOrFail($job->id);
            if (!$job->customer_id) {
                throw ValidationException::withMessages(['customer' => 'Link the job to a customer before invoicing.']);
            }
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Enter an amount to invoice.']);
            }
            $rate = $this->rates->rate($currency, $job->company_id, now());
            $baseAmount = round($amount * $rate, 2);
            if ($job->billing_method === 'fixed' && (float) $job->contract_value > 0 && (float) $job->invoiced_amount + $baseAmount > (float) $job->contract_value + 0.01) {
                throw ValidationException::withMessages(['amount' => 'That would bill more than the contract value ('.number_format((float) $job->contract_value, 2).'); '.number_format((float) $job->contract_value - (float) $job->invoiced_amount, 2).' is left to bill.']);
            }

            $tax = round($amount * $taxRate / 100, 2);
            $count = SalesInvoice::where('job_cost_id', $job->id)->count() + 1;
            $invoice = new SalesInvoice([
                'customer_id' => $job->customer_id, 'invoice_number' => $job->job_number.'-INV'.$count,
                'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(), 'currency' => $currency,
                'subtotal' => round($amount, 2), 'tax_total' => $tax, 'total' => round($amount + $tax, 2), 'status' => 'draft', 'job_cost_id' => $job->id,
            ]);
            $invoice->company_id = $job->company_id;
            $invoice->save();
            $invoice->lines()->create(['company_id' => $job->company_id, 'revenue_account_id' => $revenueAccountId, 'description' => $description,
                'quantity' => 1, 'unit_price' => round($amount, 2), 'tax_rate' => $taxRate, 'line_total' => round($amount, 2)]);

            $job->update(['invoiced_amount' => round((float) $job->invoiced_amount + $baseAmount, 2)]);

            return $invoice;
        });
    }

    /** Moves the job's cost from WIP to cost of sales and closes it. */
    public function close(JobCost $job): JobCost
    {
        return DB::transaction(function () use ($job) {
            $job = JobCost::lockForUpdate()->findOrFail($job->id);
            if (!$job->isOpen()) {
                throw ValidationException::withMessages(['job' => 'Job '.$job->job_number.' is already closed.']);
            }
            $entry = $this->ledger->transfer($job, $job->job_number.'-CLOSE', now()->toDateString(), 'job_cost_of_sales', 'wip', (float) $job->actual_cost,
                'Job '.$job->job_number.' closed: cost to cost of sales', 'job_costing');
            $job->update(['status' => 'closed', 'closed_on' => now()->toDateString(), 'closing_entry_id' => $entry?->id]);

            return $job->fresh();
        });
    }

    /** Budget vs actual by category. */
    public function summary(JobCost $job): array
    {
        $actual = $job->entries()->getQuery()->reorder()->selectRaw('category, SUM(amount) as total')->groupBy('category')->pluck('total', 'category');
        $rows = [];
        foreach (['materials' => 'Materials', 'labour' => 'Labour', 'overhead' => 'Overhead', 'other' => 'Subcontract & other'] as $key => $label) {
            $spent = $key === 'other' ? (float) ($actual['subcontract'] ?? 0) + (float) ($actual['other'] ?? 0) : (float) ($actual[$key] ?? 0);
            $budget = $job->budgetFor($key);
            $rows[] = ['label' => $label, 'budget' => $budget, 'actual' => round($spent, 2), 'variance' => round($budget - $spent, 2),
                'used' => $budget > 0 ? round($spent / $budget * 100, 1) : null];
        }

        return $rows;
    }

    public function nextNumber(): string
    {
        $last = JobCost::where('job_number', 'like', 'JOB-%')->orderByDesc('id')->value('job_number');

        return 'JOB-'.str_pad((string) ((int) substr((string) $last, 4) + 1), 4, '0', STR_PAD_LEFT);
    }
}
