<?php

namespace App\Modules\Accounting\Models;

class CustomerReceipt extends AccountingModel
{
    protected $table = 'accounting_customer_receipts';

    protected $fillable = [
        'company_id', 'customer_id', 'invoice_id', 'journal_entry_id', 'receipt_number',
        'receipt_date', 'currency', 'amount', 'payment_method', 'memo', 'status',
    ];

    protected $casts = ['receipt_date' => 'date', 'amount' => 'decimal:2'];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function invoice() { return $this->belongsTo(SalesInvoice::class, 'invoice_id'); }
    public function allocations() { return $this->hasMany(CustomerAllocation::class, 'receipt_id'); }
}
