@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    @include('topup.partials.styles')
    <x-cb.hero :title="$s->exists ? $s->name : 'New service'" icon="ri-price-tag-3-line" subtitle="How this biller is sold, who fulfils it and at what price." :back="route('admin.services.index', ['country' => $s->country_code])" back-label="Services">
        @if($s->exists && $s->needsProduct())
            <x-slot:actions>
                <form method="POST" action="{{ route('admin.services.sync', $s) }}">@csrf<button class="cb-hero-btn border-0" @disabled($s->provider === 'sandbox' || $s->provider === 'manual') title="{{ $s->provider === 'sandbox' ? 'Switch to a live provider first' : '' }}"><i class="ri-download-cloud-2-line"></i>Load plans from {{ $providers[$s->provider] ?? $s->provider }}</button></form>
            </x-slot:actions>
        @endif
    </x-cb.hero>
    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-5">
            <x-cb.card title="Service" icon="ri-settings-3-line">
                <form method="POST" action="{{ $s->exists ? route('admin.services.update', $s) : route('admin.services.store') }}">
                    @csrf @if($s->exists) @method('PUT') @endif
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label small fw-semibold">Category</label><select class="form-select" name="category">@foreach(config('afritopup.categories') as $k => $c)<option value="{{ $k }}" @selected(old('category', $s->category) === $k)>{{ $c['name'] }}</option>@endforeach</select></div>
                        <div class="col-6"><label class="form-label small fw-semibold">Country</label><select class="form-select" name="country_code">@foreach($countries as $c)<option value="{{ $c->code }}" @selected(old('country_code', $s->country_code) === $c->code)>{{ $c->flag }} {{ $c->name }}</option>@endforeach</select></div>
                        <div class="col-8"><label class="form-label small fw-semibold">Name</label><input class="form-control" name="name" value="{{ old('name', $s->name) }}" required maxlength="100"></div>
                        <div class="col-4"><label class="form-label small fw-semibold">Short name</label><input class="form-control" name="short_name" value="{{ old('short_name', $s->short_name) }}" maxlength="40"></div>
                        <div class="col-6"><label class="form-label small fw-semibold">Code</label><input class="form-control at-mono" name="code" value="{{ old('code', $s->code) }}" required maxlength="60"><div class="form-text">Used by the reseller API.</div></div>
                        <div class="col-6"><label class="form-label small fw-semibold">Brand colour</label><input class="form-control form-control-color w-100" type="color" name="color" value="{{ old('color', $s->color ?: '#0B8457') }}"></div>
                        <div class="col-6"><label class="form-label small fw-semibold">Provider</label><select class="form-select" name="provider">@foreach($providers as $k => $l)<option value="{{ $k }}" @selected(old('provider', $s->provider) === $k)>{{ $l }}</option>@endforeach</select></div>
                        <div class="col-6"><label class="form-label small fw-semibold">Provider's code</label><input class="form-control at-mono" name="provider_code" value="{{ old('provider_code', $s->provider_code) }}" maxlength="80" placeholder="e.g. mtn-data"><div class="form-text">VTpass serviceID.</div></div>
                        <div class="col-4"><label class="form-label small fw-semibold">Min amount</label><input class="form-control" type="number" step="0.01" name="min_amount" value="{{ old('min_amount', $s->min_amount) }}"></div>
                        <div class="col-4"><label class="form-label small fw-semibold">Max amount</label><input class="form-control" type="number" step="0.01" name="max_amount" value="{{ old('max_amount', $s->max_amount) }}"></div>
                        <div class="col-4"><label class="form-label small fw-semibold">Flat fee</label><input class="form-control" type="number" step="0.01" name="fee" value="{{ old('fee', $s->fee ?? 0) }}"></div>
                        <div class="col-8"><label class="form-label small fw-semibold">Logo</label><input class="form-control" name="logo" value="{{ old('logo', $s->logo) }}" placeholder="images/services/mtn.png or https://…"></div>
                        <div class="col-4"><label class="form-label small fw-semibold">Order</label><input class="form-control" type="number" name="position" value="{{ old('position', $s->position ?? 0) }}"></div>
                        <div class="col-12"><label class="form-label small fw-semibold">Options (JSON)</label><textarea class="form-control at-mono" name="options" rows="3" placeholder='{"price_per_page": "4.00"}'>{{ old('options', $s->options ? json_encode($s->options, JSON_UNESCAPED_SLASHES) : '') }}</textarea>
                            <div class="form-text">Advanced: <code>price_per_page</code> (SMS), <code>profile_required</code> (JAMB), <code>school</code>, <code>product</code>/<code>amount</code>/<code>verify</code> to override the category, <code>recipient_label</code>.</div></div>
                    </div>

                    <h6 class="mt-4 mb-2">Discounts by price tier</h6>
                    <div class="table-responsive"><table class="table table-sm align-middle mb-2"><tbody>
                        @foreach($tiers as $tier)
                            @php $d = $discounts[$tier->id] ?? null; @endphp
                            <tr><td class="fw-semibold">{{ $tier->name }}@if($tier->is_default) <span class="badge bg-light text-muted">default</span>@endif</td>
                                <td style="width:110px"><select class="form-select form-select-sm" name="discounts[{{ $tier->id }}][type]"><option value="percent" @selected(($d->type ?? 'percent') === 'percent')>% off</option><option value="flat" @selected(($d->type ?? '') === 'flat')>flat off</option></select></td>
                                <td style="width:110px"><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="discounts[{{ $tier->id }}][value]" value="{{ $d->value ?? 0 }}"></td></tr>
                        @endforeach
                    </tbody></table></div>

                    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $s->is_active))><label class="form-check-label" for="act">Customers can buy this</label></div>
                    <button class="btn btn-primary w-100">{{ $s->exists ? 'Save service' : 'Add service' }}</button>
                </form>
            </x-cb.card>
        </div>

        @if($s->exists && $s->needsProduct())
            <div class="col-xl-7">
                <x-cb.card title="Plans" icon="ri-list-check-2" :count="$products->count()" :flush="true">
                    <form method="POST" action="{{ route('admin.services.products', $s) }}">@csrf
                        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th>On</th><th>Plan</th><th style="width:110px">Cost</th><th style="width:110px">Price</th><th style="width:110px">Validity</th><th class="text-end">Margin</th></tr></thead>
                            <tbody>
                            @foreach($products as $p)
                                <tr class="{{ $p->is_active ? '' : 'opacity-50' }}">
                                    <td><input class="form-check-input" type="checkbox" name="products[{{ $p->id }}][is_active]" value="1" @checked($p->is_active)></td>
                                    <td><input class="form-control form-control-sm" name="products[{{ $p->id }}][name]" value="{{ $p->name }}" maxlength="150"><div class="small text-muted at-mono">{{ $p->code }}@if(!empty($p->meta['sample'])) · sample @endif</div></td>
                                    <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="products[{{ $p->id }}][cost]" value="{{ $p->cost }}"></td>
                                    <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="products[{{ $p->id }}][price]" value="{{ $p->price }}"></td>
                                    <td><input class="form-control form-control-sm" name="products[{{ $p->id }}][validity]" value="{{ $p->validity }}" maxlength="40"></td>
                                    <td class="text-end small {{ $p->price < $p->cost ? 'text-danger' : 'text-success' }}">{{ number_format($p->price - $p->cost, 2) }}</td>
                                </tr>
                            @endforeach
                                <tr class="table-light"><td></td>
                                    <td><input class="form-control form-control-sm mb-1" name="new[code]" placeholder="New plan code" maxlength="80"><input class="form-control form-control-sm" name="new[name]" placeholder="Name" maxlength="150"></td>
                                    <td><input class="form-control form-control-sm" type="number" step="0.01" name="new[cost]" placeholder="Cost"></td>
                                    <td><input class="form-control form-control-sm" type="number" step="0.01" name="new[price]" placeholder="Price"></td>
                                    <td colspan="2"></td></tr>
                            </tbody>
                        </table></div>
                        <div class="p-3 d-flex justify-content-between align-items-center"><span class="small text-muted">Cost is what the provider charges; price is what default-tier customers pay. Tier discounts come off the price.</span><button class="btn btn-primary">Save plans</button></div>
                    </form>
                </x-cb.card>
            </div>
        @endif
    </div>
</div></div></div>
@endsection
