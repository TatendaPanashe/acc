<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

/** Configured payment provider. Credentials are stored encrypted. */
class PaymentGateway extends Model
{
    protected $table = 'platform_payment_gateways';

    protected $fillable = ['provider', 'display_name', 'configuration', 'mode', 'status', 'sort_order'];

    protected $casts = ['configuration' => 'encrypted:array', 'status' => 'boolean'];

    protected $hidden = ['configuration'];

    public function config(string $key, mixed $default = null): mixed
    {
        return ($this->configuration ?? [])[$key] ?? $default;
    }

    public static function enabled()
    {
        return static::where('status', true)->orderBy('sort_order')->get();
    }
}
