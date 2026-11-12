<?php

namespace App\Modules\Accounting\Models;

class PosSaleLine extends AccountingModel
{
    protected static bool $audited = false;

    protected $table = 'accounting_pos_sale_lines';

    protected $fillable = [
        'company_id', 'pos_sale_id', 'item_id', 'description', 'quantity',
        'unit_price', 'discount', 'tax_rate', 'line_total', 'tax_amount', 'cost_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'line_total' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'cost_total' => 'decimal:2',
    ];

    public function sale() { return $this->belongsTo(PosSale::class, 'pos_sale_id'); }
    public function item() { return $this->belongsTo(Item::class); }
}
