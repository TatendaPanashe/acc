<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $table = 'accounting_webhook_events';

    protected $fillable = ['company_id', 'event_type', 'source', 'external_id', 'payload', 'status', 'error_message'];

    protected $casts = ['payload' => 'array'];
}
