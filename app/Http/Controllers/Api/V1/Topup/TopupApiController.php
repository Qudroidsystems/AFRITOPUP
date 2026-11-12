<?php

namespace App\Http\Controllers\Api\V1\Topup;

use App\Exceptions\TopupException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Country;
use App\Models\Product;
use App\Models\School;
use App\Models\Service;
use App\Models\SmsSenderId;
use App\Models\Transaction;
use App\Services\Settings\Settings;
use App\Services\Topup\PricingService;
use App\Services\Topup\TopupService;
use App\Support\Money;
use Illuminate\Http\Request;

/** The mobile app's buying endpoints (Sanctum). Same rules as the web pages. */
class TopupApiController extends ApiController
{
    public function __construct(protected TopupService $topup, protected PricingService $pricing)
    {
        $this->middleware('permission:Buy services');
    }

    protected function country(Request $request): Country
    {
        return Country::byCode($request->user()->country_code ?: config('afritopup.default_country', 'NG')) ?? Country::active()->ordered()->firstOrFail();
    }

    /** Categories with their services for the user's country. */
    public function catalog(Request $request)
    {
        $country = $this->country($request);
        $services = Service::active()->in($country->code)->ordered()->get()->groupBy('category');

        return $this->ok([
            'country'    => $country->only(['code', 'name', 'currency', 'dial_code', 'flag']),
            'countries'  => Country::active()->ordered()->get(['code', 'name', 'currency', 'dial_code', 'flag']),
            'categories' => collect(config('afritopup.categories'))->filter(fn ($c, $k) => $services->has($k))->map(fn ($c, $k) => [
                'key' => $k, 'name' => $c['name'], 'color' => $c['color'], 'icon' => $c['icon'],
                'services' => $services[$k]->map(fn (Service $s) => $this->service($s))->values(),
            ])->values(),
        ]);
    }

    /** Switch the country the app buys in. Its wallet (in its own currency) is created if needed. */
    public function setCountry(Request $request)
    {
        $data = $request->validate(['country_code' => 'required|string|size:2']);
        $country = Country::active()->where('code', strtoupper($data['country_code']))->first();
        if (!$country) return $this->fail('That country is not available yet.', 422, 'country_unavailable');
        $request->user()->forceFill(['country_code' => $country->code])->save();
        $w = $request->user()->wallet($country->currency);

        return $this->ok(['country' => $country->only(['code', 'name', 'currency', 'dial_code', 'flag']), 'balance' => (float) $w->balance], 'Now buying in ' . $country->name . '.');
    }

    public function products(Request $request, Service $service)
    {
        abort_unless($service->is_active, 404);
        $user = $request->user();

        return $this->ok($service->products()->active()->get()->map(function (Product $p) use ($user, $service) {
            $q = $this->pricing->quote($user, $service, $p, null);

            return ['id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'validity' => $p->validity,
                'price' => (float) $p->price, 'total' => (float) $q['total'], 'discount' => (float) $q['discount'],
                'total_label' => Money::format($q['total'], $service->country?->currency)];
        })->values());
    }

    public function verify(Request $request, Service $service)
    {
        $request->validate(['customer_id' => 'required|string|max:30', 'product_id' => 'nullable|integer', 'meter_type' => 'nullable|in:prepaid,postpaid']);
        $product = $request->filled('product_id') ? $service->products()->find($request->input('product_id')) : null;
        $r = $this->topup->verify($service, $request->input('customer_id'), $product, ['meter_type' => $request->input('meter_type', 'prepaid')]);

        return $r['valid'] ? $this->ok($r) : $this->fail($r['message'] ?: 'Not found.', 422, 'verification_failed', ['customer_id' => [$r['message'] ?: 'Not found.']]);
    }

    public function quote(Request $request, Service $service)
    {
        $product = $request->filled('product_id') ? $service->products()->active()->find($request->input('product_id')) : null;
        $q = $this->pricing->quote($request->user(), $service, $product, $product ? null : (float) $request->input('amount', 0), max(1, (int) $request->input('quantity', 1)));
        $cur = $service->country?->currency;

        return $this->ok(array_map('floatval', $q) + ['total_label' => Money::format($q['total'], $cur), 'currency' => $cur]);
    }

    public function purchase(Request $request)
    {
        $request->validate(['service_id' => 'required|integer', 'pin' => 'required|digits:4']);
        $user = $request->user();
        if (!$user->hasTransactionPin()) return $this->fail('Set your transaction PIN first.', 422, 'pin_not_set');
        if (!$user->checkTransactionPin($request->input('pin'))) return $this->fail('That PIN is not correct.', 422, 'wrong_pin', ['pin' => ['That PIN is not correct.']]);

        try {
            $t = $this->topup->purchase($user, $request->except(['pin']), 'app');
        } catch (TopupException $e) {
            return $this->fail($e->getMessage(), $e->status, $e->reason);
        }

        return $this->ok($this->txn($t->load('service', 'product')), match ($t->status) {
            'successful' => 'Successful.',
            'failed'     => ($t->message ?: 'The purchase failed.') . ' Your wallet has been refunded.',
            default      => 'Processing. We will notify you when it is confirmed.',
        }, 201);
    }

    public function transactions(Request $request)
    {
        $p = $request->user()->transactions()->with('service', 'product')->latest('id')
            ->status($request->query('status'))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->paginate(min(50, (int) $request->query('per_page', 20)));

        return $this->paged($p, fn ($t) => $this->txn($t));
    }

    public function transaction(Request $request, string $reference)
    {
        $t = $request->user()->transactions()->with('service', 'product')->where('reference', $reference)->firstOrFail();

        return $this->ok($this->txn($t));
    }

    public function schools(Request $request)
    {
        return $this->ok(School::where('is_active', true)->where('country_code', $this->country($request)->code)->orderBy('name')->get(['id', 'name', 'city', 'fee_items']));
    }

    public function senders(Request $request)
    {
        $default = (string) (app(Settings::class)->provider('termii')['sender_id'] ?? 'AfriTopup');

        return $this->ok([
            'default'  => $default,
            'approved' => SmsSenderId::where('user_id', $request->user()->id)->where('status', 'approved')->pluck('sender_id'),
            'requests' => SmsSenderId::where('user_id', $request->user()->id)->latest()->get(['id', 'sender_id', 'purpose', 'status', 'note', 'created_at']),
        ]);
    }

    public function requestSender(Request $request)
    {
        $d = $request->validate(['sender_id' => ['required', 'string', 'min:3', 'max:11', 'regex:/^[A-Za-z0-9 ]+$/'], 'purpose' => 'required|string|max:255']);
        $s = SmsSenderId::firstOrCreate(['user_id' => $request->user()->id, 'sender_id' => trim($d['sender_id'])], ['purpose' => $d['purpose'], 'status' => 'pending']);

        return $this->ok($s->only(['id', 'sender_id', 'purpose', 'status']), 'Request sent.', 201);
    }

    public function beneficiaries(Request $request)
    {
        return $this->ok($request->user()->beneficiaries()->with('service:id,code,name,short_name,color')
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->orderByDesc('last_used_at')->limit(30)->get()->map->present()->values());
    }

    public function renameBeneficiary(Request $request, \App\Models\Beneficiary $beneficiary)
    {
        abort_unless($beneficiary->user_id === $request->user()->id, 404);
        $beneficiary->update($request->validate(['label' => 'nullable|string|max:80']));

        return $this->ok($beneficiary->present());
    }

    public function deleteBeneficiary(Request $request, \App\Models\Beneficiary $beneficiary)
    {
        abort_unless($beneficiary->user_id === $request->user()->id, 404);
        $beneficiary->delete();

        return $this->ok(null, 'Removed.');
    }

    protected function service(Service $s): array
    {
        return [
            'id' => $s->id, 'code' => $s->code, 'name' => $s->name, 'short_name' => $s->display_name, 'logo_url' => $s->logo_url, 'color' => $s->color,
            'needs_product' => $s->needsProduct(), 'needs_amount' => $s->needsAmount(), 'needs_verification' => $s->needsVerification(),
            'recipient_label' => $s->recipientLabel(),
            'min_amount' => $s->min_amount !== null ? (float) $s->min_amount : null, 'max_amount' => $s->max_amount !== null ? (float) $s->max_amount : null,
            'fee' => (float) $s->fee, 'options' => (object) ($s->options ?? []),
        ];
    }

    protected function txn(Transaction $t): array
    {
        return $t->present() + ['total_label' => Money::format($t->total, $t->currency), 'service_color' => $t->service?->color, 'meta' => collect((array) $t->meta)->except(['recipients', 'request_id'])->all()];
    }
}
