<?php

namespace App\Modules\Accounting\Models;

/** Bill of materials: what (and how much labour) it takes to make output_quantity of an item. */
class Bom extends AccountingModel
{
    protected $table = 'accounting_boms';

    protected $fillable = ['company_id', 'item_id', 'code', 'name', 'version', 'output_quantity', 'labour_hours', 'labour_rate', 'overhead_rate', 'is_active', 'notes'];

    protected $casts = ['output_quantity' => 'decimal:4', 'labour_hours' => 'decimal:4', 'labour_rate' => 'decimal:2', 'overhead_rate' => 'decimal:2', 'is_active' => 'boolean'];

    public function item() { return $this->belongsTo(Item::class); }
    public function lines() { return $this->hasMany(BomLine::class); }
    public function workOrders() { return $this->hasMany(WorkOrder::class); }
}
