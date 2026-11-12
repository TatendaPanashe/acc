<?php

namespace App\Modules\Accounting\Models;

class StockMovement extends AccountingModel
{
    protected static bool $audited = false;

    protected $table = 'accounting_stock_movements';

    protected $fillable = [
        'company_id', 'item_id', 'warehouse_id', 'movement_type', 'quantity',
        'unit_cost', 'source_type', 'source_id', 'moved_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'moved_at' => 'datetime',
    ];

    public function item() { return $this->belongsTo(Item::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function source() { return $this->morphTo(); }
}
