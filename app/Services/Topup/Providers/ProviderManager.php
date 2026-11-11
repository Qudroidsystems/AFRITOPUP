<?php

namespace App\Services\Topup\Providers;

use App\Models\Product;
use App\Models\Service;
use InvalidArgumentException;

/** Finds the provider for a service, and imports a provider's plans into the catalogue. */
class ProviderManager
{
    public const DRIVERS = [
        'sandbox' => SandboxProvider::class,
        'vtpass'  => VtpassProvider::class,
        'termii'  => TermiiProvider::class,
        'manual'  => ManualProvider::class,
    ];

    public function get(string $key): Provider
    {
        $class = self::DRIVERS[$key] ?? null;
        if (!$class) throw new InvalidArgumentException("Unknown provider [{$key}].");

        return app($class);
    }

    public function for(Service $service): Provider
    {
        return $this->get($service->provider);
    }

    /** key => name, for select boxes. */
    public function options(): array
    {
        return collect(self::DRIVERS)->mapWithKeys(fn ($c, $k) => [$k => $this->get($k)->name()])->all();
    }

    /**
     * Pull the provider's plans for a service. New plans are added at the provider's price
     * (switched off when `$activateNew` is false); existing plans get the new cost, and their
     * selling price is raised to the cost if it would otherwise sell at a loss.
     *
     * @return array{added: int, updated: int, retired: int, total: int}
     */
    public function sync(Service $service, bool $activateNew = true): array
    {
        $added = $updated = 0;
        $list = $this->for($service)->products($service);
        foreach ($list as $i => $row) {
            $product = Product::firstOrNew(['service_id' => $service->id, 'code' => $row['code']]);
            $isNew = !$product->exists;
            $product->fill([
                'name'     => $isNew ? $row['name'] : $product->name,
                'cost'     => $row['amount'],
                'validity' => $row['validity'] ?? $product->validity,
                'position' => $isNew ? $i : $product->position,
            ]);
            if ($isNew) {
                $product->price = $row['amount'];
                $product->is_active = $activateNew;
                $added++;
            } else {
                if ((float) $product->price < (float) $row['amount']) $product->price = $row['amount'];
                if ($product->isDirty()) $updated++;
            }
            $product->save();
        }

        // Plans the provider no longer offers are switched off (kept for old receipts).
        $retired = 0;
        if ($list) {
            $retired = Product::where('service_id', $service->id)->where('is_active', true)
                ->whereNotIn('code', array_column($list, 'code'))->update(['is_active' => false]);
        }

        return ['added' => $added, 'updated' => $updated, 'retired' => $retired, 'total' => count($list)];
    }
}
