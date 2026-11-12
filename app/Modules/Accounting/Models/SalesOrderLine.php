<?php

namespace App\Modules\Accounting\Models;

class SalesOrderLine extends AccountingModel
{
    protected static bool $audited = false;

    protected $table = 'accounting_sales_order_lines';

    protected $fillable = [
        'company_id', 'sales_order_id', 'item_id', 'warehouse_id', 'description',
        'quantity', 'unit_price', 'tax_rate', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'line_total' => 'decimal:2',
    ];

    public function salesOrder() { return $this->belongsTo(SalesOrder::class); }
    public function item() { return $this->belongsTo(Item::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
}
