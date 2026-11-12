<?php

namespace App\Modules\Accounting\Models;

class PurchaseOrderLine extends AccountingModel
{
    protected $table = 'accounting_purchase_order_lines';

    protected $fillable = [
        'company_id', 'purchase_order_id', 'item_id', 'warehouse_id',
        'description', 'quantity', 'unit_cost', 'tax_rate', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'line_total' => 'decimal:2',
    ];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function item() { return $this->belongsTo(Item::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
}
