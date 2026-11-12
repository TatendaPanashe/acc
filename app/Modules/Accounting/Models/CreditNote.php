<?php

namespace App\Modules\Accounting\Models;

class CreditNote extends AccountingModel
{
    protected $table = 'accounting_credit_notes';

    protected $fillable = [
        'company_id', 'customer_id', 'invoice_id', 'journal_entry_id',
        'credit_note_number', 'credit_note_date', 'currency', 'subtotal',
        'tax_total', 'total', 'reason', 'status',
    ];

    protected $casts = [
        'credit_note_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function invoice() { return $this->belongsTo(SalesInvoice::class, 'invoice_id'); }
}
