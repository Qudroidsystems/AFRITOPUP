@extends('layouts.site')
@section('header', 'light')
@section('title', 'About us | ' . ($brand['name'] ?? 'AfriTopup'))
@php $name = $brand['name'] ?? 'AfriTopup'; @endphp

@section('content')
@include('site.partials.page-hero', ['crumb' => 'About', 
    'eyebrow' => 'About ' . $name,
    'title' => 'Making everyday payments effortless across Africa',
    'titleHtml' => 'Making everyday payments <span class="s-grad">effortless</span> across Africa',
    'lead' => $name . ' is ' . lcfirst(rtrim($brand['tagline'] ?? 'a digital payment and recharge platform', '.')) . ' — one wallet for airtime, data, TV, electricity, education and SMS, for individuals and the businesses that serve them.',
])

<section class="s-section pt-4">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6" data-reveal="left">
                <h2 class="s-title">Why we built {{ $name }}</h2>
                <p class="s-lead">Paying for the basics shouldn't mean switching between USSD codes, bank apps, scratch cards and queues. We bring every recharge and bill into one fast, reliable place — and give agents and businesses the tools to earn from it.</p>
                <p class="s-lead">We start in Nigeria and are rolling out across the continent, with local airtime and data in each country's own currency.</p>
            </div>
            <div class="col-lg-6" data-reveal="right">
                <div class="row g-3">
                    @foreach([['ri-flashlight-line', '#0B8457', 'Speed', 'Most payments complete in seconds, and we keep checking the slow ones for you.'], ['ri-shield-check-line', '#2563EB', 'Trust', 'PIN-protected wallets, verified recipients and automatic refunds.'], ['ri-hand-heart-line', '#F59E0B', 'Fairness', 'Clear prices, no hidden fees, and discounts for those who sell.'], ['ri-global-line', '#7C3AED', 'Reach', 'Built for Africa — multi-country and multi-currency from day one.']] as [$ic, $c, $t, $d])
                        <div class="col-sm-6"><div class="s-card h-100 p-4"><span class="s-ico" style="--c: {{ $c }}"><i class="{{ $ic }}"></i></span><h3 class="h5 mt-3">{{ $t }}</h3><p class="mb-0" style="color: var(--s-muted)">{{ $d }}</p></div></div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

<section class="s-section--tight">
    <div class="container">
        <div class="s-stats" data-reveal>
            <div class="s-stat"><strong><span data-count="{{ $stats['billers'] }}">{{ $stats['billers'] }}</span><sup>+</sup></strong><span>Networks &amp; billers</span></div>
            <div class="s-stat"><strong><span data-count="{{ $stats['plans'] }}">{{ $stats['plans'] }}</span><sup>+</sup></strong><span>Plans &amp; bouquets</span></div>
            <div class="s-stat"><strong><span data-count="{{ $stats['categories'] }}">{{ $stats['categories'] }}</span></strong><span>Kinds of payment</span></div>
            <div class="s-stat"><strong>24/7</strong><span>Always on</span></div>
        </div>
    </div>
</section>

@include('site.partials.cta')
@endsection
