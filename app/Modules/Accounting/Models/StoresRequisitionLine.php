<?php

namespace App\Modules\Accounting\Models;

class StoresRequisitionLine extends AccountingModel
{
    protected static bool $audited = false;

    protected $table = 'accounting_stores_requisition_lines';

    protected $fillable = [
        'company_id', 'stores_requisition_id', 'item_id', 'description',
        'quantity_requested', 'quantity_issued',
    ];

    protected $casts = [
        'quantity_requested' => 'decimal:4',
        'quantity_issued' => 'decimal:4',
    ];

    public function storesRequisition() { return $this->belongsTo(StoresRequisition::class); }
    public function item() { return $this->belongsTo(Item::class); }
}
