<?php

namespace App\Http\Controllers\Api\Reseller;

use App\Exceptions\TopupException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Country;
use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Services\Topup\PricingService;
use App\Services\Topup\TopupService;
use Illuminate\Http\Request;

/**
 * The reseller API (/api/reseller/v1). Services and plans are named by code
 * ("mtn", "mtn-data", "dstv", "ikeja-electric"), not by id, so integrations
 * survive catalogue changes. Every purchase needs the caller's own
 * `reference`; sending the same reference again returns the first purchase
 * instead of buying twice.
 */
class ResellerApiController extends ApiController
{
    public function __construct(protected TopupService $topup, protected PricingService $pricing)
    {
    }

    protected function country(Request $request): Country
    {
        return Country::byCode($request->user()->country_code ?: config('afritopup.default_country', 'NG')) ?? Country::firstOrFail();
    }

    protected function findService(Request $request, string $code): Service
    {
        return Service::active()->in($this->country($request)->code)->where('code', $code)->firstOrFail();
    }

    public function balance(Request $request)
    {
        $w = $request->user()->wallet();

        return $this->ok(['balance' => (float) $w->balance, 'currency' => $w->currency]);
    }

    public function services(Request $request)
    {
        $q = Service::active()->in($this->country($request)->code)->ordered()
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c));

        return $this->ok($q->get()->map(fn (Service $s) => [
            'code' => $s->code, 'name' => $s->name, 'category' => $s->category,
            'needs_product' => $s->needsProduct(), 'needs_amount' => $s->needsAmount(), 'needs_verification' => $s->needsVerification(),
            'min_amount' => $s->min_amount !== null ? (float) $s->min_amount : null, 'max_amount' => $s->max_amount !== null ? (float) $s->max_amount : null,
            'fee' => (float) $s->fee,
        ])->values());
    }

    public function products(Request $request, string $code)
    {
        $s = $this->findService($request, $code);
        $user = $request->user();

        return $this->ok($s->products()->active()->get()->map(function (Product $p) use ($s, $user) {
            $q = $this->pricing->quote($user, $s, $p, null);

            return ['code' => $p->code, 'name' => $p->name, 'validity' => $p->validity, 'price' => (float) $p->price, 'your_price' => (float) $q['total']];
        })->values());
    }

    public function verify(Request $request)
    {
        $d = $request->validate(['service' => 'required|string', 'customer_id' => 'required|string|max:30', 'product' => 'nullable|string', 'meter_type' => 'nullable|in:prepaid,postpaid']);
        $s = $this->findService($request, $d['service']);
        $product = !empty($d['product']) ? $s->products()->where('code', $d['product'])->first() : null;
        $r = $this->topup->verify($s, $d['customer_id'], $product, ['meter_type' => $d['meter_type'] ?? 'prepaid']);

        return $r['valid'] ? $this->ok($r) : $this->fail($r['message'] ?: 'Not found.', 422, 'verification_failed');
    }

    public function purchase(Request $request)
    {
        $d = $request->validate([
            'reference' => 'required|string|max:80|regex:/^[A-Za-z0-9._:-]+$/',
            'service'   => 'required|string',
            'product'   => 'nullable|string',
        ]);
        $s = $this->findService($request, $d['service']);
        $input = $request->except(['reference', 'service', 'product', 'service_id', 'product_id', 'client_reference']) + [
            'service_id'       => $s->id,
            'product_code'     => $d['product'] ?? null,
            'client_reference' => $d['reference'],
        ];

        try {
            $t = $this->topup->purchase($request->user(), $input, 'api', $request->attributes->get('api_key'));
        } catch (TopupException $e) {
            return $this->fail($e->getMessage(), $e->status, $e->reason);
        }

        return $this->ok($t->load('service', 'product')->present(), null, $t->wasRecentlyCreated ? 201 : 200);
    }

    /** By our reference or yours. */
    public function transaction(Request $request, string $reference)
    {
        $t = Transaction::with('service', 'product')->where('user_id', $request->user()->id)
            ->where(fn ($q) => $q->where('reference', $reference)->orWhere('client_reference', $reference))->firstOrFail();

        return $this->ok($t->present());
    }
}
