<?php

namespace App\Modules\Accounting\Models;

class SalesInvoice extends AccountingModel
{
    protected $table = 'accounting_invoices';

    protected $fillable = [
        'company_id', 'customer_id', 'journal_entry_id', 'invoice_number',
        'invoice_date', 'due_date', 'currency', 'subtotal', 'tax_total',
        'total', 'amount_paid', 'status', 'subscription_id', 'billing_run_id', 'job_cost_id',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function lines() { return $this->hasMany(SalesInvoiceLine::class, 'invoice_id'); }
    public function journalEntry() { return $this->belongsTo(JournalEntry::class); }
    public function subscription() { return $this->belongsTo(Subscription::class); }
    public function jobCost() { return $this->belongsTo(JobCost::class); }
    public function getBalanceAttribute(): float { return round((float) $this->total - (float) $this->amount_paid, 2); }
}
