<?php

namespace App\Http\Controllers\Api\V1;

/**
 * Public: the brand's name, logo and colours, the service categories and the
 * countries open to customers, so the app can brand itself before sign-in.
 */
class AppConfigController extends ApiController
{
    public function show()
    {
        $b = config('afritopup');

        return $this->ok([
            'name'          => $b['name'],
            'company'       => $b['company'],
            'tagline'       => $b['tagline'],
            'logo_url'      => !empty($b['logo']) ? asset($b['logo']) : null,
            'logo_light_url'=> !empty($b['logo_light']) ? asset($b['logo_light']) : null,
            'primary_color' => $b['primary_color'],
            'accent_color'  => $b['accent_color'] ?? null,
            'allow_registration' => (bool) ($b['allow_registration'] ?? false),
            'categories'    => collect($b['categories'] ?? [])->map(fn ($c, $key) => ['key' => $key, 'name' => $c['name'], 'color' => $c['color']])->values(),
            'countries'     => rescue(fn () => \App\Models\Country::active()->ordered()->get(['code', 'name', 'currency', 'dial_code', 'flag']), [], false),
            'website'       => $b['website'],
            'support_email' => $b['support_email'],
            'support_phone' => $b['support_phone'],
            'api_version'   => 1,
            'modules'       => collect($b['modules'] ?? [])->map(fn ($m, $key) => [
                'key' => $key, 'name' => $m['name'], 'phase' => $m['phase'] ?? null,
            ])->values(),
        ]);
    }
}
