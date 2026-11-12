@extends('layouts.site')
@section('header', 'light')
@section('title', 'Prices — data plans, TV bouquets & exam PINs | ' . ($brand['name'] ?? 'AfriTopup'))
@section('description', 'Live prices for data bundles, DStv, GOtv and StarTimes bouquets, and exam PINs. No hidden fees.')

@php
    $cats = config('afritopup.categories');
    $register = Route::has('register') ? route('register') : route('login');
    $first = $groups->keys()->first();
@endphp

@section('content')
@include('site.partials.page-hero', ['crumb' => 'Pricing', 
    'eyebrow' => 'Pricing',
    'title' => 'Simple prices. No hidden fees.',
    'titleHtml' => 'Simple prices. <span class="s-grad">No hidden fees.</span>',
    'lead' => 'What you see is what you pay. Airtime and electricity cost exactly the amount you choose — plans and bouquets are listed below at today\'s price.',
    'aside' => count($countries) > 1 ? view('site.partials.country-picker', ['countries' => $countries, 'current' => $country->code])->render() : null,
])

<section class="s-section pt-4">
    <div class="container">
        {{-- Pay-what-you-choose services --}}
        <div class="row g-3 mb-5">
            @foreach([['airtime', 'Airtime', 'Pay the face value — from ₦50 per top-up.'], ['electricity', 'Electricity', 'Pay the amount you choose. Tokens delivered instantly.']] as $j => [$k, $t, $d])
                @if(isset($cats[$k]))
                    <div class="col-md-6" data-reveal style="--d: {{ $j * .08 }}s">
                        <a href="{{ route('site.service', $k) }}" class="s-info h-100 align-items-center">
                            <span class="s-ico s-ico--lg" style="--c: {{ $cats[$k]['color'] }}"><i class="{{ $cats[$k]['icon'] }}"></i></span>
                            <span class="flex-grow-1"><b class="fs-5">{{ $t }}</b><small class="d-block mt-1">{{ $d }}</small></span>
                            <span class="s-eyebrow">Face value</span>
                        </a>
                    </div>
                @endif
            @endforeach
        </div>

        @if($groups->isEmpty())
            <div class="s-alert s-alert--warn"><i class="ri-information-line"></i> Plans for {{ $country->name }} are being set up — check back soon.</div>
        @else
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                <div class="s-tabs" data-tabs="cat">
                    @foreach($groups as $k => $list)
                        <button type="button" data-tab="{{ $k }}" class="{{ $k === $first ? 'is-active' : '' }}"><i class="{{ $cats[$k]['icon'] ?? 'ri-apps-line' }}"></i> {{ $cats[$k]['name'] ?? ucfirst($k) }}</button>
                    @endforeach
                </div>
                <div class="s-search" style="min-width: 260px"><i class="ri-search-line"></i><input class="form-control" style="border-radius: 999px; padding: .65rem 1rem .65rem 2.4rem" placeholder="Search e.g. 2GB, Compact…" data-filter=".s-ptable tbody tr" aria-label="Search plans"></div>
            </div>

            @foreach($groups as $k => $list)
                <div data-pane="cat" data-key="{{ $k }}" @if($k !== $first) hidden @endif>
                    <div class="row g-4">
                        @foreach($list as $g)
                            <div class="col-lg-6" data-reveal>
                                <div class="s-ptable h-100">
                                    <div class="s-ptable-head">
                                        @include('topup.partials.service-icon', ['s' => $g['service'], 'size' => 34])
                                        <h3>{{ $g['service']->name }}</h3>
                                        <span class="ms-auto small fw-bold" style="color: var(--s-muted)">{{ count($g['plans']) }} {{ \Illuminate\Support\Str::plural('plan', count($g['plans'])) }}</span>
                                    </div>
                                    <table><tbody>
                                        @forelse($g['plans'] as $p)
                                            <tr>
                                                <td><b style="color: var(--s-ink)">{{ $p['name'] }}</b>@if($p['validity'] && !\Illuminate\Support\Str::contains(strtolower($p['name']), strtolower($p['validity'])))<br><small style="color: var(--s-muted)">{{ $p['validity'] }}</small>@endif</td>
                                                <td class="s-price text-end">{{ $p['label'] }}</td>
                                                <td class="text-end" style="width:1%"><a class="s-btn s-btn--sm s-buy" href="{{ auth()->check() ? route('topup.buy.form', [$k, 'service' => $g['service']->code, 'product' => $p['id']]) : $register }}">Buy</a></td>
                                            </tr>
                                        @empty
                                            <tr><td class="text-center py-4" style="color: var(--s-muted)">No plans right now.</td></tr>
                                        @endforelse
                                    </tbody></table>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif

        <div class="s-alert s-alert--ok mt-5" data-reveal>
            <i class="ri-store-2-line fs-4"></i>
            <div class="flex-grow-1">Selling to customers? <b>Agents and resellers buy below these prices</b> and keep the difference.</div>
            <a href="{{ route('site.page', 'business') }}" class="s-btn s-btn--sm">See business prices</a>
        </div>
    </div>
</section>

@include('site.partials.cta')
@endsection
