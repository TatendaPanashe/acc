<?php

namespace App\Modules\Accounting\Models;

class SalesInvoiceLine extends AccountingModel
{
    protected $table = 'accounting_invoice_lines';

    protected $fillable = [
        'company_id', 'invoice_id', 'revenue_account_id', 'description',
        'quantity', 'unit_price', 'tax_rate', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'line_total' => 'decimal:2',
    ];

    public function invoice() { return $this->belongsTo(SalesInvoice::class, 'invoice_id'); }
    public function revenueAccount() { return $this->belongsTo(Account::class, 'revenue_account_id'); }
}
