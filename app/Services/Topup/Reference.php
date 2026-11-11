<?php

namespace App\Services\Topup;

use Illuminate\Support\Str;

/** AFT-20261111-8F3K2Q9X: prefix, Lagos date, 8 random characters. */
class Reference
{
    public static function make(?string $prefix = null): string
    {
        $prefix ??= (string) config('afritopup.reference_prefix', 'AFT');

        return $prefix . '-' . now('Africa/Lagos')->format('Ymd') . '-' . strtoupper(Str::random(8));
    }
}
