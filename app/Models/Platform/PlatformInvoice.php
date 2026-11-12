<?php

namespace App\Models\Platform;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;

/** An invoice the platform issues to a tenant for its subscription. */
class PlatformInvoice extends Model
{
    protected $table = 'platform_invoices';

    protected $fillable = [
        'company_id', 'platform_subscription_id', 'number', 'purpose', 'platform_plan_id', 'billing_cycle', 'subtotal',
        'discount_total', 'coupon_id', 'lines', 'amount', 'currency', 'status', 'provider', 'provider_reference',
        'failure_reason', 'period_start', 'period_end', 'issued_at', 'due_at', 'reminder_sent_at', 'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'paid_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'lines' => 'array',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'platform_plan_id');
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class, 'platform_subscription_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'platform_invoice_id')->latest('id');
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'pending';
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
