<?php

namespace App\Support;

/**
 * Money helpers. Arithmetic is done in minor units (kobo, cents) as integers,
 * never in floats; the database stores decimals with two places.
 */
class Money
{
    public const SYMBOLS = ['NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€', 'GHS' => 'GH₵', 'KES' => 'KSh ', 'ZAR' => 'R', 'UGX' => 'USh ', 'TZS' => 'TSh ', 'XOF' => 'CFA ', 'RWF' => 'FRw '];

    public static function symbol(?string $currency): string
    {
        $currency = strtoupper((string) $currency);

        return self::SYMBOLS[$currency] ?? $currency . ' ';
    }

    public static function format($amount, ?string $currency = null): string
    {
        $currency = strtoupper($currency ?: config('afritopup.academy.currency', 'NGN'));
        $symbol = self::SYMBOLS[$currency] ?? $currency . ' ';

        return $symbol . number_format((float) $amount, 2);
    }

    /** "1500.50" → 150050 */
    public static function minor($amount): int
    {
        $s = trim((string) ($amount ?? '0'));
        $negative = str_starts_with($s, '-');
        [$whole, $frac] = array_pad(explode('.', ltrim($s, '-+')), 2, '0');
        $frac = substr(str_pad($frac, 3, '0'), 0, 3);
        $minor = ((int) $whole) * 100 + intdiv((int) $frac + 5, 10); // round half up on the third place

        return $negative ? -$minor : $minor;
    }

    /** 150050 → "1500.50" */
    public static function major(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $minor = abs($minor);

        return $sign . intdiv($minor, 100) . '.' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
