<?php

namespace App\Modules\Accounting\Models;

use App\Models\User;

class SubscriptionEvent extends AccountingModel
{
    protected static bool $audited = false;

    protected $table = 'accounting_subscription_events';

    protected $fillable = ['company_id', 'subscription_id', 'type', 'details', 'user_id'];

    protected $casts = ['details' => 'array'];

    public function user() { return $this->belongsTo(User::class); }
}
