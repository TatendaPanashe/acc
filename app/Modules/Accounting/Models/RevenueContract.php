<?php

namespace App\Modules\Accounting\Models;

class RevenueContract extends AccountingModel
{
    protected $table = 'accounting_revenue_contracts';

    protected $fillable = [
        'company_id', 'customer_id', 'contract_number', 'customer_name', 'transaction_price',
        'performance_obligations', 'starts_on', 'ends_on', 'recognition_method', 'status',
    ];

    protected $casts = ['transaction_price' => 'decimal:2', 'starts_on' => 'date', 'ends_on' => 'date'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
