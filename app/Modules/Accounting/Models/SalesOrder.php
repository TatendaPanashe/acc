<?php

namespace App\Modules\Accounting\Models;

class SalesOrder extends AccountingModel
{
    protected $table = 'accounting_sales_orders';

    protected $fillable = [
        'company_id', 'customer_id', 'order_number', 'order_date', 'required_date',
        'currency', 'subtotal', 'tax_total', 'total', 'status', 'notes',
    ];

    protected $casts = [
        'order_date' => 'date',
        'required_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function lines() { return $this->hasMany(SalesOrderLine::class); }
}
