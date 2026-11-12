<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'platform_plans';

    protected $fillable = ['code', 'name', 'description', 'tagline', 'monthly_price', 'annual_price', 'currency', 'trial_days', 'max_users', 'is_active', 'is_featured', 'is_custom', 'sort_order'];

    protected $casts = ['monthly_price' => 'decimal:2', 'annual_price' => 'decimal:2', 'is_active' => 'boolean', 'is_featured' => 'boolean', 'is_custom' => 'boolean'];

    public function features()
    {
        return $this->hasMany(PlanFeature::class, 'platform_plan_id');
    }

    public function hasFeature(string $key): bool
    {
        return $this->features->contains('feature_key', $key);
    }

    /** Null = unlimited. */
    public function limit(string $key): ?int
    {
        $feature = $this->features->firstWhere('feature_key', $key);

        return $feature?->limit_value;
    }

    /** Lowest-priced active plan that includes a feature (for upgrade prompts). */
    public static function cheapestWith(string $feature): ?self
    {
        return static::with('features')->where('is_active', true)->orderBy('sort_order')->get()->first(fn (self $plan) => $plan->hasFeature($feature));
    }

    public function priceFor(string $cycle): float
    {
        return (float) ($cycle === 'annual' ? $this->annual_price : $this->monthly_price);
    }
}
