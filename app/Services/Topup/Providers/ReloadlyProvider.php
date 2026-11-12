<?php

namespace App\Services\Topup\Providers;

use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Services\Settings\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reloadly (https://docs.reloadly.com): airtime and data bundles for 800+
 * operators in 150+ countries — Ghana, Kenya, Uganda, South Africa …
 *
 *  - OAuth client-credentials token (cached until shortly before it expires).
 *  - A service's provider_code is the Reloadly operatorId.
 *  - Fixed-denomination operators (most data bundles) are imported as plans whose
 *    code is the local amount; range operators take a typed amount.
 *  - Amounts are sent in the operator's local currency (useLocalAmount), the same
 *    currency as the customer's wallet for that country.
 */
class ReloadlyProvider implements Provider
{
    public function __construct(protected ?Settings $settings = null)
    {
        $this->settings ??= app(Settings::class);
    }

    public function key(): string
    {
        return 'reloadly';
    }

    public function name(): string
    {
        return 'Reloadly';
    }

    protected function cfg(): array
    {
        return $this->settings->provider('reloadly');
    }

    public function configured(): bool
    {
        $c = $this->cfg();

        return !empty($c['client_id']) && !empty($c['client_secret']);
    }

    protected function live(): bool
    {
        return ($this->cfg()['mode'] ?? 'sandbox') === 'live';
    }

    protected function base(): string
    {
        return $this->live() ? 'https://topups.reloadly.com' : 'https://topups-sandbox.reloadly.com';
    }

    protected function token(): string
    {
        $c = $this->cfg();
        $key = 'reloadly-token:' . ($this->live() ? 'live' : 'sandbox') . ':' . md5((string) $c['client_id']);

        return Cache::remember($key, now()->addMinutes(50), function () use ($c) {
            $res = Http::acceptJson()->asJson()->timeout(20)->post('https://auth.reloadly.com/oauth/token', [
                'client_id' => $c['client_id'], 'client_secret' => $c['client_secret'],
                'grant_type' => 'client_credentials', 'audience' => $this->base(),
            ])->throw();

            return (string) $res->json('access_token');
        });
    }

    protected function http()
    {
        return Http::withToken($this->token())->withHeaders(['Accept' => 'application/com.reloadly.topups-v1+json'])->timeout(60)->connectTimeout(15);
    }

    public function purchase(Transaction $t): ProviderResult
    {
        if (!$this->configured()) return ProviderResult::failed('This service is not available right now.');
        $operatorId = (int) ($t->service->provider_code ?? 0);
        if (!$operatorId) return ProviderResult::failed('This service is not set up yet.');

        $amount = $t->product ? (float) $t->product->code : (float) ($t->cost ?? $t->amount);
        try {
            $res = $this->http()->asJson()->post($this->base() . '/topups', [
                'operatorId'       => $operatorId,
                'amount'           => $amount,
                'useLocalAmount'   => true,
                'customIdentifier' => $t->reference,
                'recipientPhone'   => ['countryCode' => $t->country_code, 'number' => $t->recipient],
            ]);
        } catch (ConnectionException $e) {
            Log::warning('Reloadly /topups unreachable', ['ref' => $t->reference]);

            return ProviderResult::processing('Waiting for the network to confirm.');
        }

        return $this->read($res->json() ?? [], $res->status());
    }

    public function requery(Transaction $t): ProviderResult
    {
        if (!$t->provider_reference) {
            // Never reached Reloadly with an id: look it up by our reference.
            try {
                $list = $this->http()->get($this->base() . '/topups/reports/transactions', ['customIdentifier' => $t->reference, 'size' => 1])->json('content') ?? [];
            } catch (\Throwable $e) {
                return ProviderResult::processing('Still waiting for the network to confirm.');
            }
            if (!$list) {
                return $t->created_at->lt(now()->subMinutes(30))
                    ? ProviderResult::failed('The network did not receive this request.')
                    : ProviderResult::processing('Still waiting for the network to confirm.');
            }

            return $this->read((array) $list[0], 200);
        }
        try {
            $res = $this->http()->get($this->base() . '/topups/' . rawurlencode($t->provider_reference) . '/status');
        } catch (ConnectionException $e) {
            return ProviderResult::processing('Still waiting for the network to confirm.');
        }

        return $this->read((array) ($res->json('transaction') ?? $res->json() ?? []), $res->status());
    }

    protected function read(array $j, int $http): ProviderResult
    {
        $status = strtoupper((string) ($j['status'] ?? ''));
        $id = isset($j['transactionId']) ? (string) $j['transactionId'] : null;
        $raw = array_intersect_key($j, array_flip(['transactionId', 'status', 'operatorTransactionId', 'requestedAmount', 'requestedAmountCurrencyCode', 'deliveredAmount', 'deliveredAmountCurrencyCode', 'discount', 'errorCode', 'message']));

        if ($status === 'SUCCESSFUL') {
            $pin = data_get($j, 'pinDetail.code');

            return ProviderResult::successful('Delivered.', $id, $pin ? 'PIN ' . $pin : null, null, $raw);
        }
        if (in_array($status, ['PENDING', 'PROCESSING'], true)) return ProviderResult::processing('Your request is being processed.', $id, $raw);
        if (in_array($status, ['FAILED', 'REFUNDED'], true)) return ProviderResult::failed('The network could not complete this top-up.', $id, $raw);
        if ($http >= 500 || $http === 0) return ProviderResult::processing('Waiting for the network to confirm.', $id, $raw);

        // 4xx with an errorCode: refused before anything was sent.
        $msg = (string) ($j['message'] ?? 'The top-up was refused.');
        if (in_array($j['errorCode'] ?? '', ['INSUFFICIENT_BALANCE', 'ACCOUNT_SUSPENDED'], true)) $msg = 'This service is temporarily unavailable. Please try again shortly.';

        return ProviderResult::failed($msg, $id, $raw);
    }

    public function verify(Service $service, string $customerId, ?Product $product = null, array $extra = []): array
    {
        return ['valid' => true, 'name' => null, 'details' => [], 'message' => null];
    }

    /** Fixed amounts of the service's operator, as plans (code = local amount). */
    public function products(Service $service): array
    {
        $op = $this->operator((int) $service->provider_code);
        if (!$op || ($op['denominationType'] ?? '') !== 'FIXED') return [];
        $desc = (array) ($op['localFixedAmountsDescriptions'] ?? $op['fixedAmountsDescriptions'] ?? []);

        return collect((array) ($op['localFixedAmounts'] ?? []))->map(function ($amount) use ($desc, $op) {
            $key = number_format((float) $amount, 2, '.', '');
            $name = $desc[$key] ?? $desc[(string) $amount] ?? null;

            return [
                'code'     => rtrim(rtrim($key, '0'), '.'),
                'name'     => trim(($op['name'] ?? '') . ' ' . ($name ?: ($op['destinationCurrencySymbol'] ?? '') . $key)),
                'amount'   => (string) $amount,
                'validity' => $name && preg_match('/(\d+\s*(?:day|days|hrs|hours|week|weeks|month|months))/i', $name, $m) ? strtolower($m[1]) : null,
            ];
        })->values()->all();
    }

    public function operator(int $id): ?array
    {
        if (!$id || !$this->configured()) return null;

        return Cache::remember('reloadly-op:' . $id, now()->addHours(6), fn () => $this->http()->get($this->base() . '/operators/' . $id)->json());
    }

    /** Every operator in a country, to import as services. */
    public function operators(string $iso): array
    {
        return (array) $this->http()->get($this->base() . '/operators/countries/' . strtoupper($iso), ['includeBundles' => 'true', 'includeData' => 'true', 'includePin' => 'false'])->throw()->json();
    }

    public function balance(): ?float
    {
        if (!$this->configured()) return null;
        try {
            $b = $this->http()->get($this->base() . '/accounts/balance')->json('balance');
        } catch (\Throwable $e) {
            return null;
        }

        return $b !== null ? (float) $b : null;
    }
}
