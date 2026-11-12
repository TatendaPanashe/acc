<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Accounting\Services\ExchangeRateService;
use Illuminate\Support\Carbon;

/** Recurring (annuity) customer contract, billed in advance each cycle. */
class Subscription extends AccountingModel
{
    protected $table = 'accounting_subscriptions';

    public const CYCLES = ['monthly' => 1, 'quarterly' => 3, 'semiannual' => 6, 'annual' => 12];
    public const CYCLE_LABELS = ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'semiannual' => 'Every 6 months', 'annual' => 'Annually'];
    public const STATUSES = ['draft' => 'Draft', 'active' => 'Active', 'paused' => 'Paused', 'cancelled' => 'Cancelled', 'expired' => 'Expired'];

    protected $fillable = [
        'company_id', 'customer_id', 'contract_number', 'description', 'recurring_amount', 'currency', 'billing_cycle',
        'starts_on', 'ends_on', 'term_months', 'auto_renew', 'escalation_percent', 'payment_terms_days', 'post_invoices',
        'next_invoice_on', 'last_invoiced_on', 'status', 'cancelled_on', 'cancellation_reason', 'notes',
    ];

    protected $casts = [
        'recurring_amount' => 'decimal:2', 'escalation_percent' => 'decimal:2',
        'starts_on' => 'date', 'ends_on' => 'date', 'next_invoice_on' => 'date', 'last_invoiced_on' => 'date', 'cancelled_on' => 'date',
        'auto_renew' => 'boolean', 'post_invoices' => 'boolean',
    ];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function lines() { return $this->hasMany(SubscriptionLine::class); }
    public function events() { return $this->hasMany(SubscriptionEvent::class)->latest('id'); }
    public function invoices() { return $this->hasMany(SalesInvoice::class)->latest('invoice_date'); }

    public function cycleMonths(): int
    {
        return self::CYCLES[$this->billing_cycle] ?? 1;
    }

    /** End of the billing period that starts on $start. */
    public function periodEnd(Carbon $start): Carbon
    {
        return $start->copy()->addMonthsNoOverflow($this->cycleMonths())->subDay();
    }

    public function monthlyValue(): float
    {
        return round((float) $this->recurring_amount / $this->cycleMonths(), 2);
    }

    public function monthlyValueInBase(): float
    {
        try {
            $rate = app(ExchangeRateService::class)->rate($this->currency, (int) $this->company_id, now());
        } catch (\Throwable) {
            $rate = 1.0;
        }

        return round($this->monthlyValue() * $rate, 2);
    }
}
