<?php

namespace App\Modules\Accounting\Models;

class Subscription extends AccountingModel
{
    protected $table = 'accounting_subscriptions';

    protected $fillable = [
        'company_id', 'customer_id', 'contract_number', 'description',
        'recurring_amount', 'currency', 'billing_cycle', 'next_invoice_on', 'status',
    ];

    protected $casts = ['recurring_amount' => 'decimal:2', 'next_invoice_on' => 'date'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
