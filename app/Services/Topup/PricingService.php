<?php

namespace App\Services\Topup;

use App\Models\PriceTier;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Support\Money;

/**
 * What a purchase costs a given user: the list price (product price × quantity,
 * or the amount typed), less their tier's discount on the service, plus the
 * service's flat fee. All in minor units.
 */
class PricingService
{
    /** @return array{amount: string, discount: string, fee: string, total: string, cost: string} major-unit strings */
    public function quote(User $user, Service $service, ?Product $product, string|float|int|null $amount, int $quantity = 1): array
    {
        $quantity = max(1, $quantity);
        if ($product) {
            $base = Money::minor($product->price) * $quantity;
            $cost = Money::minor($product->cost) * $quantity;
        } else {
            $base = Money::minor($amount ?? 0) * $quantity;
            $cost = $base;
        }

        $discount = $this->discount($user->tier(), $service, $base);
        $fee = Money::minor($service->fee ?? 0);
        $total = max(0, $base - $discount + $fee);

        return [
            'amount'   => Money::major($base),
            'discount' => Money::major($discount),
            'fee'      => Money::major($fee),
            'total'    => Money::major($total),
            'cost'     => Money::major($cost),
        ];
    }

    /** Discount in minor units for this tier on this service, never more than the base. */
    public function discount(?PriceTier $tier, Service $service, int $baseMinor): int
    {
        if (!$tier || $baseMinor <= 0) return 0;
        $rule = $service->relationLoaded('discounts')
            ? $service->discounts->firstWhere('price_tier_id', $tier->id)
            : $service->discounts()->where('price_tier_id', $tier->id)->first();
        if (!$rule || (float) $rule->value <= 0) return 0;

        $off = $rule->type === 'flat'
            ? Money::minor($rule->value)
            : intdiv($baseMinor * (int) round((float) $rule->value * 100) + 5000, 10000);   // percent with 2 decimals, rounded

        return min($off, $baseMinor);
    }
}
