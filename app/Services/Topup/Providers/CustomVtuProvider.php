<?php

namespace App\Services\Topup\Providers;

use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Services\Settings\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Any vendor running the common Nigerian VTU API (the "Husmodata-style" API used by many
 * data-reseller sites): token auth, POST /api/topup/, /api/data/, /api/billpayment/, /api/cablesub/,
 * GET /api/user/ for the balance. Admins add as many of these as they like under
 * Settings → VTU vendors; each gets the key "custom-<slug>".
 *
 * Every site numbers networks, discos and TV providers its own way, so those ids are part of the
 * vendor's settings (with the usual defaults), and plan ids are imported or typed on each plan.
 */
class CustomVtuProvider implements Provider
{
    public const DEFAULT_IDS = [
        'network' => ['mtn' => '1', 'glo' => '2', '9mobile' => '3', 'airtel' => '4'],
        'cable'   => ['gotv' => '1', 'dstv' => '2', 'startimes' => '3'],
        'disco'   => ['ikeja' => '1', 'eko' => '2', 'abuja' => '3', 'kano' => '4', 'enugu' => '5', 'portharcourt' => '6',
            'ibadan' => '7', 'kaduna' => '8', 'jos' => '9', 'benin' => '10', 'yola' => '11', 'aba' => '12'],
    ];

    public function __construct(protected string $slug, protected ?Settings $settings = null)
    {
        $this->settings ??= app(Settings::class);
    }

    public static function categories(): array
    {
        return ['airtime', 'data', 'cable', 'electricity'];
    }

    public function key(): string
    {
        return 'custom-' . $this->slug;
    }

    public function name(): string
    {
        return (string) ($this->cfg()['name'] ?? ucfirst($this->slug));
    }

    public function cfg(): array
    {
        return $this->settings->customVendor($this->slug) ?? [];
    }

    public function configured(): bool
    {
        $c = $this->cfg();

        return !empty($c['base_url']) && !empty($c['token']);
    }

    protected function url(string $path): string
    {
        return rtrim((string) $this->cfg()['base_url'], '/') . '/' . ltrim($path, '/');
    }

    protected function http()
    {
        return Http::withHeaders(['Authorization' => 'Token ' . ($this->cfg()['token'] ?? '')])->acceptJson()->asJson()->timeout(90)->connectTimeout(15);
    }

    protected function id(string $type, ?string $what, Service $s): ?string
    {
        if ($pinned = NetworkCodes::pinned($s, $this->key())) return $pinned;
        if (!$what) return null;
        $map = array_merge(self::DEFAULT_IDS[$type] ?? [], array_filter((array) ($this->cfg()['ids'][$type] ?? []), 'strlen'));

        return $map[$what] ?? null;
    }

    public function purchase(Transaction $t): ProviderResult
    {
        if (!$this->configured()) return ProviderResult::failed('This service is not available right now.');
        $s = $t->service;
        $amount = round((float) ($t->cost ?? $t->amount));

        [$path, $body] = match ($t->category) {
            'airtime' => ['api/topup/', ['network' => $this->id('network', NetworkCodes::network($s), $s), 'amount' => $amount,
                'mobile_number' => $t->recipient, 'Ported_number' => true, 'airtime_type' => 'VTU']],
            'data' => ['api/data/', ['network' => $this->id('network', NetworkCodes::network($s), $s), 'mobile_number' => $t->recipient,
                'plan' => $t->product?->codeFor($this->key()), 'Ported_number' => true]],
            'cable' => ['api/cablesub/', ['cablename' => $this->id('cable', NetworkCodes::cable($s), $s), 'cableplan' => $t->product?->codeFor($this->key()),
                'smart_card_number' => $t->recipient]],
            'electricity' => ['api/billpayment/', ['disco_name' => $this->id('disco', NetworkCodes::disco($s), $s), 'amount' => $amount,
                'meter_number' => $t->recipient, 'MeterType' => ($t->meta['meter_type'] ?? 'prepaid') === 'postpaid' ? 2 : 1]],
            default => [null, []],
        };
        if (!$path || in_array(null, $body, true) || in_array('', $body, true)) {
            return ProviderResult::failed('This service is not available from the current vendor.');
        }

        try {
            $res = $this->http()->post($this->url($path), $body);
        } catch (ConnectionException $e) {
            Log::warning('Custom VTU vendor unreachable', ['vendor' => $this->key(), 'ref' => $t->reference]);

            return ProviderResult::processing('Waiting for the network to confirm.');
        }
        $t->forceFill(['meta' => array_merge((array) $t->meta, ['vendor_path' => $path])])->save();

        return $this->read($res);
    }

    public function requery(Transaction $t): ProviderResult
    {
        $path = $t->meta['vendor_path'] ?? null;
        if (!$t->provider_reference || !$path) {
            return $t->created_at->lt(now()->subMinutes(30))
                ? ProviderResult::failed('The vendor did not confirm this purchase.')
                : ProviderResult::processing('Still waiting for the network to confirm.');
        }
        try {
            $res = $this->http()->get($this->url($path . rawurlencode($t->provider_reference)));
        } catch (ConnectionException $e) {
            return ProviderResult::processing('Still waiting for the network to confirm.', $t->provider_reference);
        }

        return $this->read($res, $t->provider_reference);
    }

    protected function read(Response $res, ?string $ref = null): ProviderResult
    {
        $j = (array) $res->json();
        $status = strtolower((string) ($j['Status'] ?? $j['status'] ?? ''));
        $ref = isset($j['id']) ? (string) $j['id'] : ($j['ident'] ?? $ref);
        $raw = array_intersect_key($j, array_flip(['id', 'ident', 'Status', 'status', 'api_response', 'plan_amount', 'paid_amount', 'amount', 'balance_before', 'balance_after', 'error']));
        $msg = (string) ($j['api_response'] ?? $j['error'] ?? $j['message'] ?? $j['detail'] ?? '');

        if (in_array($status, ['successful', 'success', 'completed', 'delivered'], true)) {
            $cost = $j['paid_amount'] ?? $j['plan_amount'] ?? $j['amount'] ?? null;

            return ProviderResult::successful('Delivered.', $ref, $this->token($j), is_numeric($cost) ? (string) $cost : null, $raw);
        }
        if (in_array($status, ['failed', 'fail', 'reversed', 'refunded', 'cancelled'], true)) {
            return ProviderResult::failed($this->friendly($msg), $ref, $raw);
        }
        if ($res->status() >= 400 && $res->status() < 500 && !$ref) {
            // Refused before anything was created (bad plan, low balance, bad token …).
            return ProviderResult::failed($this->friendly($msg ?: (is_array($j) ? json_encode($j) : 'Refused')), null, $raw);
        }

        return ProviderResult::processing('Your request is being processed.', $ref, $raw);
    }

    protected function friendly(string $msg): string
    {
        if (preg_match('/balance|insufficient|token|credential|authenticat/i', $msg)) return 'This service is temporarily unavailable. Please try again shortly.';

        return $msg !== '' ? mb_strimwidth(strip_tags($msg), 0, 180, '…') : 'The purchase failed.';
    }

    protected function token(array $j): ?string
    {
        foreach (['token', 'Token', 'pin', 'pins'] as $k) {
            if (!empty($j[$k]) && is_string($j[$k])) return trim(preg_replace('/^Token\s*:\s*/i', '', $j[$k]));
        }

        return null;
    }

    public function verify(Service $service, string $customerId, ?Product $product = null, array $extra = []): array
    {
        if (!$this->configured()) return ['valid' => false, 'name' => null, 'details' => [], 'message' => 'Verification is not available right now.'];
        try {
            $res = match ($service->category) {
                'cable' => $this->http()->timeout(30)->get($this->url('api/validateiuc'), ['smart_card_number' => $customerId, 'cablename' => $this->id('cable', NetworkCodes::cable($service), $service)]),
                'electricity' => $this->http()->timeout(30)->get($this->url('api/validatemeter'), ['meternumber' => $customerId,
                    'disconame' => $this->id('disco', NetworkCodes::disco($service), $service), 'mtype' => ($extra['meter_type'] ?? 'prepaid') === 'postpaid' ? 2 : 1]),
                default => null,
            };
        } catch (ConnectionException $e) {
            return ['valid' => false, 'name' => null, 'details' => [], 'message' => 'Could not reach the provider. Please try again.'];
        }
        if ($res === null) return ['valid' => true, 'name' => null, 'details' => [], 'message' => null];
        $j = (array) $res->json();
        $name = trim((string) ($j['name'] ?? $j['Customer_Name'] ?? $j['customer_name'] ?? ''));
        $invalid = ($j['invalid'] ?? false) === true || $name === '' || preg_match('/invalid|error/i', $name);

        return $invalid
            ? ['valid' => false, 'name' => null, 'details' => [], 'message' => 'We could not find that number. Please check it.']
            : ['valid' => true, 'name' => $name, 'details' => array_filter(['address' => $j['address'] ?? null]), 'message' => null];
    }

    /** Best effort: many of these sites list their plans on GET /api/user/ ("Dataplans", "Cableplan"). */
    public function products(Service $service): array
    {
        if (!$this->configured() || !in_array($service->category, ['data', 'cable'], true)) return [];
        $j = (array) $this->http()->timeout(40)->get($this->url('api/user/'))->json();
        $rows = [];
        if ($service->category === 'data') {
            $netId = $this->id('network', NetworkCodes::network($service), $service);
            $net = strtolower((string) NetworkCodes::network($service));
            $walk = function ($node) use (&$walk, &$rows, $netId, $net) {
                if (!is_array($node)) return;
                if (isset($node['dataplan_id']) || (isset($node['id'], $node['plan'], $node['plan_amount']))) {
                    $pn = strtolower((string) ($node['plan_network'] ?? $node['network'] ?? ''));
                    if ($pn === (string) $netId || str_contains($pn, $net) || ($net === '9mobile' && str_contains($pn, 'etisalat'))) {
                        $name = trim(strtoupper($net) . ' ' . ($node['plan'] ?? '') . ' ' . ($node['plan_type'] ?? '') . ' ' . ($node['month_validate'] ?? ''));
                        $rows[] = ['code' => (string) ($node['dataplan_id'] ?? $node['id']), 'name' => preg_replace('/\s+/', ' ', $name),
                            'amount' => (string) ($node['plan_amount'] ?? '0'), 'validity' => $node['month_validate'] ?? null];
                    }

                    return;
                }
                foreach ($node as $v) $walk($v);
            };
            $walk($j['Dataplans'] ?? $j['dataplans'] ?? $j);
        } else {
            $cableId = $this->id('cable', NetworkCodes::cable($service), $service);
            $walk = function ($node) use (&$walk, &$rows, $cableId) {
                if (!is_array($node)) return;
                if (isset($node['cableplan_id'])) {
                    if ((string) ($node['cable'] ?? $node['cablename'] ?? '') === (string) $cableId) {
                        $rows[] = ['code' => (string) $node['cableplan_id'], 'name' => trim((string) ($node['package'] ?? '')), 'amount' => (string) ($node['plan_amount'] ?? '0'), 'validity' => null];
                    }

                    return;
                }
                foreach ($node as $v) $walk($v);
            };
            $walk($j['Cableplan'] ?? $j['cableplans'] ?? $j);
        }

        return array_values(array_filter($rows, fn ($r) => $r['code'] !== ''));
    }

    public function balance(): ?float
    {
        if (!$this->configured()) return null;
        try {
            $j = (array) $this->http()->timeout(20)->get($this->url('api/user/'))->json();
        } catch (\Throwable $e) {
            return null;
        }
        $b = data_get($j, 'user.wallet_balance') ?? data_get($j, 'user.Account_Balance') ?? $j['wallet_balance'] ?? $j['balance'] ?? null;

        return is_numeric($b) ? (float) $b : null;
    }
}
