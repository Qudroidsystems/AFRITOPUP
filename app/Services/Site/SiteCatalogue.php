<?php

namespace App\Services\Site;

use App\Models\Country;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\Topup\PricingService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * What the public website shows: the live catalogue per country, priced at
 * the everyday (default tier) price a new customer pays. Cached briefly so
 * the pages stay fast; admin changes show within a few minutes.
 */
class SiteCatalogue
{
    public const TTL = 300;

    /** Phone prefixes → network, so the widget can pick the network as you type (Nigeria). */
    public const PREFIXES = [
        'NG' => [
            'mtn'     => ['0803', '0806', '0703', '0706', '0810', '0813', '0814', '0816', '0903', '0906', '0913', '0916', '0704', '07025', '07026'],
            'airtel'  => ['0802', '0808', '0708', '0701', '0812', '0901', '0902', '0904', '0907', '0912', '0911'],
            'glo'     => ['0805', '0807', '0705', '0811', '0815', '0905', '0915'],
            '9mobile' => ['0809', '0817', '0818', '0908', '0909'],
        ],
    ];

    public function __construct(protected PricingService $pricing) {}

    public function country(?string $code): Country
    {
        $code = strtoupper((string) $code);

        return ($code ? Country::active()->where('code', $code)->first() : null)
            ?? Country::active()->where('code', config('afritopup.default_country', 'NG'))->first()
            ?? Country::active()->ordered()->first()
            // Nothing seeded yet: still show the site.
            ?? new Country(['code' => config('afritopup.default_country', 'NG'), 'name' => 'Nigeria', 'currency' => 'NGN', 'dial_code' => '234', 'flag' => '🇳🇬']);
    }

    public function countries(): Collection
    {
        return Cache::remember('site.countries', self::TTL, fn () => Country::active()->ordered()->get(['code', 'name', 'currency', 'dial_code', 'flag']));
    }

    /** Categories with their services, for the quick-pay widget and the service pages. */
    public function catalogue(Country $country): array
    {
        return Cache::remember("site.catalogue.{$country->code}", self::TTL, function () use ($country) {
            $services = Service::active()->in($country->code)->ordered()->get()->groupBy('category');

            return [
                'country'    => $country->only(['code', 'name', 'currency', 'dial_code', 'flag']),
                'symbol'     => Money::symbol($country->currency),
                'prefixes'   => self::PREFIXES[$country->code] ?? (object) [],
                'categories' => collect(config('afritopup.categories'))->filter(fn ($c, $k) => $services->has($k))->map(fn ($c, $k) => [
                    'key' => $k, 'name' => $c['name'], 'icon' => $c['icon'], 'color' => $c['color'],
                    'recipient_label' => $c['recipient_label'], 'url' => route('site.service', $k),
                    'services' => $services[$k]->map(fn (Service $s) => [
                        'id' => $s->id, 'code' => $s->code, 'name' => $s->name, 'short' => $s->display_name, 'color' => $s->color ?: $c['color'], 'ink' => \App\Support\Color::ink($s->color ?: $c['color']),
                        'logo' => $s->logo_url, 'needs_product' => $s->needsProduct(), 'needs_amount' => $s->needsAmount(),
                        'recipient_label' => $s->recipientLabel(),
                        'min' => $s->min_amount !== null ? (float) $s->min_amount : null,
                        'max' => $s->max_amount !== null ? (float) $s->max_amount : null,
                    ])->values()->all(),
                ])->values()->all(),
            ];
        });
    }

    /** A service's plans at the price a new customer pays. */
    public function plans(Service $service): array
    {
        return Cache::remember("site.plans.{$service->id}", self::TTL, function () use ($service) {
            $guest = new User(['user_type' => User::TYPE_CUSTOMER]);
            $currency = $service->country?->currency ?? 'NGN';

            return $service->products()->active()->get()->map(function (Product $p) use ($guest, $service, $currency) {
                $q = $this->pricing->quote($guest, $service, $p, null);

                return ['id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'validity' => $p->validity,
                    'price' => (float) $q['total'], 'label' => Money::format($q['total'], $currency)];
            })->values()->all();
        });
    }

    /** Real numbers for the stats band. */
    public function stats(): array
    {
        return Cache::remember('site.stats', self::TTL, fn () => [
            'billers'    => Service::active()->count(),
            'plans'      => Product::where('is_active', true)->whereHas('service', fn ($q) => $q->where('is_active', true))->count(),
            'countries'  => Country::active()->count(),
            'categories' => Service::active()->distinct()->count('category'),
        ]);
    }

    /** Tiles for the "works with" strip. */
    public function billers(Country $country): Collection
    {
        return Cache::remember("site.billers.{$country->code}", self::TTL, fn () => Service::active()->in($country->code)
            ->whereIn('category', ['airtime', 'cable', 'electricity', 'education'])->ordered()->get()
            ->unique(fn ($s) => $s->display_name)->values());
    }
}
