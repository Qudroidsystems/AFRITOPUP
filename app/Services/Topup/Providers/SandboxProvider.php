<?php

namespace App\Services\Topup\Providers;

use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;

/**
 * A pretend provider for development and demos — nothing leaves the server.
 * Recipients ending in 0000 fail; ending in 1111 stay processing until the
 * first requery, which then succeeds.
 */
class SandboxProvider implements Provider
{
    public function key(): string
    {
        return 'sandbox';
    }

    public function name(): string
    {
        return 'Sandbox (test)';
    }

    public function configured(): bool
    {
        return true;
    }

    public function purchase(Transaction $t): ProviderResult
    {
        $digits = preg_replace('/\D+/', '', (string) $t->recipient);
        $ref = 'SBX' . $t->id . strtoupper(substr(md5($t->reference), 0, 6));
        if (str_ends_with($digits, '0000')) return ProviderResult::failed('The provider declined this request (sandbox).', $ref, ['sandbox' => true]);
        if (str_ends_with($digits, '1111')) return ProviderResult::processing('Waiting for the network (sandbox).', $ref, ['sandbox' => true]);

        return ProviderResult::successful('Delivered (sandbox).', $ref, $this->token($t), (string) $t->cost, ['sandbox' => true]);
    }

    public function requery(Transaction $t): ProviderResult
    {
        return ProviderResult::successful('Delivered (sandbox).', $t->provider_reference, $this->token($t), (string) $t->cost, ['sandbox' => true]);
    }

    public function verify(Service $service, string $customerId, ?Product $product = null, array $extra = []): array
    {
        $digits = preg_replace('/\D+/', '', $customerId);
        if (strlen($digits) < 6 || str_ends_with($digits, '0000')) {
            return ['valid' => false, 'name' => null, 'details' => [], 'message' => 'We could not find that number (sandbox).'];
        }

        return ['valid' => true, 'name' => 'Test Customer ' . substr($digits, -4), 'details' => ['address' => '12 Sandbox Close, Lagos'], 'message' => null];
    }

    public function products(Service $service): array
    {
        return [];
    }

    public function balance(): ?float
    {
        return 1000000.0;
    }

    protected function token(Transaction $t): ?string
    {
        if ($t->category === 'electricity' && ($t->meta['meter_type'] ?? 'prepaid') === 'prepaid') {
            return implode('-', str_split(str_pad((string) random_int(0, 99999999), 20, (string) random_int(1, 9), STR_PAD_LEFT), 4));
        }
        if ($t->category === 'education' && $t->service?->code !== 'school-fees') {
            return collect(range(1, max(1, $t->quantity)))->map(fn () => 'PIN ' . random_int(100000000000, 999999999999) . ' / SN WRN' . random_int(10000000, 99999999))->implode("\n");
        }

        return null;
    }
}
