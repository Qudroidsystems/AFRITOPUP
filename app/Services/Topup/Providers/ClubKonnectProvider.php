<?php

namespace App\Services\Topup\Providers;

use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Services\Settings\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ClubKonnect / Nellobytes Systems (https://www.nellobytesystems.com): airtime, data, cable TV,
 * electricity and WAEC PINs in Nigeria.
 *
 *  - Every call is a GET with UserID and APIKey in the query string.
 *  - A purchase answers {"orderid", "statuscode": "100", "status": "ORDER_RECEIVED"}; it is then
 *    re-checked with APIQueryV1.asp until ORDER_COMPLETED (statuscode 200) or ORDER_CANCELLED.
 *  - Networks: 01 MTN, 02 Glo, 03 9mobile, 04 Airtel. Discos 01 Eko … 12 Aba. Meter type 01 prepaid, 02 postpaid.
 *  - A data plan's code is ClubKonnect's DataPlan id ("500.0", "1000.0" …); cable packages use their
 *    package code ("dstv-padi"). Import them with "Load plans" on the service.
 */
class ClubKonnectProvider implements Provider
{
    protected const BASE = 'https://www.nellobytesystems.com';

    protected const NETWORK = ['mtn' => '01', 'glo' => '02', '9mobile' => '03', 'airtel' => '04'];

    protected const DISCO = ['eko' => '01', 'ikeja' => '02', 'abuja' => '03', 'kano' => '04', 'portharcourt' => '05', 'jos' => '06',
        'ibadan' => '07', 'kaduna' => '08', 'enugu' => '09', 'benin' => '10', 'yola' => '11', 'aba' => '12'];

    /** Answers that mean the order was refused and never placed. */
    protected const REFUSED = ['INVALID_CREDENTIALS', 'MISSING_CREDENTIALS', 'MISSING_USERID', 'MISSING_APIKEY', 'INVALID_USERID',
        'INSUFFICIENT_BALANCE', 'INVALID_RECIPIENT', 'INVALID_MOBILENUMBER', 'MISSING_MOBILENUMBER', 'INVALID_AMOUNT', 'MINIMUM_50',
        'MINIMUM_1000', 'MAXIMUM_200000', 'INVALID_DATAPLAN', 'INVALID_PACKAGE', 'INVALID_SMARTCARDNO', 'INVALID_METERNO',
        'INVALID_MOBILENETWORK', 'MISSING_MOBILENETWORK', 'INVALID_CABLETV', 'INVALID_COMPANY', 'INVALID_METERTYPE',
        'DUPLICATE_REQUESTID', 'SERVICE_NOT_AVAILABLE', 'ORDER_CANCELLED', 'ORDER_FAILED', 'ORDER_ERROR'];

    public function __construct(protected ?Settings $settings = null)
    {
        $this->settings ??= app(Settings::class);
    }

    public function key(): string
    {
        return 'clubkonnect';
    }

    public function name(): string
    {
        return 'ClubKonnect (Nellobytes)';
    }

    public static function categories(): array
    {
        return ['airtime', 'data', 'cable', 'electricity', 'education'];
    }

    protected function cfg(): array
    {
        return $this->settings->provider('clubkonnect');
    }

    public function configured(): bool
    {
        $c = $this->cfg();

        return !empty($c['user_id']) && !empty($c['api_key']);
    }

    protected function call(string $script, array $query, int $timeout = 60): array
    {
        $c = $this->cfg();
        $res = Http::acceptJson()->timeout($timeout)->connectTimeout(15)->get(self::BASE . '/' . $script, array_merge([
            'UserID' => (string) ($c['user_id'] ?? ''), 'APIKey' => (string) ($c['api_key'] ?? ''),
        ], $query));
        $j = $res->json();
        if (!is_array($j)) {
            // A few scripts answer plain text: "INSUFFICIENT_BALANCE".
            $text = strtoupper(trim(strip_tags((string) $res->body())));
            $j = ['status' => $text ?: ($res->successful() ? '' : 'HTTP_' . $res->status())];
        }
        $j['_http'] = $res->status();

        return $j;
    }

    public function purchase(Transaction $t): ProviderResult
    {
        if (!$this->configured()) return ProviderResult::failed('This service is not available right now.');
        $s = $t->service;
        $requestId = $t->meta['request_id'] ?? ('PC' . now('Africa/Lagos')->format('ymdHis') . strtoupper(Str::random(6)));
        $t->forceFill(['meta' => array_merge((array) $t->meta, ['request_id' => $requestId])])->save();
        $amount = (string) round((float) ($t->cost ?? $t->amount));
        $phone = $t->phone ?: $t->recipient;

        [$script, $q] = match ($t->category) {
            'airtime' => ['APIAirtimeV1.asp', ['MobileNetwork' => $this->networkId($s), 'Amount' => $amount, 'MobileNumber' => $t->recipient]],
            'data' => ['APIDatabundleV1.asp', ['MobileNetwork' => $this->networkId($s), 'DataPlan' => $t->product?->codeFor($this->key()), 'MobileNumber' => $t->recipient]],
            'cable' => ['APICableTVV1.asp', ['CableTV' => $this->cableId($s), 'Package' => $t->product?->codeFor($this->key()), 'SmartCardNo' => $t->recipient, 'PhoneNo' => $phone]],
            'electricity' => ['APIElectricityV1.asp', ['ElectricCompany' => $this->discoId($s), 'MeterType' => ($t->meta['meter_type'] ?? 'prepaid') === 'postpaid' ? '02' : '01',
                'MeterNo' => $t->recipient, 'Amount' => $amount, 'PhoneNo' => $phone]],
            'education' => $this->examCall($s, $t, $phone),
            default => [null, []],
        };
        if (!$script || in_array(null, $q, true) || in_array('', $q, true)) {
            return ProviderResult::failed('This service is not available from the current vendor.');
        }

        try {
            $j = $this->call($script, $q + ['RequestID' => $requestId], 90);
        } catch (ConnectionException $e) {
            Log::warning('ClubKonnect unreachable', ['ref' => $t->reference, 'error' => $e->getMessage()]);

            return ProviderResult::processing('Waiting for the network to confirm.', $requestId);
        }

        return $this->read($j, $requestId);
    }

    protected function examCall(Service $s, Transaction $t, string $phone): array
    {
        $code = strtolower((string) $s->code);

        return match (true) {
            str_starts_with($code, 'waec') => ['APIWAECV1.asp', ['ExamType' => $t->product?->codeFor($this->key()) ?: 'waecdirect', 'PhoneNo' => $phone]],
            str_starts_with($code, 'jamb') => ['APIJAMBV1.asp', ['ExamType' => $t->product?->codeFor($this->key()) ?: 'utme', 'PhoneNo' => $t->meta['profile_id'] ?? $phone]],
            default => [null, []],
        };
    }

    public function requery(Transaction $t): ProviderResult
    {
        $requestId = $t->meta['request_id'] ?? null;
        $orderId = $t->provider_reference;
        if (!$requestId && !$orderId) return ProviderResult::failed('This purchase never reached the provider.');

        try {
            $j = $this->call('APIQueryV1.asp', $orderId && $orderId !== $requestId ? ['OrderID' => $orderId] : ['RequestID' => $requestId], 30);
        } catch (ConnectionException $e) {
            return ProviderResult::processing('Still waiting for the network to confirm.', $orderId);
        }

        return $this->read($j, $orderId ?: $requestId);
    }

    protected function read(array $j, ?string $fallbackRef): ProviderResult
    {
        $status = strtoupper(trim((string) ($j['status'] ?? $j['orderstatus'] ?? '')));
        $code = (string) ($j['statuscode'] ?? '');
        $ref = (string) ($j['orderid'] ?? $fallbackRef);
        $raw = array_intersect_key($j, array_flip(['orderid', 'statuscode', 'status', 'remark', 'ordertype', 'amountcharged', 'walletbalance', '_http']));
        $cost = isset($j['amountcharged']) && is_numeric($j['amountcharged']) ? (string) $j['amountcharged'] : null;

        if ($code === '200' || $status === 'ORDER_COMPLETED') {
            return ProviderResult::successful('Delivered.', $ref, $this->token($j), $cost, $raw);
        }
        if (in_array($status, self::REFUSED, true) || str_starts_with($status, 'INVALID_') || str_starts_with($status, 'MISSING_')) {
            return ProviderResult::failed($this->friendly($status, (string) ($j['remark'] ?? '')), $ref, $raw);
        }
        if (($j['_http'] ?? 200) >= 500 || $status === '' || in_array($status, ['ORDER_RECEIVED', 'ORDER_ONHOLD', 'ORDER_PROCESSING', 'ORDER_PENDING'], true) || $code === '100') {
            return ProviderResult::processing('Your request is being processed.', $ref, $raw);
        }

        return ProviderResult::processing('Waiting for the network to confirm.', $ref, $raw);
    }

    protected function friendly(string $status, string $remark): string
    {
        return match (true) {
            in_array($status, ['INSUFFICIENT_BALANCE', 'INVALID_CREDENTIALS', 'MISSING_CREDENTIALS', 'SERVICE_NOT_AVAILABLE'], true) => 'This service is temporarily unavailable. Please try again shortly.',
            in_array($status, ['INVALID_RECIPIENT', 'INVALID_MOBILENUMBER'], true) => 'That phone number was not accepted. Please check it.',
            $status === 'INVALID_SMARTCARDNO' => 'That smartcard / IUC number was not accepted. Please check it.',
            $status === 'INVALID_METERNO' => 'That meter number was not accepted. Please check it.',
            default => $remark !== '' ? ucfirst(strtolower($remark)) : 'The purchase failed (' . strtolower(str_replace('_', ' ', $status)) . ').',
        };
    }

    /** Electricity token, exam PINs. */
    protected function token(array $j): ?string
    {
        foreach (['metertoken', 'MeterToken', 'token', 'Token', 'pin', 'carddetails'] as $k) {
            if (!empty($j[$k]) && is_string($j[$k])) return trim(preg_replace('/^Token\s*:\s*/i', '', $j[$k]));
        }

        return null;
    }

    protected function networkId(Service $s): ?string
    {
        return NetworkCodes::pinned($s, $this->key()) ?? (self::NETWORK[NetworkCodes::network($s) ?? ''] ?? null);
    }

    protected function discoId(Service $s): ?string
    {
        return NetworkCodes::pinned($s, $this->key()) ?? (self::DISCO[NetworkCodes::disco($s) ?? ''] ?? null);
    }

    protected function cableId(Service $s): ?string
    {
        return NetworkCodes::pinned($s, $this->key()) ?? NetworkCodes::cable($s);
    }

    public function verify(Service $service, string $customerId, ?Product $product = null, array $extra = []): array
    {
        if (!$this->configured()) return ['valid' => false, 'name' => null, 'details' => [], 'message' => 'Verification is not available right now.'];
        try {
            $j = match ($service->category) {
                'cable' => $this->call('APIVerifyCableTVV1.0.asp', ['CableTV' => $this->cableId($service), 'SmartCardNo' => $customerId], 30),
                'electricity' => $this->call('APIVerifyElectricityV1.asp', ['ElectricCompany' => $this->discoId($service), 'MeterNo' => $customerId,
                    'MeterType' => ($extra['meter_type'] ?? 'prepaid') === 'postpaid' ? '02' : '01'], 30),
                default => null,
            };
        } catch (ConnectionException $e) {
            return ['valid' => false, 'name' => null, 'details' => [], 'message' => 'Could not reach the provider. Please try again.'];
        }
        if ($j === null) return ['valid' => true, 'name' => null, 'details' => [], 'message' => null];

        $name = trim((string) ($j['customer_name'] ?? $j['CustomerName'] ?? $j['name'] ?? ''));
        if ($name === '' || str_contains(strtoupper($name), 'INVALID') || str_contains(strtoupper($name), 'ERROR')) {
            return ['valid' => false, 'name' => null, 'details' => [], 'message' => 'We could not find that number. Please check it.'];
        }

        return ['valid' => true, 'name' => $name, 'details' => array_filter(['address' => $j['customer_address'] ?? null]), 'message' => null];
    }

    public function products(Service $service): array
    {
        if (!$this->configured()) return [];
        $c = $this->cfg();
        if ($service->category === 'data') {
            $net = NetworkCodes::network($service);
            $j = Http::acceptJson()->timeout(40)->get(self::BASE . '/APIDatabundlePlansV2.asp', ['UserID' => $c['user_id']])->json();
            $rows = [];
            foreach ((array) data_get($j, 'MOBILE_NETWORK', []) as $label => $groups) {
                $l = strtolower(str_replace(['m_', '_'], '', (string) $label));
                $match = $net === '9mobile' ? (str_contains($l, '9mobile') || str_contains($l, 'etisalat')) : str_contains($l, (string) $net);
                if (!$match) continue;
                foreach ((array) $groups as $g) {
                    foreach ((array) ($g['PRODUCT'] ?? []) as $p) {
                        $name = trim((string) ($p['PRODUCT_NAME'] ?? ''));
                        $rows[] = ['code' => (string) ($p['PRODUCT_ID'] ?? $p['PRODUCT_CODE'] ?? ''), 'name' => strtoupper((string) $net) . ' ' . $name,
                            'amount' => (string) ($p['PRODUCT_AMOUNT'] ?? '0'), 'validity' => self::validity($name)];
                    }
                }
            }

            return array_values(array_filter($rows, fn ($r) => $r['code'] !== ''));
        }
        if ($service->category === 'cable') {
            $cable = $this->cableId($service);
            $j = Http::acceptJson()->timeout(40)->get(self::BASE . '/APICableTVPackagesV2.asp', ['UserID' => $c['user_id']])->json();
            $rows = [];
            $walk = function ($node) use (&$walk, &$rows, $cable) {
                if (!is_array($node)) return;
                if (isset($node['ID'], $node['PRODUCT']) && strtolower((string) $node['ID']) === $cable) {
                    foreach ((array) $node['PRODUCT'] as $p) {
                        $rows[] = ['code' => (string) ($p['PACKAGE_ID'] ?? ''), 'name' => trim((string) ($p['PACKAGE_NAME'] ?? '')),
                            'amount' => (string) ($p['PACKAGE_AMOUNT'] ?? '0'), 'validity' => null];
                    }

                    return;
                }
                foreach ($node as $v) $walk($v);
            };
            $walk($j);

            return array_values(array_filter($rows, fn ($r) => $r['code'] !== ''));
        }

        return [];
    }

    protected static function validity(string $name): ?string
    {
        return preg_match('/(\d+\s*(?:day|days|hrs|hours|week|weeks|month|months))/i', $name, $m) ? strtolower($m[1]) : null;
    }

    public function balance(): ?float
    {
        if (!$this->configured()) return null;
        try {
            $j = $this->call('APIWalletBalanceV1.asp', [], 20);
        } catch (\Throwable $e) {
            return null;
        }
        $b = $j['balance'] ?? $j['Balance'] ?? null;

        return is_numeric($b) ? (float) $b : (is_string($b) && is_numeric(str_replace(',', '', $b)) ? (float) str_replace(',', '', $b) : null);
    }
}
