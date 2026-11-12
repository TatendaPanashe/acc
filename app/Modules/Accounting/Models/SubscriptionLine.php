<?php

namespace App\Modules\Accounting\Models;

class SubscriptionLine extends AccountingModel
{
    protected static bool $audited = false;

    protected $table = 'accounting_subscription_lines';

    protected $fillable = ['company_id', 'subscription_id', 'item_id', 'revenue_account_id', 'description', 'quantity', 'unit_price', 'tax_rate'];

    protected $casts = ['quantity' => 'decimal:4', 'unit_price' => 'decimal:2', 'tax_rate' => 'decimal:4'];

    public function item() { return $this->belongsTo(Item::class); }
    public function revenueAccount() { return $this->belongsTo(Account::class, 'revenue_account_id'); }

    public function amount(): float
    {
        return round((float) $this->quantity * (float) $this->unit_price, 2);
    }
}
