<?php

namespace App\Services\Topup\Providers;

use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;

/**
 * Fulfilled by staff (school fees paid out to a school, or a biller with no
 * API yet). The purchase stays processing until someone marks it delivered
 * or refunds it on the transaction page.
 */
class ManualProvider implements Provider
{
    public function key(): string
    {
        return 'manual';
    }

    public function name(): string
    {
        return 'Manual (staff)';
    }

    public function configured(): bool
    {
        return true;
    }

    public function purchase(Transaction $t): ProviderResult
    {
        return ProviderResult::processing('Payment received. We will confirm when it has been delivered.');
    }

    public function requery(Transaction $t): ProviderResult
    {
        return ProviderResult::processing('Waiting for staff to confirm delivery.');
    }

    public function verify(Service $service, string $customerId, ?Product $product = null, array $extra = []): array
    {
        return ['valid' => true, 'name' => null, 'details' => [], 'message' => null];
    }

    public function products(Service $service): array
    {
        return [];
    }

    public function balance(): ?float
    {
        return null;
    }
}
