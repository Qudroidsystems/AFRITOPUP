<?php

namespace App\Support;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** Inline SVG QR codes (bacon/bacon-qr-code). */
class Qr
{
    public static function svg(string $text, int $size = 160): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd())))->writeString($text);

        // Drop the XML prolog so it can sit inside HTML.
        return preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    }
}
