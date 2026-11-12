<?php

namespace App\Models\Platform;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'platform_payments';

    protected $fillable = [
        'platform_invoice_id', 'company_id', 'provider', 'transaction_reference', 'provider_reference', 'amount',
        'currency', 'status', 'failure_reason', 'redirect_url', 'poll_url', 'meta', 'paid_at',
    ];

    protected $casts = ['amount' => 'decimal:2', 'meta' => 'array', 'paid_at' => 'datetime'];

    public function invoice()
    {
        return $this->belongsTo(PlatformInvoice::class, 'platform_invoice_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
