<?php

namespace App\Http\Controllers\Topup;

use App\Exceptions\TopupException;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Product;
use App\Models\School;
use App\Models\Service;
use App\Models\SmsSenderId;
use App\Services\Settings\Settings;
use App\Services\Topup\PricingService;
use App\Services\Topup\TopupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Buying on the web: pick a category, fill the form, confirm with the transaction PIN. */
class BuyController extends Controller
{
    public function __construct(protected TopupService $topup, protected PricingService $pricing)
    {
        $this->middleware('permission:Buy services');
    }

    protected function country(Request $request): Country
    {
        $code = $request->user()->country_code ?: config('afritopup.default_country', 'NG');

        return Country::byCode($code) ?? Country::active()->ordered()->firstOrFail();
    }

    public function index(Request $request)
    {
        $country = $this->country($request);
        $services = Service::active()->in($country->code)->ordered()->get()->groupBy('category');

        return view('topup.buy.index', [
            'pagetitle'  => 'Buy & Pay',
            'country'    => $country,
            'categories' => collect(config('afritopup.categories'))->filter(fn ($c, $k) => $services->has($k)),
            'services'   => $services,
            'wallet'     => $request->user()->wallet($country->currency),
            'recent'     => $request->user()->transactions()->with('service', 'product')->latest('id')->limit(6)->get(),
        ]);
    }

    public function show(Request $request, string $category)
    {
        abort_unless(config('afritopup.categories.' . $category), 404);
        $country = $this->country($request);
        $services = Service::active()->in($country->code)->where('category', $category)->ordered()->get();
        abort_if($services->isEmpty(), 404);
        $user = $request->user();

        return view('topup.buy.form', [
            'pagetitle' => config('afritopup.categories.' . $category . '.name'),
            'category'  => $category,
            'cat'       => config('afritopup.categories.' . $category),
            'country'   => $country,
            'services'  => $services,
            'selected'  => $services->firstWhere('code', $request->query('service')) ?? $services->first(),
            'wallet'    => $user->wallet($country->currency),
            'hasPin'    => $user->hasTransactionPin(),
            'schools'   => $category === 'education' ? School::where('is_active', true)->where('country_code', $country->code)->orderBy('name')->get(['id', 'name', 'city', 'fee_items']) : collect(),
            'senders'   => $category === 'sms' ? collect([(string) (app(Settings::class)->provider('termii')['sender_id'] ?? 'AfriTopup')])
                ->merge(SmsSenderId::where('user_id', $user->id)->where('status', 'approved')->pluck('sender_id'))->unique()->values() : collect(),
        ]);
    }

    /** Plans for a service, priced for this user. */
    public function products(Request $request, Service $service): JsonResponse
    {
        abort_unless($service->is_active, 404);
        $user = $request->user();
        $products = $service->products()->active()->get()->map(function (Product $p) use ($user, $service) {
            $q = $this->pricing->quote($user, $service, $p, null);

            return ['id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'validity' => $p->validity, 'price' => (float) $p->price, 'total' => (float) $q['total'], 'discount' => (float) $q['discount']];
        });

        return response()->json(['products' => $products, 'service' => $this->servicePayload($service)]);
    }

    /** Check a smartcard / meter / profile ID. */
    public function verify(Request $request, Service $service): JsonResponse
    {
        $request->validate(['customer_id' => 'required|string|max:30', 'product_id' => 'nullable|integer', 'meter_type' => 'nullable|in:prepaid,postpaid']);
        $product = $request->filled('product_id') ? $service->products()->find($request->input('product_id')) : null;

        return response()->json($this->topup->verify($service, $request->input('customer_id'), $product, ['meter_type' => $request->input('meter_type', 'prepaid')]));
    }

    /** What the customer will pay. */
    public function quote(Request $request, Service $service): JsonResponse
    {
        $product = $request->filled('product_id') ? $service->products()->active()->find($request->input('product_id')) : null;
        $qty = max(1, (int) $request->input('quantity', 1));

        return response()->json($this->pricing->quote($request->user(), $service, $product, $product ? null : (float) $request->input('amount', 0), $qty));
    }

    public function store(Request $request)
    {
        $request->validate([
            'service_id' => 'required|integer',
            'pin'        => 'required|digits:4',
        ], ['pin.required' => 'Enter your 4-digit transaction PIN.', 'pin.digits' => 'Your transaction PIN is 4 digits.']);

        $user = $request->user();
        if (!$user->hasTransactionPin()) return back()->withInput()->with('error', 'Set your transaction PIN on your wallet page first.');
        if (!$user->checkTransactionPin($request->input('pin'))) return back()->withInput()->withErrors(['pin' => 'That PIN is not correct.']);

        try {
            $t = $this->topup->purchase($user, $request->except(['_token', 'pin', 'client_reference']), 'web');
        } catch (TopupException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $flash = match ($t->status) {
            'successful' => ['success', 'Done! ' . $t->summary],
            'failed'     => ['error', ($t->message ?: 'The purchase failed.') . ' Your wallet has been refunded.'],
            default      => ['info', 'We are processing your purchase. You will be notified as soon as it is confirmed.'],
        };

        return redirect()->route('topup.transactions.show', $t->reference)->with(...$flash);
    }

    protected function servicePayload(Service $s): array
    {
        return [
            'id' => $s->id, 'code' => $s->code, 'name' => $s->name, 'category' => $s->category,
            'needs_product' => $s->needsProduct(), 'needs_amount' => $s->needsAmount(), 'needs_verification' => $s->needsVerification(),
            'recipient_label' => $s->recipientLabel(), 'min_amount' => $s->min_amount !== null ? (float) $s->min_amount : null,
            'max_amount' => $s->max_amount !== null ? (float) $s->max_amount : null, 'fee' => (float) $s->fee, 'options' => $s->options ?? (object) [],
        ];
    }
}
