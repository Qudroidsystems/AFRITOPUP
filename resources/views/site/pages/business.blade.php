@extends('layouts.site')
@section('title', 'Agents & resellers — earn on every payment | ' . ($brand['name'] ?? 'AfriTopup'))
@php
    $name = $brand['name'] ?? 'AfriTopup';
    $register = Route::has('register') ? route('register') : route('login');
    $disc = \Database\Seeders\CatalogueSeeder::DISCOUNTS;
    $cats = config('afritopup.categories');
@endphp

@section('content')
@include('site.partials.page-hero', ['crumb' => 'Business', 
    'dark' => true,
    'eyebrow' => 'For business',
    'title' => 'Earn on every recharge and bill you sell',
    'titleHtml' => 'Earn on every recharge and bill <span style="color: var(--s-gold)">you sell</span>',
    'lead' => 'Run a shop, a POS stand, a cyber café or an online store? Buy airtime, data, TV and electricity at a discount from your ' . $name . ' wallet and sell at the regular price.',
])

<section class="s-section">
    <div class="container">
        <div class="s-head text-center" data-reveal>
            <span class="s-eyebrow">Your margin</span>
            <h2 class="s-title">Discounts by category</h2>
            <p class="s-lead">Typical discounts off the customer price. Exact rates can vary by network and are shown in your account before you buy.</p>
        </div>
        <div class="s-ptable" data-reveal>
            <table>
                <thead><tr style="background: var(--s-soft)"><td><b>Service</b></td><td class="text-center"><b>Agent</b></td><td class="text-center"><b>Reseller</b></td></tr></thead>
                <tbody>
                @foreach($disc as $k => [$agent, $reseller])
                    @continue(!isset($cats[$k]))
                    <tr>
                        <td><span class="d-inline-flex align-items-center gap-2"><span class="s-ico s-ico--sm" style="--c: {{ $cats[$k]['color'] }}"><i class="{{ $cats[$k]['icon'] }}"></i></span><b style="color: var(--s-ink)">{{ $cats[$k]['name'] }}</b></span></td>
                        <td class="text-center s-price">{{ $agent > 0 ? 'up to ' . rtrim(rtrim(number_format($agent, 1), '0'), '.') . '%' : '—' }}</td>
                        <td class="text-center s-price" style="color: var(--s-green)">{{ $reseller > 0 ? 'up to ' . rtrim(rtrim(number_format($reseller, 1), '0'), '.') . '%' : '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="s-section s-dark">
    <div class="container position-relative">
        <div class="row g-4">
            @foreach([
                ['Agent', 'ri-store-2-line', 'For shops, POS operators and individuals', ['Agent prices on every sale', 'Higher wallet and daily limits after ID check', 'Sales history to reconcile your day', 'Referral earnings from customers you bring']],
                ['Reseller', 'ri-code-box-line', 'For websites, apps and large volumes', ['Our lowest wholesale prices', 'REST API with live and test keys', 'Webhooks when purchases complete', 'White-label: your brand, our rails']],
            ] as $j => [$t, $ic, $sub, $items])
                <div class="col-lg-6" data-reveal style="--d: {{ $j * .1 }}s">
                    <div class="s-plan-card {{ $j === 0 ? 'is-featured' : '' }}">
                        <span class="s-ico s-ico--lg" style="background: rgba(255,255,255,.1); color: var(--s-gold)"><i class="{{ $ic }}"></i></span>
                        <h3 class="mt-3 mb-1">{{ $t }}</h3>
                        <div style="color: rgba(255,255,255,.6)">{{ $sub }}</div>
                        <ul>@foreach($items as $it)<li><i class="ri-checkbox-circle-fill"></i>{{ $it }}</li>@endforeach</ul>
                        <a href="{{ auth()->check() ? route('dashboard') : $register }}" class="s-btn {{ $j === 0 ? 's-btn--gold' : 's-btn--light' }}">{{ auth()->check() ? 'Request an upgrade' : 'Create account to apply' }} <i class="ri-arrow-right-line"></i></a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="s-section">
    <div class="container">
        <div class="s-head text-center" data-reveal><span class="s-eyebrow">How to start</span><h2 class="s-title">Selling in three steps</h2></div>
        <div class="s-steps">
            @foreach([['ri-user-add-line', 'Create an account', 'Sign up free on the website or the app.'], ['ri-id-card-line', 'Request an upgrade', 'Tell us about your business and verify your ID.'], ['ri-money-dollar-circle-line', 'Start earning', 'Fund your wallet and sell at your own price.']] as $j => [$ic, $t, $d])
                <div class="s-step" data-reveal style="--d: {{ $j * .1 }}s"><div class="s-step-num"><i class="{{ $ic }}"></i></div><h3>{{ $t }}</h3><p>{{ $d }}</p></div>
            @endforeach
        </div>
    </div>
</section>

@include('site.partials.cta', ['ctaTitle' => 'Ready to grow your business?', 'ctaText' => 'Open your account today — upgrading to agent or reseller takes minutes.'])
@endsection
