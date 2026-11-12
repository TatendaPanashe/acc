<?php

namespace App\Modules\Accounting\Models;

class TransferOrder extends AccountingModel
{
    protected $table = 'accounting_transfer_orders';

    protected $fillable = [
        'company_id', 'transfer_number', 'from_warehouse_id', 'to_warehouse_id',
        'transfer_date', 'status', 'notes',
    ];

    protected $casts = ['transfer_date' => 'date'];

    public function fromWarehouse() { return $this->belongsTo(Warehouse::class, 'from_warehouse_id'); }
    public function toWarehouse() { return $this->belongsTo(Warehouse::class, 'to_warehouse_id'); }
    public function lines() { return $this->hasMany(TransferOrderLine::class); }
}
