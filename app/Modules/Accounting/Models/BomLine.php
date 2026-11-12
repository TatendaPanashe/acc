<?php

namespace App\Modules\Accounting\Models;

class BomLine extends AccountingModel
{
    protected static bool $audited = false;

    protected $table = 'accounting_bom_lines';

    protected $fillable = ['company_id', 'bom_id', 'item_id', 'quantity', 'scrap_percent', 'notes'];

    protected $casts = ['quantity' => 'decimal:4', 'scrap_percent' => 'decimal:2'];

    public function item() { return $this->belongsTo(Item::class); }

    /** Quantity per batch including expected scrap. */
    public function grossQuantity(): float
    {
        return round((float) $this->quantity * (1 + (float) $this->scrap_percent / 100), 4);
    }
}
