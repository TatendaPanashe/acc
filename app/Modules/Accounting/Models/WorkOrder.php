<?php

namespace App\Modules\Accounting\Models;

use App\Models\User;

class WorkOrder extends AccountingModel
{
    protected $table = 'accounting_work_orders';

    public const STATUSES = ['draft' => 'Draft', 'released' => 'Released', 'in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

    protected $fillable = [
        'company_id', 'wo_number', 'bom_id', 'item_id', 'warehouse_id', 'quantity', 'status', 'due_date', 'standard_unit_cost',
        'material_cost', 'labour_cost', 'overhead_cost', 'labour_hours', 'completed_quantity', 'released_at', 'completed_at', 'created_by', 'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4', 'completed_quantity' => 'decimal:4', 'labour_hours' => 'decimal:4', 'standard_unit_cost' => 'decimal:4',
        'material_cost' => 'decimal:2', 'labour_cost' => 'decimal:2', 'overhead_cost' => 'decimal:2',
        'due_date' => 'date', 'released_at' => 'datetime', 'completed_at' => 'datetime',
    ];

    public function bom() { return $this->belongsTo(Bom::class); }
    public function item() { return $this->belongsTo(Item::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function materials() { return $this->hasMany(WorkOrderMaterial::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function totalCost(): float
    {
        return round((float) $this->material_cost + (float) $this->labour_cost + (float) $this->overhead_cost, 2);
    }

    public function actualUnitCost(): ?float
    {
        return (float) $this->completed_quantity > 0 ? round($this->totalCost() / (float) $this->completed_quantity, 4) : null;
    }
}
