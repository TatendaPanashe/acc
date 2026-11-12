<?php

namespace App\Modules\Accounting\Models;

class SupplierInvoice extends AccountingModel
{
    protected $table = 'accounting_supplier_invoices';

    protected $fillable = [
        'company_id', 'supplier_id', 'journal_entry_id', 'invoice_number',
        'invoice_date', 'due_date', 'currency', 'subtotal', 'tax_total',
        'total', 'amount_paid', 'status',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function lines() { return $this->hasMany(SupplierInvoiceLine::class, 'supplier_invoice_id'); }
    public function getBalanceAttribute(): float { return round((float) $this->total - (float) $this->amount_paid, 2); }
}
