<?php

namespace App\Modules\Accounting\Models;

class Opportunity extends AccountingModel
{
    protected $table = 'accounting_opportunities';

    protected $fillable = ['company_id', 'customer_id', 'name', 'stage', 'value', 'expected_close_on'];

    protected $casts = ['expected_close_on' => 'date', 'value' => 'decimal:2'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
