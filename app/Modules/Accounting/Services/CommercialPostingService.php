<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\CustomerAllocation;
use App\Modules\Accounting\Models\CustomerReceipt;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\PosSale;
use App\Modules\Accounting\Models\SalesInvoice;
use App\Modules\Accounting\Models\SupplierInvoice;
use App\Modules\Accounting\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns commercial documents into posted journal entries.
 *
 * - Every document row is locked (SELECT ... FOR UPDATE) and re-checked inside the transaction,
 *   so a double click or two users posting at once cannot post twice.
 * - GL accounts come from the company's account mapping (Accounting Settings → Account Mapping).
 * - Foreign-currency documents are converted at the rate on the document date. When a receipt,
 *   payment or credit note settles an invoice, the invoice is relieved at its original rate and
 *   the difference is posted as a realised exchange gain or loss.
 */
class CommercialPostingService
{
    public function __construct(
        private readonly JournalPostingService $journalPosting,
        private readonly AccountMappingService $mappings,
        private readonly ExchangeRateService $rates,
    ) {
    }

    public function postPosSale(PosSale $sale): PosSale
    {
        return DB::transaction(function () use ($sale) {
            $sale = $this->lock($sale);
            if ($sale->journal_entry_id) {
                return $sale;
            }
            $sale->load('lines');

            $company = $sale->company_id;
            $rate = $this->rates->rate($sale->currency, $company, $sale->sold_at);
            $entry = $this->entry('POS-'.$sale->receipt_number, $sale->sold_at->toDateString(), 'point_of_sale', $sale, $sale->currency, $rate, 'POS receipt '.$sale->receipt_number);

            $this->line($entry, $this->mappings->tenderAccount($sale->payment_method, $company), strtoupper($sale->payment_method).' tender', (float) $sale->total, 0, $rate);
            $this->line($entry, $this->map('sales_revenue', $company), 'POS sales revenue', 0, (float) $sale->subtotal - (float) $sale->discount_total, $rate);
            if ((float) $sale->tax_total > 0) {
                $this->line($entry, $this->map('vat_output', $company), 'POS output VAT', 0, (float) $sale->tax_total, $rate);
            }

            // Cost of sales is recorded in base currency at the stock's carrying cost.
            $costTotal = round((float) $sale->lines->sum('cost_total'), 2);
            if ($costTotal > 0) {
                $this->baseLine($entry, $this->map('cogs', $company), 'POS cost of goods sold', $costTotal, 0);
                $this->baseLine($entry, $this->map('inventory', $company), 'POS inventory issue', 0, $costTotal);
            }

            $this->finish($entry);
            $sale->update(['journal_entry_id' => $entry->id]);

            return $sale->fresh('lines');
        });
    }

    public function postSalesInvoice(SalesInvoice $invoice): SalesInvoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = $this->lock($invoice);
            if ($invoice->status !== 'draft') {
                return $invoice;
            }
            $invoice->load('lines');

            $company = $invoice->company_id;
            $rate = $this->rates->rate($invoice->currency, $company, $invoice->invoice_date);
            $entry = $this->entry('AR-'.$invoice->invoice_number, $invoice->invoice_date, 'accounts_receivable', $invoice, $invoice->currency, $rate, 'Sales invoice '.$invoice->invoice_number);

            $this->line($entry, $this->map('accounts_receivable', $company), 'Customer invoice', (float) $invoice->total, 0, $rate);
            foreach ($invoice->lines as $line) {
                $this->line($entry, $this->postable($line->revenue_account_id, $company), $line->description, 0, (float) $line->line_total, $rate);
            }
            if ((float) $invoice->tax_total > 0) {
                $this->line($entry, $this->map('vat_output', $company), 'Output VAT', 0, (float) $invoice->tax_total, $rate);
            }

            $this->finish($entry);
            $invoice->update(['journal_entry_id' => $entry->id, 'status' => 'posted']);

            return $invoice->fresh('lines');
        });
    }

    public function postCreditNote(CreditNote $creditNote): CreditNote
    {
        return DB::transaction(function () use ($creditNote) {
            $creditNote = $this->lock($creditNote);
            if ($creditNote->status === 'posted') {
                return $creditNote;
            }

            $company = $creditNote->company_id;
            $invoice = $creditNote->invoice_id ? $this->lockInvoice(SalesInvoice::class, $creditNote->invoice_id) : null;
            $this->assertSameParty($invoice, 'customer_id', $creditNote->customer_id, $creditNote->currency, 'credit note');

            $rate = $this->rates->rate($creditNote->currency, $company, $creditNote->credit_note_date);
            $entry = $this->entry('CN-'.$creditNote->credit_note_number, $creditNote->credit_note_date, 'accounts_receivable', $creditNote, $creditNote->currency, $rate, 'Credit note '.$creditNote->credit_note_number);

            $this->line($entry, $this->map('sales_revenue', $company), 'Sales credit', (float) $creditNote->subtotal, 0, $rate);
            if ((float) $creditNote->tax_total > 0) {
                $this->line($entry, $this->map('vat_output', $company), 'Output VAT reversal', (float) $creditNote->tax_total, 0, $rate);
            }

            $applied = $invoice ? min((float) $creditNote->total, $invoice->balance) : 0.0;
            $this->settle($entry, $this->map('accounts_receivable', $company), 'Reduce receivable', (float) $creditNote->total, $applied, $rate, $invoice, credit: true);

            $this->finish($entry);
            $creditNote->update(['journal_entry_id' => $entry->id, 'status' => 'posted']);

            if ($invoice && $applied > 0) {
                $this->applyToInvoice($invoice, $applied);
            }

            return $creditNote->fresh();
        });
    }

    public function postReceipt(CustomerReceipt $receipt, ?SalesInvoice $invoice = null): CustomerReceipt
    {
        return DB::transaction(function () use ($receipt, $invoice) {
            $receipt = $this->lock($receipt);
            if ($receipt->status === 'posted') {
                return $receipt;
            }

            $company = $receipt->company_id;
            $invoice = $invoice ? $this->lockInvoice(SalesInvoice::class, $invoice->id) : null;
            $this->assertSameParty($invoice, 'customer_id', $receipt->customer_id, $receipt->currency, 'receipt');

            $rate = $this->rates->rate($receipt->currency, $company, $receipt->receipt_date);
            $entry = $this->entry('RCT-'.$receipt->receipt_number, $receipt->receipt_date, 'accounts_receivable', $receipt, $receipt->currency, $rate, 'Customer receipt '.$receipt->receipt_number);

            $amount = (float) $receipt->amount;
            $applied = $invoice ? min($amount, $invoice->balance) : 0.0;

            $this->line($entry, $this->mappings->tenderAccount($receipt->payment_method, $company), 'Receipt', $amount, 0, $rate);
            $this->settle($entry, $this->map('accounts_receivable', $company), 'Reduce receivable', $amount, $applied, $rate, $invoice, credit: true);

            $this->finish($entry);
            $receipt->update(['journal_entry_id' => $entry->id, 'status' => 'posted']);

            if ($invoice && $applied > 0) {
                CustomerAllocation::create([
                    'company_id' => $company,
                    'receipt_id' => $receipt->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $applied,
                ]);
                $this->applyToInvoice($invoice, $applied);
            }

            return $receipt->fresh('allocations');
        });
    }

    public function postSupplierInvoice(SupplierInvoice $invoice): SupplierInvoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = $this->lock($invoice);
            if (!in_array($invoice->status, ['draft', 'pending_approval', 'approved'], true)) {
                return $invoice;
            }
            $invoice->load('lines');

            $company = $invoice->company_id;
            $rate = $this->rates->rate($invoice->currency, $company, $invoice->invoice_date);
            $entry = $this->entry('AP-'.$invoice->invoice_number, $invoice->invoice_date, 'accounts_payable', $invoice, $invoice->currency, $rate, 'Supplier invoice '.$invoice->invoice_number);

            foreach ($invoice->lines as $line) {
                $this->line($entry, $this->postable($line->expense_account_id, $company), $line->description, (float) $line->line_total, 0, $rate);
            }
            if ((float) $invoice->tax_total > 0) {
                $this->line($entry, $this->map('vat_input', $company), 'Input VAT', (float) $invoice->tax_total, 0, $rate);
            }
            $this->line($entry, $this->map('accounts_payable', $company), 'Supplier payable', 0, (float) $invoice->total, $rate);

            $this->finish($entry);
            $invoice->update(['journal_entry_id' => $entry->id, 'status' => 'posted']);

            return $invoice->fresh('lines');
        });
    }

    public function postSupplierPayment(SupplierPayment $payment, ?SupplierInvoice $invoice = null): SupplierPayment
    {
        return DB::transaction(function () use ($payment, $invoice) {
            $payment = $this->lock($payment);
            if ($payment->status === 'posted') {
                return $payment;
            }

            $company = $payment->company_id;
            $invoice = $invoice ? $this->lockInvoice(SupplierInvoice::class, $invoice->id) : null;
            $this->assertSameParty($invoice, 'supplier_id', $payment->supplier_id, $payment->currency, 'payment');

            $rate = $this->rates->rate($payment->currency, $company, $payment->payment_date);
            $entry = $this->entry('PAY-'.$payment->payment_number, $payment->payment_date, 'accounts_payable', $payment, $payment->currency, $rate, 'Supplier payment '.$payment->payment_number);

            $amount = (float) $payment->amount;
            $applied = $invoice ? min($amount, $invoice->balance) : 0.0;

            $this->settle($entry, $this->map('accounts_payable', $company), 'Reduce payable', $amount, $applied, $rate, $invoice, credit: false);
            $this->line($entry, $this->mappings->tenderAccount($payment->payment_method, $company), 'Payment', 0, $amount, $rate);

            $this->finish($entry);
            $payment->update(['journal_entry_id' => $entry->id, 'status' => 'posted']);

            if ($invoice && $applied > 0) {
                $this->applyToInvoice($invoice, $applied);
            }

            return $payment->fresh();
        });
    }

    // ------------------------------------------------------------------

    /**
     * Posts the control-account side of a settlement. The part applied to an invoice is relieved at the
     * invoice's original rate; anything unapplied at today's rate. The base-currency difference against
     * the cash side is a realised exchange gain/loss.
     */
    private function settle(JournalEntry $entry, Account $control, string $description, float $amount, float $applied, float $rate, ?Model $invoice, bool $credit): void
    {
        $invoiceRate = $invoice ? $this->invoiceRate($invoice, $rate) : $rate;
        $base = $this->rates->toBase($applied, $invoiceRate) + $this->rates->toBase($amount - $applied, $rate);
        $cashBase = $this->rates->toBase($amount, $rate);

        $this->line($entry, $control, $description, $credit ? 0 : $amount, $credit ? $amount : 0, $rate, round($base, 2));

        // For a receipt (control credited) receiving more base than the invoice carried is a gain.
        $difference = round(($credit ? 1 : -1) * ($cashBase - $base), 2);
        if ($difference > 0) {
            $this->baseLine($entry, $this->map('fx_gain', $entry->company_id), 'Realised exchange gain', 0, $difference);
        } elseif ($difference < 0) {
            $this->baseLine($entry, $this->map('fx_loss', $entry->company_id), 'Realised exchange loss', abs($difference), 0);
        }
    }

    private function invoiceRate(Model $invoice, float $fallback): float
    {
        $rate = (float) JournalEntry::withoutGlobalScopes()->whereKey($invoice->journal_entry_id)->value('exchange_rate');

        return $rate > 0 ? $rate : $fallback;
    }

    private function assertSameParty(?Model $invoice, string $partyColumn, int $partyId, string $currency, string $document): void
    {
        if (!$invoice) {
            return;
        }

        if ((int) $invoice->{$partyColumn} !== $partyId) {
            throw ValidationException::withMessages(['invoice_id' => "The {$document} and invoice {$invoice->invoice_number} belong to different parties."]);
        }

        if (strtoupper($invoice->currency) !== strtoupper($currency)) {
            throw ValidationException::withMessages(['currency' => "The {$document} must be in {$invoice->currency}, the currency of invoice {$invoice->invoice_number}."]);
        }

        if (!in_array($invoice->status, ['posted', 'part_paid', 'paid'], true)) {
            throw ValidationException::withMessages(['invoice_id' => "Invoice {$invoice->invoice_number} has not been posted yet."]);
        }
    }

    private function applyToInvoice(Model $invoice, float $amount): void
    {
        $invoice->amount_paid = round((float) $invoice->amount_paid + $amount, 2);
        $invoice->status = $invoice->balance <= 0 ? 'paid' : 'part_paid';
        $invoice->save();
    }

    /** @template T of Model @param T $model @return T */
    private function lock(Model $model): Model
    {
        return $model->newQueryWithoutScopes()->lockForUpdate()->findOrFail($model->getKey());
    }

    private function lockInvoice(string $class, int $id): Model
    {
        return $class::withoutGlobalScopes()->lockForUpdate()->findOrFail($id);
    }

    private function map(string $key, int $companyId): Account
    {
        return $this->mappings->account($key, $companyId);
    }

    private function postable(int $accountId, int $companyId): Account
    {
        $account = Account::withoutGlobalScopes()->where('company_id', $companyId)->find($accountId);

        if (!$account || !$account->status || !$account->allow_posting) {
            throw ValidationException::withMessages(['account' => 'A document line uses an account that is missing, inactive or does not allow posting.']);
        }

        return $account;
    }

    private function entry(string $number, mixed $date, string $module, Model $source, string $currency, float $rate, string $memo): JournalEntry
    {
        $entry = new JournalEntry([
            'entry_number' => $number,
            'entry_date' => $date,
            'source_module' => $module,
            'source_type' => $source::class,
            'source_id' => $source->getKey(),
            'currency' => strtoupper($currency),
            'exchange_rate' => $rate,
            'memo' => $memo,
            'created_by' => auth()->id(),
        ]);
        $entry->company_id = $source->company_id;
        $entry->save();

        return $entry;
    }

    /** Line in transaction currency; base amount = fx × rate unless given. */
    private function line(JournalEntry $entry, Account $account, ?string $description, float $fxDebit, float $fxCredit, float $rate, ?float $base = null): void
    {
        $fxDebit = round($fxDebit, 2);
        $fxCredit = round($fxCredit, 2);
        $baseAmount = $base ?? $this->rates->toBase($fxDebit ?: $fxCredit, $rate);
        if ($baseAmount == 0.0) {
            return; // too small to register in base currency; any cent left over is absorbed as rounding
        }

        $entry->lines()->create([
            'company_id' => $entry->company_id,
            'account_id' => $account->id,
            'description' => $description,
            'fx_debit' => $fxDebit,
            'fx_credit' => $fxCredit,
            'debit' => $fxDebit > 0 ? $baseAmount : 0,
            'credit' => $fxCredit > 0 ? $baseAmount : 0,
        ]);
    }

    /** Line that exists only in base currency (exchange differences, stock cost). */
    private function baseLine(JournalEntry $entry, Account $account, string $description, float $debit, float $credit): void
    {
        $entry->lines()->create([
            'company_id' => $entry->company_id,
            'account_id' => $account->id,
            'description' => $description,
            'fx_debit' => 0,
            'fx_credit' => 0,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
        ]);
    }

    private function finish(JournalEntry $entry): void
    {
        $this->journalPosting->absorbRounding($entry);
        $this->journalPosting->post($entry);
    }
}
