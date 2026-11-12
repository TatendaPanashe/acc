<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $table = 'platform_coupons';

    protected $fillable = ['code', 'description', 'discount_type', 'discount_value', 'duration', 'applies_to_plans', 'max_redemptions', 'times_redeemed', 'expires_at', 'is_active'];

    protected $casts = ['discount_value' => 'decimal:2', 'applies_to_plans' => 'array', 'expires_at' => 'datetime', 'is_active' => 'boolean'];

    /** Null when usable for the plan, otherwise the reason it is not. */
    public function problemFor(Plan $plan): ?string
    {
        return match (true) {
            !$this->is_active => 'This coupon is no longer active.',
            $this->expires_at && $this->expires_at->isPast() => 'This coupon has expired.',
            $this->max_redemptions !== null && $this->times_redeemed >= $this->max_redemptions => 'This coupon has been fully redeemed.',
            !empty($this->applies_to_plans) && !in_array($plan->code, $this->applies_to_plans, true) => 'This coupon does not apply to the '.$plan->name.' plan.',
            default => null,
        };
    }

    public function discountOn(float $amount): float
    {
        $discount = $this->discount_type === 'percent'
            ? $amount * (float) $this->discount_value / 100
            : (float) $this->discount_value;

        return round(min($amount, max(0, $discount)), 2);
    }

    public function label(): string
    {
        $value = $this->discount_type === 'percent'
            ? rtrim(rtrim(number_format((float) $this->discount_value, 2), '0'), '.').'% off'
            : '$'.number_format((float) $this->discount_value, 2).' off';

        return $value.($this->duration === 'forever' ? ' every invoice' : ' first invoice');
    }
}
