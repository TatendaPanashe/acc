<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\ExchangeRate;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Converts transaction-currency amounts to the company's base currency.
 * Uses the most recent rate on or before the transaction date.
 */
class ExchangeRateService
{
    /** @var array<int, string> */
    private array $baseCache = [];

    public function baseCurrency(int $companyId): string
    {
        return $this->baseCache[$companyId] ??= Currency::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_base', true)
            ->value('code') ?? 'USD';
    }

    /** Units of base currency for one unit of $currency on $date. */
    public function rate(string $currency, int $companyId, mixed $date): float
    {
        $currency = strtoupper($currency);
        $base = $this->baseCurrency($companyId);

        if ($currency === $base) {
            return 1.0;
        }

        $day = Carbon::parse($date)->toDateString();
        $query = fn (string $from, string $to) => ExchangeRate::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('from_currency', $from)
            ->where('to_currency', $to)
            ->whereDate('rate_date', '<=', $day)
            ->orderByDesc('rate_date')
            ->value('rate');

        if ($direct = (float) $query($currency, $base)) {
            return $direct;
        }

        if ($inverse = (float) $query($base, $currency)) {
            return 1 / $inverse;
        }

        throw ValidationException::withMessages([
            'currency' => "No exchange rate from {$currency} to {$base} on or before ".Carbon::parse($date)->format('d M Y').'. Add one under Accounting Settings → Exchange Rates.',
        ]);
    }

    public function toBase(float $amount, float $rate): float
    {
        return round($amount * $rate, 2);
    }
}
