<?php

namespace App\Modules\Accounting\Models;

class Item extends AccountingModel
{
    protected $table = 'accounting_items';

    protected $fillable = [
        'company_id', 'sku', 'name', 'type', 'costing_method', 'standard_cost',
        'selling_price', 'reorder_level', 'track_serials', 'track_lots', 'status',
    ];

    protected $casts = [
        'standard_cost' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'reorder_level' => 'decimal:4',
        'track_serials' => 'boolean',
        'track_lots' => 'boolean',
        'status' => 'boolean',
    ];

    public function stockMovements() { return $this->hasMany(StockMovement::class); }

    public function getQuantityOnHandAttribute(): float
    {
        return (float) $this->stockMovements()->sum('quantity');
    }
}
