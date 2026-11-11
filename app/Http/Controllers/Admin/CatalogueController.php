<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceTierDiscount;
use App\Services\Topup\Providers\ProviderManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Services & Pricing: billers, plans, providers, tier discounts, countries and price tiers. */
class CatalogueController extends Controller
{
    public function __construct(protected ProviderManager $providers)
    {
        $this->middleware('permission:Manage services');
    }

    public function index(Request $request)
    {
        $country = strtoupper($request->query('country', config('afritopup.default_country', 'NG')));
        $services = Service::withCount(['products', 'products as active_products_count' => fn ($q) => $q->where('is_active', true)])
            ->in($country)->ordered()->get()->groupBy('category');

        return view('admin.catalogue.index', [
            'pagetitle' => 'Services & Pricing',
            'country'   => $country,
            'countries' => Country::ordered()->get(),
            'services'  => $services,
            'providers' => $this->providers->options(),
            'balances'  => collect(['vtpass', 'termii'])->mapWithKeys(fn ($k) => [$k => $this->providers->get($k)->configured() ? rescue(fn () => $this->providers->get($k)->balance(), null, false) : null]),
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.catalogue.service', ['pagetitle' => 'New service', 's' => new Service(['country_code' => $request->query('country', 'NG'), 'category' => $request->query('category', 'airtime'), 'provider' => 'sandbox', 'is_active' => true]),
            'countries' => Country::ordered()->get(), 'providers' => $this->providers->options(), 'tiers' => PriceTier::orderBy('position')->get(), 'discounts' => collect()]);
    }

    public function store(Request $request)
    {
        $s = Service::create($this->serviceData($request));
        $this->saveDiscounts($request, $s);

        return redirect()->route('admin.services.edit', $s)->with('success', 'Service added.');
    }

    public function edit(Service $service)
    {
        return view('admin.catalogue.service', [
            'pagetitle' => $service->name, 's' => $service, 'countries' => Country::ordered()->get(),
            'providers' => $this->providers->options(), 'tiers' => PriceTier::orderBy('position')->get(),
            'discounts' => $service->discounts->keyBy('price_tier_id'),
            'products'  => $service->products()->get(),
        ]);
    }

    public function update(Request $request, Service $service)
    {
        $service->update($this->serviceData($request, $service));
        $this->saveDiscounts($request, $service);

        return back()->with('success', 'Service saved.');
    }

    public function toggle(Service $service)
    {
        $service->forceFill(['is_active' => !$service->is_active])->save();

        return back()->with('success', $service->name . ($service->is_active ? ' is on.' : ' is off.'));
    }

    /** Import plans from the provider. */
    public function sync(Service $service)
    {
        try {
            $r = $this->providers->sync($service);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not load plans from ' . $service->provider . ': ' . $e->getMessage());
        }

        return back()->with('success', "Plans loaded from the provider: {$r['added']} new, {$r['updated']} updated, {$r['retired']} switched off ({$r['total']} offered).");
    }

    /** Save the plan table (name, cost, price, validity, on/off) in one go, and add a new plan. */
    public function saveProducts(Request $request, Service $service)
    {
        $d = $request->validate([
            'products'            => 'array',
            'products.*.name'     => 'required|string|max:150',
            'products.*.cost'     => 'required|numeric|min:0',
            'products.*.price'    => 'required|numeric|min:0',
            'products.*.validity' => 'nullable|string|max:40',
            'new.code'            => 'nullable|string|max:80',
            'new.name'            => 'required_with:new.code|nullable|string|max:150',
            'new.cost'            => 'required_with:new.code|nullable|numeric|min:0',
            'new.price'           => 'required_with:new.code|nullable|numeric|min:0',
        ]);

        foreach ($d['products'] ?? [] as $id => $row) {
            $p = Product::where('service_id', $service->id)->find($id);
            if (!$p) continue;
            $p->update(['name' => $row['name'], 'cost' => $row['cost'], 'price' => $row['price'], 'validity' => $row['validity'] ?? null,
                'is_active' => !empty($request->input("products.$id.is_active"))]);
        }
        if (!empty($d['new']['code'])) {
            Product::updateOrCreate(['service_id' => $service->id, 'code' => trim($d['new']['code'])],
                ['name' => $d['new']['name'], 'cost' => $d['new']['cost'], 'price' => $d['new']['price'], 'is_active' => true]);
        }

        return back()->with('success', 'Plans saved.');
    }

    public function countries()
    {
        return view('admin.catalogue.countries', ['pagetitle' => 'Countries', 'countries' => Country::withCount('services')->ordered()->get()]);
    }

    public function toggleCountry(Country $country)
    {
        if ($country->is_active && $country->code === config('afritopup.default_country')) return back()->with('error', 'The default country cannot be switched off.');
        $country->forceFill(['is_active' => !$country->is_active])->save();

        return back()->with('success', $country->name . ($country->is_active ? ' is now open to customers.' : ' is now hidden from customers.'));
    }

    public function tiers()
    {
        return view('admin.catalogue.tiers', ['pagetitle' => 'Price tiers', 'tiers' => PriceTier::withCount('users')->orderBy('position')->get()]);
    }

    public function saveTier(Request $request, ?PriceTier $tier = null)
    {
        $d = $request->validate([
            'name' => 'required|string|max:60',
            'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('price_tiers', 'code')->ignore($tier?->id)],
            'description' => 'nullable|string|max:255',
        ]);
        $tier ??= new PriceTier(['position' => PriceTier::max('position') + 1]);
        $tier->fill($d)->save();
        if ($request->boolean('is_default')) {
            PriceTier::where('id', '!=', $tier->id)->update(['is_default' => false]);
            $tier->forceFill(['is_default' => true])->save();
        }

        return back()->with('success', 'Tier saved.');
    }

    protected function serviceData(Request $request, ?Service $s = null): array
    {
        $d = $request->validate([
            'category'      => ['required', Rule::in(array_keys(config('afritopup.categories')))],
            'country_code'  => 'required|exists:countries,code',
            'code'          => ['required', 'alpha_dash', 'max:60', Rule::unique('services')->where('country_code', $request->input('country_code'))->ignore($s?->id)],
            'name'          => 'required|string|max:100',
            'short_name'    => 'nullable|string|max:40',
            'logo'          => 'nullable|string|max:255',
            'color'         => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'provider'      => ['required', Rule::in(array_keys(ProviderManager::DRIVERS))],
            'provider_code' => 'nullable|string|max:80',
            'min_amount'    => 'nullable|numeric|min:0',
            'max_amount'    => 'nullable|numeric|min:0',
            'fee'           => 'nullable|numeric|min:0',
            'options'       => 'nullable|json',
            'position'      => 'nullable|integer|min:0|max:999',
        ]);
        $d['options'] = !empty($d['options']) ? json_decode($d['options'], true) : null;
        $d['fee'] = $d['fee'] ?? 0;
        $d['position'] = $d['position'] ?? 0;
        $d['is_active'] = $request->boolean('is_active');

        return $d;
    }

    protected function saveDiscounts(Request $request, Service $service): void
    {
        foreach ((array) $request->input('discounts', []) as $tierId => $row) {
            $value = (float) ($row['value'] ?? 0);
            $type = ($row['type'] ?? 'percent') === 'flat' ? 'flat' : 'percent';
            if ($type === 'percent') $value = min($value, 100);
            ServiceTierDiscount::updateOrCreate(['service_id' => $service->id, 'price_tier_id' => (int) $tierId], ['type' => $type, 'value' => max(0, $value)]);
        }
    }
}
