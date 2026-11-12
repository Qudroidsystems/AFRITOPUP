<?php

namespace App\Support;

/** Colour helpers for brand tiles. */
class Color
{
    /** Dark or white text, whichever reads better on $hex (WCAG relative luminance). */
    public static function ink(?string $hex, string $dark = '#14201B', string $light = '#ffffff'): string
    {
        if (!preg_match('/^#?([0-9a-f]{6})$/i', (string) $hex, $m)) return $light;
        [$r, $g, $b] = array_map(fn ($c) => hexdec($c) / 255, str_split($m[1], 2));
        $lin = fn ($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $l = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);

        return $l > 0.45 ? $dark : $light;
    }
}
