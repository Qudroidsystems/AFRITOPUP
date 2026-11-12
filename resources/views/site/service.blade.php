@extends('layouts.site')

@php
    $name = $brand['name'] ?? 'AfriTopup';
    $services = collect($entry['services'] ?? []);
    $canQuickPay = $entry && in_array($key, ['airtime', 'data', 'cable', 'electricity', 'education'], true);
    $register = Route::has('register') ? route('register') : route('login');
    $steps = [
        'airtime'     => [['ri-smartphone-line', 'Enter the number', 'We detect the network for you.'], ['ri-money-dollar-circle-line', 'Choose an amount', 'From ₦50 — or tap a quick amount.'], ['ri-flashlight-line', 'Pay with your PIN', 'Airtime lands in seconds.']],
        'data'        => [['ri-smartphone-line', 'Enter the number', 'Any network, any line.'], ['ri-list-check-2', 'Pick a bundle', 'Daily, weekly or monthly plans.'], ['ri-wifi-line', 'Pay with your PIN', 'Data is active in seconds.']],
        'cable'       => [['ri-tv-2-line', 'Enter your smartcard', 'We show the name on the account.'], ['ri-list-check-2', 'Choose a bouquet', 'Renew or change your plan.'], ['ri-checkbox-circle-line', 'Pay with your PIN', 'Your TV is back on in minutes.']],
        'electricity' => [['ri-dashboard-3-line', 'Enter your meter', 'Prepaid or postpaid, any DisCo.'], ['ri-money-dollar-circle-line', 'Choose an amount', 'We verify the meter owner first.'], ['ri-key-2-line', 'Get your token', 'On screen, by SMS and e-mail.']],
        'education'   => [['ri-graduation-cap-line', 'Choose the exam', 'WAEC, JAMB or school fees.'], ['ri-user-line', 'Enter the details', 'Phone number or profile ID.'], ['ri-key-2-line', 'Get your PIN', 'Shown on your receipt instantly.']],
        'sms'         => [['ri-contacts-line', 'Add recipients', 'Paste numbers or upload a list.'], ['ri-edit-2-line', 'Write your message', 'Use your own sender ID.'], ['ri-send-plane-line', 'Send instantly', 'Delivery reports in your history.']],
    ][$key] ?? [];
@endphp

@section('title', $cat['name'] . ' — ' . $blurb[0] . ' | ' . $name)
@section('description', $blurb[1])

@section('content')
<section class="s-hero" style="padding-bottom: 120px">
    <span class="s-blob s-blob--1"></span><span class="s-blob s-blob--2"></span>
    <div class="container position-relative">
        <div class="row align-items-center g-5">
            <div class="col-lg-6" data-reveal>
                <div class="s-crumbs" style="color: rgba(255,255,255,.6)"><a href="{{ route('home') }}" style="color: rgba(255,255,255,.6)">Home</a><span>/</span><span>{{ $cat['name'] }}</span></div>
                <h1 class="mt-3">{{ $blurb[0] }}</h1>
                <p class="s-lead">{{ $blurb[1] }}</p>
                @if($services->isNotEmpty())
                    <div class="d-flex flex-wrap gap-2 mt-4">
                        @foreach($services as $s)
                            <span class="s-biller" style="background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.14); color: #fff">
                                <span class="s-tile" style="--c: {{ $s['color'] }}; color: {{ $s['ink'] }}">@if($s['logo'])<img src="{{ $s['logo'] }}" alt="">@else{{ mb_strtoupper(mb_substr($s['short'], 0, 3)) }}@endif</span>{{ $s['short'] }}
                            </span>
                        @endforeach
                    </div>
                @endif
                @if(count($countries) > 1)
                    <form method="GET" class="mt-4 d-inline-flex align-items-center gap-2" style="color: rgba(255,255,255,.75)">
                        <i class="ri-global-line"></i>
                        <select name="country" class="form-select form-select-sm" style="width: auto; border-radius: 10px" onchange="this.form.submit()" aria-label="Country">
                            @foreach($countries as $c)<option value="{{ $c->code }}" @selected($c->code === $catalogue['country']['code'])>{{ $c->flag }} {{ $c->name }}</option>@endforeach
                        </select>
                    </form>
                @endif
            </div>
            <div class="col-lg-6 col-xl-5 offset-xl-1" data-reveal="zoom">
                @if($canQuickPay)
                    @include('site.partials.quickpay', ['catalogue' => $catalogue, 'only' => $key])
                @else
                    <div class="qp"><div class="qp-body text-center py-5">
                        <span class="s-ico s-ico--lg mb-3" style="--c: {{ $cat['color'] }}"><i class="{{ $cat['icon'] }}"></i></span>
                        <h3>{{ $entry ? 'Ready when you are' : 'Coming soon here' }}</h3>
                        <p class="qp-sub mb-4">{{ $entry ? 'Sign in to compose your message, choose a sender ID and send.' : 'This service is not available in ' . $catalogue['country']['name'] . ' yet.' }}</p>
                        @if($entry)
                            <a href="{{ auth()->check() ? route('topup.buy.form', $key) : $register }}" class="s-btn s-btn--lg">{{ auth()->check() ? 'Open ' . $cat['name'] : 'Create free account' }} <i class="ri-arrow-right-line"></i></a>
                        @endif
                    </div></div>
                @endif
            </div>
        </div>
    </div>
    <div class="s-hero-bottom"></div>
</section>

@if($steps)
<section class="s-section--tight pt-4">
    <div class="container">
        <div class="s-steps">
            @foreach($steps as $j => [$ic, $t, $d])
                <div class="s-step" data-reveal style="--d: {{ $j * .1 }}s">
                    <div class="s-step-num"><i class="{{ $ic }}"></i></div>
                    <h3>{{ $t }}</h3><p>{{ $d }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($plans)
<section class="s-section s-section--soft" id="prices">
    <div class="container">
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4" data-reveal>
            <div>
                <span class="s-eyebrow">Live prices</span>
                <h2 class="s-title mb-0">{{ $key === 'cable' ? 'Bouquets' : 'Plans' }} &amp; prices</h2>
            </div>
            <div class="s-search" style="min-width: 260px"><i class="ri-search-line"></i><input class="form-control" style="border-radius: 999px; padding: .65rem 1rem .65rem 2.4rem" placeholder="Search plans…" data-filter="#plans tbody tr"></div>
        </div>
        <div class="s-tabs mb-4" data-tabs="plans">
            @foreach($plans as $j => $g)
                <button type="button" data-tab="p{{ $g['service']->id }}" class="{{ $j === 0 ? 'is-active' : '' }}">@include('topup.partials.service-icon', ['s' => $g['service'], 'size' => 22]) {{ $g['service']->display_name }}</button>
            @endforeach
        </div>
        <div id="plans">
            @foreach($plans as $j => $g)
                <div class="s-ptable" data-pane="plans" data-key="p{{ $g['service']->id }}" @if($j > 0) hidden @endif>
                    <table>
                        <tbody>
                        @forelse($g['plans'] as $p)
                            <tr>
                                <td><b style="color: var(--s-ink)">{{ $p['name'] }}</b>@if($p['validity'])<br><small style="color: var(--s-muted)">{{ $p['validity'] }}</small>@endif</td>
                                <td class="s-price text-end">{{ $p['label'] }}</td>
                                <td class="text-end" style="width: 1%"><a class="s-btn s-btn--sm s-buy" href="{{ auth()->check() ? route('topup.buy.form', [$key, 'service' => $g['service']->code, 'product' => $p['id']]) : $register }}">Buy</a></td>
                            </tr>
                        @empty
                            <tr><td class="text-center py-4" style="color: var(--s-muted)">No plans available right now.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
        <p class="small mt-3 mb-0" style="color: var(--s-muted)"><i class="ri-information-line"></i> Prices shown are for customers. <a href="{{ route('site.page', 'business') }}">Agents and resellers pay less.</a></p>
    </div>
</section>
@endif

<section class="s-section">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4" data-reveal>
                <span class="s-eyebrow">FAQ</span>
                <h2 class="s-title">{{ $cat['name'] }} questions</h2>
                <a href="{{ route('site.page', 'faq') }}" class="s-link">All help topics <i class="ri-arrow-right-line"></i></a>
            </div>
            <div class="col-lg-8" data-reveal="right">
                <div class="s-faq">
                    @foreach(array_slice($faqs, 0, 6) as $j => [$q, $a])
                        <details @if($j === 0) open @endif><summary>{{ $q }}<i class="ri-add-line"></i></summary><div class="s-faq-a">{{ $a }}</div></details>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

@include('site.partials.cta')
@endsection
