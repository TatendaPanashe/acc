<?php

namespace App\Modules\Accounting\Models;

class WorkOrderMaterial extends AccountingModel
{
    protected static bool $audited = false;

    protected $table = 'accounting_work_order_materials';

    protected $fillable = ['company_id', 'work_order_id', 'item_id', 'required_quantity', 'issued_quantity', 'issued_cost'];

    protected $casts = ['required_quantity' => 'decimal:4', 'issued_quantity' => 'decimal:4', 'issued_cost' => 'decimal:2'];

    public function item() { return $this->belongsTo(Item::class); }

    public function remaining(): float
    {
        return max(0, round((float) $this->required_quantity - (float) $this->issued_quantity, 4));
    }
}
