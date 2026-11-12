<?php

namespace App\Modules\Accounting\Models;

use App\Models\User;

class StoresRequisition extends AccountingModel
{
    protected $table = 'accounting_stores_requisitions';

    protected $fillable = [
        'company_id', 'requisition_number', 'warehouse_id', 'requested_by',
        'department', 'required_date', 'status', 'purpose',
    ];

    protected $casts = ['required_date' => 'date'];

    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function lines() { return $this->hasMany(StoresRequisitionLine::class); }
}
