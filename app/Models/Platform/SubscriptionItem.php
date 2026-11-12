<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

class SubscriptionItem extends Model
{
    protected $table = 'platform_subscription_items';

    protected $fillable = ['platform_subscription_id', 'item_type', 'description', 'quantity', 'unit_price'];

    protected $casts = ['unit_price' => 'decimal:2'];

    public function total(): float
    {
        return round($this->quantity * (float) $this->unit_price, 2);
    }
}
