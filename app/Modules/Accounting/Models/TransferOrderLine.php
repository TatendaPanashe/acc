<?php

namespace App\Modules\Accounting\Models;

class TransferOrderLine extends AccountingModel
{
    protected static bool $audited = false;

    protected $table = 'accounting_transfer_order_lines';

    protected $fillable = ['company_id', 'transfer_order_id', 'item_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:4'];

    public function transferOrder() { return $this->belongsTo(TransferOrder::class); }
    public function item() { return $this->belongsTo(Item::class); }
}
