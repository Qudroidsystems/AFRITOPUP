<?php

namespace App\Services\Topup\Providers;

use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Services\Settings\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Finds the vendor (provider) for a service or a purchase, imports a vendor's plans into the
 * catalogue, and moves services from one vendor to another.
 *
 * Built-in vendors are listed in DRIVERS. Custom vendors (any site on the common VTU API) are
 * added by admins and have keys "custom-<slug>".
 */
class ProviderManager
{
    public const DRIVERS = [
        'sandbox'     => SandboxProvider::class,
        'vtpass'      => VtpassProvider::class,
        'clubkonnect' => ClubKonnectProvider::class,
        'termii'      => TermiiProvider::class,
        'reloadly'    => ReloadlyProvider::class,
        'manual'      => ManualProvider::class,
    ];

    /** What each built-in vendor can sell. Custom vendors: CustomVtuProvider::categories(). */
    public const CATEGORIES = [
        'sandbox'     => ['airtime', 'data', 'cable', 'electricity', 'sms', 'education'],
        'vtpass'      => ['airtime', 'data', 'cable', 'electricity', 'education'],
        'clubkonnect' => ['airtime', 'data', 'cable', 'electricity', 'education'],
        'termii'      => ['sms'],
        'reloadly'    => ['airtime', 'data'],
        'manual'      => ['airtime', 'data', 'cable', 'electricity', 'sms', 'education'],
    ];

    public function __construct(protected ?Settings $settings = null)
    {
        $this->settings ??= app(Settings::class);
    }

    public function get(string $key): Provider
    {
        if (str_starts_with($key, 'custom-')) {
            $slug = substr($key, 7);
            if ($this->settings->customVendor($slug) === null) throw new InvalidArgumentException("Unknown vendor [{$key}].");

            return new CustomVtuProvider($slug, $this->settings);
        }
        $class = self::DRIVERS[$key] ?? null;
        if (!$class) throw new InvalidArgumentException("Unknown provider [{$key}].");

        return app($class);
    }

    public function exists(string $key): bool
    {
        return isset(self::DRIVERS[$key]) || (str_starts_with($key, 'custom-') && $this->settings->customVendor(substr($key, 7)) !== null);
    }

    public function for(Service $service): Provider
    {
        return $this->get($service->provider);
    }

    /**
     * The vendor a purchase was sent to. A purchase stays with its vendor even after the
     * service is switched to another one, so re-checks and refunds go to the right place.
     */
    public function forTransaction(Transaction $t): Provider
    {
        return $this->get($t->provider ?: $t->service->provider);
    }

    /** Every vendor key: built-in first, then custom ones. */
    public function keys(): array
    {
        return array_merge(array_keys(self::DRIVERS), array_map(fn ($s) => 'custom-' . $s, array_keys($this->settings->customVendors())));
    }

    /** key => name, for select boxes (optionally only vendors that sell a category). */
    public function options(?string $category = null): array
    {
        return collect($this->keys())
            ->filter(fn ($k) => !$category || in_array($category, $this->categories($k), true))
            ->mapWithKeys(fn ($k) => [$k => $this->get($k)->name()])->all();
    }

    public function categories(string $key): array
    {
        return str_starts_with($key, 'custom-') ? CustomVtuProvider::categories() : (self::CATEGORIES[$key] ?? []);
    }

    /** Vendors that hold money with us (not sandbox/manual) and have keys, with their balance (cached 5 min). */
    public function balances(): array
    {
        return Cache::remember('provider-balances:v2', 300, function () {
            $out = [];
            foreach ($this->keys() as $k) {
                if (in_array($k, ['sandbox', 'manual'], true)) continue;
                $p = $this->get($k);
                if (!$p->configured()) continue;
                $out[$k] = ['name' => $p->name(), 'balance' => rescue(fn () => $p->balance(), null, false)];
            }

            return $out;
        });
    }

    /**
     * Pull the vendor's plans for a service. Each plan is matched to an existing one — by its code at
     * this vendor, then the same code, then (data) the same size and validity, or (cable) the same name —
     * and the vendor's code is remembered on it. Matched plans get the vendor's cost; their selling price
     * is raised to the cost if it would otherwise sell at a loss. Unmatched plans are added at cost plus
     * `$markup` percent (switched off when `$activateNew` is false). Plans the vendor does not offer are
     * switched off (kept for old receipts).
     *
     * @return array{added: int, updated: int, retired: int, total: int}
     */
    public function sync(Service $service, bool $activateNew = true, float $markup = 0.0): array
    {
        $vendor = $service->provider;
        $list = $this->for($service)->products($service);
        $existing = Product::where('service_id', $service->id)->get();
        $added = $updated = 0;
        $seen = [];

        foreach ($list as $i => $row) {
            $row['code'] = (string) $row['code'];
            $product = $this->match($existing, $row, $vendor, $service->category, $seen);
            $isNew = $product === null;
            if ($isNew) {
                $code = $existing->contains('code', $row['code']) || Product::where('service_id', $service->id)->where('code', $row['code'])->exists()
                    ? substr($vendor . ':' . $row['code'], 0, 80) : $row['code'];
                $product = new Product(['service_id' => $service->id, 'code' => $code, 'name' => $row['name'], 'position' => $i]);
                $product->price = round((float) $row['amount'] * (1 + max(0, $markup) / 100), 2);
                $product->is_active = $activateNew;
                $added++;
            }
            $product->cost = $row['amount'];
            if (!empty($row['validity'])) $product->validity = $row['validity'];
            $product->setCodeFor($vendor, $row['code'] === $product->code ? null : $row['code']);
            if (!$isNew) {
                if ((float) $product->price < (float) $row['amount']) $product->price = $row['amount'];
                if ($product->isDirty()) $updated++;
            }
            $product->save();
            $seen[$product->id] = true;
            if ($isNew) $existing->push($product);
        }

        // Plans this vendor does not offer are switched off (kept for old receipts).
        $retired = 0;
        if ($list) {
            foreach ($existing as $p) {
                if ($p->is_active && !isset($seen[$p->id])) {
                    $p->forceFill(['is_active' => false])->save();
                    $retired++;
                }
            }
        }

        return ['added' => $added, 'updated' => $updated, 'retired' => $retired, 'total' => count($list)];
    }

    protected function match($existing, array $row, string $vendor, string $category, array $seen): ?Product
    {
        $free = $existing->reject(fn ($p) => isset($seen[$p->id]));
        $hit = $free->first(fn ($p) => $p->hasCodeFor($vendor) && $p->codeFor($vendor) === $row['code'])
            ?? $free->first(fn ($p) => !$p->hasCodeFor($vendor) && $p->code === $row['code']);
        if ($hit) return $hit;

        if ($category === 'data' && ($mb = NetworkCodes::dataSize($row['name']))) {
            $days = NetworkCodes::validityDays($row['validity'] ?? $row['name']);
            $same = $free->filter(fn ($p) => !$p->hasCodeFor($vendor) && NetworkCodes::dataSize($p->name) === $mb
                && NetworkCodes::validityDays($p->validity ?: $p->name) === $days);
            if ($same->count() >= 1) return $same->sortBy(fn ($p) => abs((float) $p->cost - (float) $row['amount']))->first();
        }
        if ($category === 'cable') {
            $norm = fn ($s) => preg_replace('/[^a-z]/', '', strtolower(preg_replace('/(n|₦|ngn)?\s?[\d,]+(\.\d+)?/iu', '', (string) $s)));
            $key = $norm($row['name']);
            if ($key !== '') return $free->first(fn ($p) => !$p->hasCodeFor($vendor) && $norm($p->name) === $key);
        }

        return null;
    }

    /**
     * Move every service of a category in a country to another vendor (school fees and other
     * staff-fulfilled services stay as they are). Plans are imported from the new vendor when asked.
     *
     * @return array{moved: int, synced: int, added: int, retired: int, errors: array<string, string>}
     */
    public function switchVendor(string $category, string $country, string $vendor, bool $syncPlans = true, float $markup = 0.0, bool $activateNew = false): array
    {
        if (!$this->exists($vendor)) throw new InvalidArgumentException("Unknown vendor [{$vendor}].");
        if (!in_array($category, $this->categories($vendor), true)) throw new InvalidArgumentException($this->get($vendor)->name() . " does not sell {$category}.");

        $services = Service::where('category', $category)->where('country_code', strtoupper($country))
            ->where('provider', '!=', 'manual')->get();
        $out = ['moved' => 0, 'synced' => 0, 'added' => 0, 'retired' => 0, 'errors' => []];
        foreach ($services as $s) {
            if ($s->provider !== $vendor) {
                $s->forceFill(['provider' => $vendor])->save();
                $out['moved']++;
            }
            if ($syncPlans && $s->needsProduct() && !in_array($vendor, ['sandbox', 'manual'], true)) {
                try {
                    $r = DB::transaction(fn () => $this->sync($s, $activateNew, $markup));
                    $out['synced']++;
                    $out['added'] += $r['added'];
                    $out['retired'] += $r['retired'];
                } catch (\Throwable $e) {
                    $out['errors'][$s->name] = $e->getMessage();
                }
            }
        }
        Cache::forget('site.catalogue.' . strtoupper($country));

        return $out;
    }

    /** category => [vendor key => number of services], for the routing table. */
    public function routing(string $country): array
    {
        return Service::where('country_code', strtoupper($country))->selectRaw('category, provider, COUNT(*) as n')
            ->groupBy('category', 'provider')->get()->groupBy('category')
            ->map(fn ($rows) => $rows->pluck('n', 'provider')->all())->all();
    }
}
