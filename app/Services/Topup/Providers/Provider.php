<?php

namespace App\Services\Topup\Providers;

use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;

/**
 * A VTU or bill-payment aggregator. Add one by implementing this interface and
 * registering it in ProviderManager::DRIVERS; then pick it on a service under
 * Services & Pricing. Implementations must never throw for an ordinary
 * refusal — return ProviderResult::failed() — and should return processing()
 * when they cannot tell (timeouts), so the purchase is re-checked, not refunded.
 */
interface Provider
{
    public function key(): string;

    public function name(): string;

    /** Are the credentials in place? */
    public function configured(): bool;

    public function purchase(Transaction $transaction): ProviderResult;

    /** Ask again about a purchase that is still processing. */
    public function requery(Transaction $transaction): ProviderResult;

    /**
     * Check a smartcard / meter / profile ID before paying.
     * @return array{valid: bool, name: ?string, details: array, message: ?string}
     */
    public function verify(Service $service, string $customerId, ?Product $product = null, array $extra = []): array;

    /**
     * The provider's plans for a service, to import into `products`.
     * @return array<int, array{code: string, name: string, amount: string, validity: ?string}>
     */
    public function products(Service $service): array;

    /** Our float with the provider, or null when it can't be read. */
    public function balance(): ?float;
}
