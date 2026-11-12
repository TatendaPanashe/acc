<?php

namespace App\Support;

/** Number formatting for the platform dashboard. */
class PlatformFormat
{
    public static function money(float $value, bool $cents = false, string $symbol = '$'): string
    {
        $sign = $value < 0 ? '-' : '';

        return $sign.$symbol.number_format(abs($value), $cents ? 2 : 0);
    }

    /** 1,284 / 12.9K / 4.2M */
    public static function compact(float $value, string $prefix = ''): string
    {
        $abs = abs($value);
        $sign = $value < 0 ? '-' : '';
        [$div, $suffix] = match (true) {
            $abs >= 1_000_000 => [1_000_000, 'M'],
            $abs >= 10_000 => [1_000, 'K'],
            default => [1, ''],
        };
        $number = $div === 1 ? number_format($abs) : rtrim(rtrim(number_format($abs / $div, 1), '0'), '.');

        return $sign.$prefix.$number.$suffix;
    }

    public static function initials(?string $name): string
    {
        return collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->join('') ?: '?';
    }

    /** "Nice" axis maximum and step for 4 intervals. */
    public static function axis(float $max, int $intervals = 4): array
    {
        if ($max <= 0) {
            return [$intervals, 1];
        }
        $raw = $max / $intervals;
        $magnitude = 10 ** floor(log10($raw));
        $step = collect([1, 2, 2.5, 5, 10])->map(fn ($m) => $m * $magnitude)->first(fn ($s) => $s >= $raw);

        return [$step * $intervals, $step];
    }
}
