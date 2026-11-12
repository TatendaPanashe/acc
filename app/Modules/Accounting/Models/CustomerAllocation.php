<?php

namespace App\Modules\Accounting\Models;

class CustomerAllocation extends AccountingModel
{
    protected $table = 'accounting_customer_allocations';

    protected $fillable = ['company_id', 'receipt_id', 'invoice_id', 'amount'];

    protected $casts = ['amount' => 'decimal:2'];

    public function receipt() { return $this->belongsTo(CustomerReceipt::class, 'receipt_id'); }
    public function invoice() { return $this->belongsTo(SalesInvoice::class, 'invoice_id'); }
}
