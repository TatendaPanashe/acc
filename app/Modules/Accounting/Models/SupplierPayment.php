<?php

namespace App\Modules\Accounting\Models;

class SupplierPayment extends AccountingModel
{
    protected $table = 'accounting_supplier_payments';

    protected $fillable = [
        'company_id', 'payment_run_id', 'supplier_id', 'supplier_invoice_id',
        'journal_entry_id', 'payment_number', 'payment_date', 'currency',
        'amount', 'payment_method', 'status',
    ];

    protected $casts = ['payment_date' => 'date', 'amount' => 'decimal:2'];

    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function invoice() { return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id'); }
    public function paymentRun() { return $this->belongsTo(PaymentRun::class, 'payment_run_id'); }
}
