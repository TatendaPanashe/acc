<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhook extends Model
{
    protected $table = 'platform_payment_webhooks';

    protected $fillable = ['provider', 'event_id', 'payload', 'signature_valid', 'processed_at', 'error'];

    protected $casts = ['payload' => 'array', 'signature_valid' => 'boolean', 'processed_at' => 'datetime'];
}
