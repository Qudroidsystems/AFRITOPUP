<?php

namespace App\Support;

/** Brand lines shared by the web pages, e-mails, PDFs and the app config. */
class Brand
{
    /** "Pericharge is a product of Peridote Global Limited." */
    public static function productOf(): string
    {
        $name = (string) (config('afritopup.name') ?: 'Pericharge');
        $owner = (string) (config('afritopup.owner') ?: 'Peridote Global Limited');

        return $name . ' is a product of ' . $owner . '.';
    }

    /** The two coloured halves of the logotype: ["Peri", "charge"]. */
    public static function wordmark(?string $name = null): array
    {
        $name = trim($name ?? (string) (config('afritopup.name') ?: 'Pericharge'));
        $w = (array) config('afritopup.wordmark', []);
        if (count($w) === 2 && implode('', $w) === $name) return array_values($w);
        preg_match('/^(.[^A-Z]*)(.*)$/u', $name, $m);

        return [$m[1] ?? $name, $m[2] ?? ''];
    }
}
