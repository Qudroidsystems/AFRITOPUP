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
use Illuminate\Support\Str;

/**
 * VTpass (https://www.vtpass.com/documentation): airtime, data, cable TV,
 * electricity and exam PINs in Nigeria.
 *
 *  - POST requests send the api-key and secret-key headers; GET requests the api-key and public-key.
 *  - request_id must start with the Lagos date and time as YYYYMMDDHHII.
 *  - code 000 + transactions.status "delivered" = done; "pending"/"initiated" or code 099 = processing.
 *
 * A service's provider_code is the VTpass serviceID (mtn, mtn-data, dstv, ikeja-electric, waec, jamb …)
 * and a product's code is the VTpass variation_code.
 */
class VtpassProvider implements Provider
{
    /** Codes that mean the request definitely did not go through. */
    protected const FAILED_CODES = ['011', '012', '013', '014', '015', '016', '017', '018', '019', '021', '022', '023', '024', '025', '026', '027', '028', '030', '031', '032', '034', '035', '040', '083', '085', '087', '089'];

    public function __construct(protected ?Settings $settings = null)
    {
        $this->settings ??= app(Settings::class);
    }

    public function key(): string
    {
        return 'vtpass';
    }

    public function name(): string
    {
        return 'VTpass';
    }

    protected function cfg(): array
    {
        return $this->settings->provider('vtpass');
    }

    public function configured(): bool
    {
        $c = $this->cfg();

        return !empty($c['api_key']) && !empty($c['secret_key']) && !empty($c['public_key']);
    }

    protected function base(): string
    {
        return ($this->cfg()['mode'] ?? 'sandbox') === 'live' ? 'https://vtpass.com/api' : 'https://sandbox.vtpass.com/api';
    }

    protected function post(string $path, array $body): Response
    {
        $c = $this->cfg();

        return Http::withHeaders(['api-key' => (string) ($c['api_key'] ?? ''), 'secret-key' => (string) ($c['secret_key'] ?? '')])
            ->acceptJson()->asJson()->timeout(90)->connectTimeout(15)
            ->post($this->base() . $path, $body);
    }

    protected function get(string $path, array $query = []): Response
    {
        $c = $this->cfg();

        return Http::withHeaders(['api-key' => (string) ($c['api_key'] ?? ''), 'public-key' => (string) ($c['public_key'] ?? '')])
            ->acceptJson()->timeout(30)->get($this->base() . $path, $query);
    }

    /** VTpass wants the Lagos date-time first, then anything unique. */
    public static function requestId(): string
    {
        return now('Africa/Lagos')->format('YmdHi') . strtoupper(Str::random(12));
    }

    public function purchase(Transaction $t): ProviderResult
    {
        if (!$this->configured()) return ProviderResult::failed('This service is not available right now.');

        $requestId = $t->meta['request_id'] ?? self::requestId();
        $t->forceFill(['meta' => array_merge((array) $t->meta, ['request_id' => $requestId]), 'provider_reference' => $requestId])->save();

        try {
            $res = $this->post('/pay', $this->payload($t, $requestId));
        } catch (ConnectionException $e) {
            Log::warning('VTpass /pay unreachable', ['ref' => $t->reference, 'error' => $e->getMessage()]);

            return ProviderResult::processing('Waiting for the network to confirm.', $requestId);   // re-checked by topup:requery
        }

        return $this->read($res, $requestId);
    }

    public function requery(Transaction $t): ProviderResult
    {
        $requestId = $t->meta['request_id'] ?? $t->provider_reference;
        if (!$requestId) return ProviderResult::failed('This purchase never reached the provider.');

        try {
            $res = $this->post('/requery', ['request_id' => $requestId]);
        } catch (ConnectionException $e) {
            return ProviderResult::processing('Still waiting for the network to confirm.', $requestId);
        }

        return $this->read($res, $requestId);
    }

    protected function payload(Transaction $t, string $requestId): array
    {
        $s = $t->service;
        $p = [
            'request_id' => $requestId,
            'serviceID'  => $s->provider_code ?: $s->code,
            'amount'     => (string) round((float) ($t->cost ?? $t->amount)),
            'phone'      => $t->phone ?: $t->recipient,
        ];

        switch ($t->category) {
            case 'airtime':
                $p['phone'] = $t->recipient;
                break;
            case 'data':
                $p += ['billersCode' => $t->recipient, 'variation_code' => $t->product?->code];
                $p['phone'] = $t->recipient;
                break;
            case 'cable':
                $p += [
                    'billersCode'       => $t->recipient,
                    'variation_code'    => $t->product?->code,
                    'subscription_type' => ($t->meta['subscription_type'] ?? 'change') === 'renew' ? 'renew' : 'change',
                    'quantity'          => 1,
                ];
                break;
            case 'electricity':
                $p += ['billersCode' => $t->recipient, 'variation_code' => $t->meta['meter_type'] ?? 'prepaid'];
                break;
            case 'education':
                $p += ['variation_code' => $t->product?->code, 'quantity' => $t->quantity];
                if (!empty($t->meta['profile_id'])) $p['billersCode'] = $t->meta['profile_id'];
                break;
        }

        return $p;
    }

    protected function read(Response $res, string $requestId): ProviderResult
    {
        $j = (array) $res->json();
        $code = (string) ($j['code'] ?? '');
        $status = strtolower((string) data_get($j, 'content.transactions.status', ''));
        $desc = (string) ($j['response_description'] ?? 'No answer from the provider.');
        $ref = (string) (data_get($j, 'content.transactions.transactionId') ?: $requestId);
        $raw = $this->trimRaw($j);

        if ($res->serverError() || $code === '' && !$res->successful()) {
            return ProviderResult::processing('Waiting for the network to confirm.', $ref, $raw);
        }
        if ($code === '000' && $status === 'delivered') {
            $cost = data_get($j, 'content.transactions.total_amount') ?? data_get($j, 'amount');

            return ProviderResult::successful('Delivered.', $ref, $this->token($j), $cost !== null ? (string) $cost : null, $raw);
        }
        if (in_array($status, ['failed', 'reversed'], true)) return ProviderResult::failed($this->friendly($code, $desc), $ref, $raw);
        if ($code === '000' || $code === '099' || in_array($status, ['pending', 'initiated'], true)) {
            return ProviderResult::processing('Your request is being processed.', $ref, $raw);
        }
        if (in_array($code, self::FAILED_CODES, true)) return ProviderResult::failed($this->friendly($code, $desc), $ref, $raw);

        // Anything unknown: don't refund blindly — re-check later.
        return ProviderResult::processing('Waiting for the network to confirm.', $ref, $raw);
    }

    /** Never show "low wallet balance" (code 018) to a customer. */
    protected function friendly(string $code, string $desc): string
    {
        return match ($code) {
            '018', '030', '034' => 'This service is temporarily unavailable. Please try again shortly.',
            '019' => 'A similar purchase was just made. Please wait a minute before trying again.',
            '012', '013', '017', '011' => 'The provider could not process this request: ' . ucfirst(strtolower($desc)) . '.',
            default => ucfirst(strtolower($desc)) ?: 'The purchase failed.',
        };
    }

    /** Electricity token, exam PINs. */
    protected function token(array $j): ?string
    {
        foreach (['purchased_code', 'mainToken', 'token', 'Token', 'Pin'] as $k) {
            if (!empty($j[$k]) && is_string($j[$k])) return trim(preg_replace('/^Token\s*:\s*/i', '', $j[$k]));
        }
        if (!empty($j['cards']) && is_array($j['cards'])) {
            return collect($j['cards'])->map(fn ($c) => 'PIN ' . ($c['Pin'] ?? '?') . ' / SN ' . ($c['Serial'] ?? '?'))->implode("\n");
        }
        if (!empty($j['tokens']) && is_array($j['tokens'])) return implode("\n", $j['tokens']);

        return null;
    }

    protected function trimRaw(array $j): array
    {
        return ['code' => $j['code'] ?? null, 'response_description' => $j['response_description'] ?? null,
            'status' => data_get($j, 'content.transactions.status'), 'transactionId' => data_get($j, 'content.transactions.transactionId'),
            'amount' => $j['amount'] ?? null, 'commission' => data_get($j, 'content.transactions.commission')];
    }

    public function verify(Service $service, string $customerId, ?Product $product = null, array $extra = []): array
    {
        if (!$this->configured()) return ['valid' => false, 'name' => null, 'details' => [], 'message' => 'Verification is not available right now.'];

        $body = ['billersCode' => $customerId, 'serviceID' => $service->provider_code ?: $service->code];
        if ($service->category === 'electricity') $body['type'] = $extra['meter_type'] ?? 'prepaid';
        if ($service->category === 'education' && $product) $body['type'] = $product->code;

        try {
            $res = $this->post('/merchant-verify', $body);
        } catch (ConnectionException $e) {
            return ['valid' => false, 'name' => null, 'details' => [], 'message' => 'Could not reach the provider. Please try again.'];
        }

        $c = (array) $res->json('content');
        if ($res->json('code') !== '000' || !empty($c['error']) || empty($c['Customer_Name'])) {
            return ['valid' => false, 'name' => null, 'details' => [], 'message' => (string) ($c['error'] ?? 'We could not find that number. Please check it.')];
        }

        return ['valid' => true, 'name' => trim((string) $c['Customer_Name']), 'details' => array_filter([
            'address'         => $c['Address'] ?? null,
            'current_bouquet' => $c['Current_Bouquet'] ?? null,
            'renewal_amount'  => $c['Renewal_Amount'] ?? null,
            'due_date'        => $c['Due_Date'] ?? null,
            'meter_type'      => $c['Meter_Type'] ?? null,
            'min_amount'      => $c['Min_Purchase_Amount'] ?? null,
        ]), 'message' => null];
    }

    public function products(Service $service): array
    {
        $res = $this->get('/service-variations', ['serviceID' => $service->provider_code ?: $service->code]);
        $list = (array) ($res->json('content.varations') ?? $res->json('content.variations') ?? []);

        return collect($list)->map(fn ($v) => [
            'code'     => (string) $v['variation_code'],
            'name'     => trim((string) $v['name']),
            'amount'   => (string) ($v['variation_amount'] ?? '0'),
            'validity' => self::validity((string) $v['name']),
        ])->filter(fn ($v) => $v['code'] !== '')->values()->all();
    }

    /** "MTN N1000 1.5GB - 30 days" → "30 days" */
    protected static function validity(string $name): ?string
    {
        return preg_match('/(\d+\s*(?:day|days|hrs|hours|week|weeks|month|months))/i', $name, $m) ? strtolower($m[1]) : null;
    }

    public function balance(): ?float
    {
        if (!$this->configured()) return null;
        try {
            $res = $this->get('/balance');
        } catch (\Throwable $e) {
            return null;
        }
        $b = $res->json('contents.balance') ?? $res->json('content.balance');

        return $b !== null ? (float) $b : null;
    }
}
