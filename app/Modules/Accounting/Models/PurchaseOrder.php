<?php

namespace App\Modules\Accounting\Models;

class PurchaseOrder extends AccountingModel
{
    protected $table = 'accounting_purchase_orders';

    protected $fillable = [
        'company_id', 'supplier_id', 'order_number', 'order_date', 'expected_date',
        'currency', 'subtotal', 'tax_total', 'total', 'status', 'notes',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function lines() { return $this->hasMany(PurchaseOrderLine::class); }
}
