<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

/** Nigeria is live; the other markets are set up but hidden until their services are ready. */
class CountrySeeder extends Seeder
{
    public const COUNTRIES = [
        ['NG', 'Nigeria',      'NGN', '234', '^0[789][01]\d{8}$', '🇳🇬', true],
        ['GH', 'Ghana',        'GHS', '233', '^0[235]\d{8}$',     '🇬🇭', false],
        ['KE', 'Kenya',        'KES', '254', '^0[17]\d{8}$',      '🇰🇪', false],
        ['UG', 'Uganda',       'UGX', '256', '^07\d{8}$',         '🇺🇬', false],
        ['TZ', 'Tanzania',     'TZS', '255', '^0[67]\d{8}$',      '🇹🇿', false],
        ['ZA', 'South Africa', 'ZAR', '27',  '^0[678]\d{8}$',     '🇿🇦', false],
    ];

    public function run(): void
    {
        foreach (self::COUNTRIES as $i => [$code, $name, $currency, $dial, $pattern, $flag, $active]) {
            $c = Country::firstOrNew(['code' => $code]);
            $isNew = !$c->exists;
            $c->fill(['name' => $name, 'currency' => $currency, 'dial_code' => $dial, 'phone_pattern' => $pattern, 'flag' => $flag, 'position' => $i]);
            if ($isNew) $c->is_active = $active;   // never switch a live market off on re-seed
            $c->save();
        }
    }
}
