<?php

namespace App\Modules\Accounting\Models;

class SupplierInvoiceLine extends AccountingModel
{
    protected $table = 'accounting_supplier_invoice_lines';

    protected $fillable = [
        'company_id', 'supplier_invoice_id', 'expense_account_id', 'description',
        'quantity', 'unit_cost', 'tax_rate', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'line_total' => 'decimal:2',
    ];

    public function invoice() { return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id'); }
    public function expenseAccount() { return $this->belongsTo(Account::class, 'expense_account_id'); }
}
