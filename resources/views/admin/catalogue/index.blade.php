@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    @include('topup.partials.styles')
    <x-cb.hero title="Services & Pricing" icon="ri-price-tag-3-line" subtitle="Billers, their plans and prices, and which provider fulfils each one.">
        <x-slot:actions>
            <a href="{{ route('admin.services.create', ['country' => $country]) }}" class="cb-hero-btn"><i class="ri-add-line"></i>New service</a>
            <a href="{{ route('admin.tiers.index') }}" class="cb-hero-btn"><i class="ri-percent-line"></i>Price tiers</a>
        </x-slot:actions>
    </x-cb.hero>
    @include('partials.flash')

    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        @foreach($countries as $c)
            <a href="{{ route('admin.services.index', ['country' => $c->code]) }}" class="btn btn-sm {{ $c->code === $country ? 'btn-primary' : 'btn-light' }}">{{ $c->flag }} {{ $c->name }}@unless($c->is_active) <span class="opacity-75">(hidden)</span>@endunless</a>
        @endforeach
        <span class="ms-auto small text-muted">
            @foreach($balances as $k => $b) {{ ucfirst($k) }}: <strong>{{ $b === null ? 'not connected' : \App\Support\Money::format($b) }}</strong>@if(!$loop->last) · @endif @endforeach
        </span>
    </div>

    @if($services->isEmpty())
        <x-cb.card><div class="empty-state"><i class="ri-price-tag-3-line"></i><h6>No services in this country yet</h6><p>Add the billers you will sell here, and switch the country on under <a href="{{ route('admin.countries.index') }}">Countries</a>.</p></div></x-cb.card>
    @endif

    @foreach(config('afritopup.categories') as $key => $cat)
        @continue(!$services->has($key))
        <x-cb.card :title="$cat['name']" :icon="$cat['icon']" :count="$services[$key]->count()" :flush="true">
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Service</th><th>Provider</th><th>Plans</th><th>Limits / fee</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach($services[$key] as $s)
                    <tr>
                        <td data-label="Service"><div class="d-flex align-items-center gap-2">@include('topup.partials.service-icon', ['s' => $s, 'size' => 32])<div><a href="{{ route('admin.services.edit', $s) }}" class="fw-semibold">{{ $s->name }}</a><div class="small text-muted at-mono">{{ $s->code }}</div></div></div></td>
                        <td data-label="Provider"><span class="badge {{ $s->provider === 'sandbox' ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success' }}">{{ $providers[$s->provider] ?? $s->provider }}</span>@if($s->provider_code)<div class="small text-muted at-mono">{{ $s->provider_code }}</div>@endif</td>
                        <td data-label="Plans">@if($s->needsProduct()){{ $s->active_products_count }} / {{ $s->products_count }} on @else <span class="text-muted">—</span>@endif</td>
                        <td data-label="Limits" class="small">@if($s->min_amount !== null){{ number_format($s->min_amount) }}–{{ number_format($s->max_amount) }}@endif @if((float) $s->fee > 0)<div>fee {{ number_format($s->fee, 2) }}</div>@endif</td>
                        <td data-label="Status">
                            <form method="POST" action="{{ route('admin.services.toggle', $s) }}">@csrf<button class="btn btn-sm {{ $s->is_active ? 'btn-soft-success' : 'btn-soft-secondary' }}">{{ $s->is_active ? 'On' : 'Off' }}</button></form>
                        </td>
                        <td class="text-end"><a href="{{ route('admin.services.edit', $s) }}" class="btn btn-sm btn-light">Edit</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </x-cb.card>
    @endforeach
</div></div></div>
@endsection
