@extends('layouts.site')

@php
    $name = $brand['name'] ?? 'AfriTopup';
    $byKey = collect($catalogue['categories'])->keyBy('key');
    $active = $countries->pluck('code')->all();
    $soon = collect(['GH' => ['Ghana', '🇬🇭'], 'KE' => ['Kenya', '🇰🇪'], 'UG' => ['Uganda', '🇺🇬'], 'TZ' => ['Tanzania', '🇹🇿'], 'ZA' => ['South Africa', '🇿🇦'], 'RW' => ['Rwanda', '🇷🇼'], 'CM' => ['Cameroon', '🇨🇲'], 'CI' => ["Côte d'Ivoire", '🇨🇮']])->except($active);
    $register = Route::has('register') ? route('register') : route('login');
@endphp

@section('content')

{{-- ================= HERO ================= --}}
<section class="s-hero">
    <span class="s-blob s-blob--1"></span><span class="s-blob s-blob--2"></span>
    <div class="container position-relative">
        <div class="row align-items-center g-5">
            <div class="col-lg-6" data-reveal>
                <span class="s-eyebrow s-eyebrow--light"><i class="ri-flashlight-fill" style="color: var(--s-gold)"></i> {{ $brand['tagline'] ?? 'Recharge & pay bills' }}</span>
                <h1>
                    Pay for
                    <span class="s-rotator"><span>airtime</span><span>data</span><span>DStv &amp; GOtv</span><span>electricity</span><span>exam PINs</span></span><br>
                    in <span class="s-hl">seconds.<svg viewBox="0 0 300 14" preserveAspectRatio="none" aria-hidden="true"><path d="M2 10 C 80 2, 200 2, 298 8" stroke="currentColor" stroke-width="4" fill="none" stroke-linecap="round"/></svg></span>
                </h1>
                <p class="s-lead">One wallet for every bill. Fund it by card or bank transfer, then top up any line, renew your TV and buy power tokens — with instant delivery and a receipt every time.</p>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    @auth
                        <a href="{{ route('dashboard') }}" class="s-btn s-btn--gold s-btn--lg">Go to my account <i class="ri-arrow-right-line"></i></a>
                    @else
                        <a href="{{ $register }}" class="s-btn s-btn--gold s-btn--lg">Create free account <i class="ri-arrow-right-line"></i></a>
                    @endauth
                    <a href="{{ route('site.pricing') }}" class="s-btn s-btn--light s-btn--lg">See prices</a>
                </div>
                <div class="s-hero-trust">
                    <span><i class="ri-flashlight-line"></i> Instant delivery</span>
                    <span><i class="ri-shield-keyhole-line"></i> PIN-protected wallet</span>
                    <span><i class="ri-refund-2-line"></i> Auto-refund on failure</span>
                </div>
                <div class="s-hero-apps">
                    <span>Also on mobile</span>
                    @include('site.partials.app-badges', ['size' => 'sm', 'light' => true])
                </div>
            </div>
            <div class="col-lg-6 col-xl-5 offset-xl-1" data-reveal="zoom" style="--d:.15s">
                @include('site.partials.quickpay', ['catalogue' => $catalogue])
            </div>
        </div>
    </div>
    <div class="s-hero-bottom"></div>
</section>

{{-- ================= WORKS WITH ================= --}}
@if($billers->isNotEmpty())
<section class="pt-2 pb-4">
    <div class="container">
        <p class="text-center fw-bold small text-uppercase mb-3" style="letter-spacing:.1em;color:var(--s-muted)">Works with the networks and billers you already use</p>
        <div class="s-marquee">
            <div class="s-marquee-track">
                @foreach([1, 2] as $loopCopy)
                    @foreach($billers as $b)
                        <span class="s-biller" @if($loopCopy === 2) aria-hidden="true" @endif>
                            <span class="s-tile" style="--c: {{ $b->color ?: '#0B8457' }}; color: {{ \App\Support\Color::ink($b->color ?: '#0B8457') }}">@if($b->logo_url)<img src="{{ $b->logo_url }}" alt="" loading="lazy">@else{{ mb_strtoupper(mb_substr($b->display_name, 0, 3)) }}@endif</span>
                            {{ $b->display_name }}
                        </span>
                    @endforeach
                @endforeach
            </div>
        </div>
    </div>
</section>
@endif

{{-- ================= SERVICES ================= --}}
<section class="s-section" id="services">
    <div class="container">
        <div class="s-head text-center" data-reveal>
            <span class="s-eyebrow">Everything in one place</span>
            <h2 class="s-title">One app. <span class="s-grad">Every payment.</span></h2>
            <p class="s-lead">Stop juggling USSD codes, scratch cards and queues. Pay for everything you use every month from a single wallet.</p>
        </div>
        <div class="s-bento">
            @php $i = 0; @endphp
            @php
                // airtime + data wide on top, three cards, then SMS as a full-width banner.
                $order = collect(config('afritopup.categories'))->sortBy(fn ($c, $k) => array_search($k, ['airtime', 'data', 'cable', 'electricity', 'education', 'sms']));
            @endphp
            @foreach($order as $k => $c)
                @php
                    $svcs = collect($byKey[$k]['services'] ?? []);
                    $class = match (true) { $i === 0 => 's-svc--wide', $i === 1 => 's-svc--wide s-svc--feature', $k === 'sms' => 's-svc--banner', default => '' };
                    $i++;
                @endphp
                <a href="{{ route('site.service', $k) }}" class="s-svc {{ $class }}" style="--c: {{ $c['color'] }}; --d: {{ $i * 0.06 }}s" data-reveal>
                    <span class="s-ico s-ico--lg" style="--c: {{ $c['color'] }}"><i class="{{ $c['icon'] }}"></i></span>
                    <div>
                        <h3>{{ $c['name'] }}</h3>
                        <p class="mt-2">{{ $blurbs[$k][1] ?? '' }}</p>
                    </div>
                    @if($svcs->isNotEmpty() && $k !== 'sms')
                        <div class="s-svc-nets">
                            @foreach($svcs->take(5) as $s)
                                <span class="s-tile" style="--c: {{ $s['color'] }}; color: {{ $s['ink'] }}" title="{{ $s['short'] }}">@if($s['logo'])<img src="{{ $s['logo'] }}" alt="">@else{{ mb_strtoupper(mb_substr($s['short'], 0, 2)) }}@endif</span>
                            @endforeach
                            @if($svcs->count() > 5)<span class="s-tile" style="--c:#94A3B8">+{{ $svcs->count() - 5 }}</span>@endif
                        </div>
                    @endif
                    <span class="s-svc-go">{{ \App\Http\Controllers\Site\SiteController::CTA[$k] ?? 'Pay now' }} <i class="ri-arrow-right-line"></i></span>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- ================= HOW IT WORKS ================= --}}
<section class="s-section s-section--soft">
    <div class="container">
        <div class="s-head text-center" data-reveal>
            <span class="s-eyebrow">How it works</span>
            <h2 class="s-title">Ready in under a minute</h2>
            <p class="s-lead">No paperwork, no app required — the website does everything the app does.</p>
        </div>
        <div class="s-steps">
            <div class="s-step" data-reveal>
                <div class="s-step-num"><i class="ri-user-add-line"></i></div>
                <h3>Create your account</h3>
                <p>Sign up with your e-mail, Google or Apple. Confirm your e-mail and set a 4-digit PIN.</p>
            </div>
            <div class="s-step" data-reveal style="--d:.12s">
                <div class="s-step-num"><i class="ri-bank-line"></i></div>
                <h3>Fund your wallet</h3>
                <p>Pay by card, or transfer to your personal account number — it credits automatically.</p>
            </div>
            <div class="s-step" data-reveal style="--d:.24s">
                <div class="s-step-num"><i class="ri-checkbox-circle-line"></i></div>
                <h3>Pay &amp; relax</h3>
                <p>Choose a service, confirm with your PIN, and get your receipt, token or PIN instantly.</p>
            </div>
        </div>
    </div>
</section>

{{-- ================= FEATURES + PHONE ================= --}}
<section class="s-section">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6 order-lg-2" data-reveal="right">
                <span class="s-eyebrow s-eyebrow--gold">Built for speed</span>
                <h2 class="s-title">Made for the way you pay</h2>
                <p class="s-lead mb-4">Small touches that save you time on every payment.</p>
                <div class="row g-2">
                    @foreach([
                        ['ri-radar-line', '#0B8457', 'Network auto-detect', 'Type a number and we pick the right network for you.'],
                        ['ri-contacts-book-3-line', '#2563EB', 'Saved recipients', 'Numbers, smartcards and meters you pay are one tap away.'],
                        ['ri-file-text-line', '#7C3AED', 'Instant receipts', 'Tokens and PINs on screen, by e-mail and in your history.'],
                        ['ri-refund-2-line', '#F59E0B', 'Automatic refunds', 'If a network fails, your wallet is refunded straight away.'],
                        ['ri-global-line', '#0EA5E9', 'Across Africa', 'Switch country to buy local airtime and data abroad.'],
                        ['ri-lock-2-line', '#EC4899', 'Protected by PIN', 'Nothing leaves your wallet without your transaction PIN.'],
                    ] as $j => [$icon, $color, $t, $d])
                        <div class="col-sm-6" data-reveal style="--d: {{ $j * .05 }}s">
                            <div class="s-feat">
                                <span class="s-ico" style="--c: {{ $color }}"><i class="{{ $icon }}"></i></span>
                                <div><h4>{{ $t }}</h4><p>{{ $d }}</p></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="col-lg-6 order-lg-1" data-reveal="left">
                <div class="s-phone-stage">
                    <div class="s-float-card s-float-card--1"><span class="s-ico s-ico--sm"><i class="ri-checkbox-circle-fill"></i></span><div><b>Delivered</b><small>₦1,000 MTN airtime</small></div></div>
                    <div class="s-float-card s-float-card--2"><span class="s-ico s-ico--sm" style="--c:#F59E0B"><i class="ri-flashlight-fill"></i></span><div><b>Token ready</b><small>5523 8810 4471 …</small></div></div>
                    <div class="s-phone" aria-hidden="true">
                        <div class="s-phone-screen">
                            <div class="s-ph-top">
                                <small>Wallet balance</small>
                                <div class="s-ph-bal">₦48,250.00</div>
                                <div class="s-ph-actions"><span>+ Fund</span><span>Transfer</span><span>History</span></div>
                            </div>
                            <div class="s-ph-grid">
                                @foreach(config('afritopup.categories') as $k => $c)
                                    @if($loop->index < 8)
                                        <div><i style="background: color-mix(in srgb, {{ $c['color'] }} 14%, #fff); color: {{ $c['color'] }}" class="{{ $c['icon'] }}"></i>{{ \Illuminate\Support\Str::before($c['name'], ' ') }}</div>
                                    @endif
                                @endforeach
                                <div><i style="background:#EEF2F7;color:#475569" class="ri-gift-line"></i>Rewards</div>
                                <div><i style="background:#EEF2F7;color:#475569" class="ri-more-fill"></i>More</div>
                            </div>
                            <div class="s-ph-list">
                                <h6>Recent</h6>
                                <div class="s-ph-row"><span class="s-tile" style="--c:#FFCC00;color:#111">MTN</span><div><b>MTN Airtime</b><small>0803 *** 4567</small></div><em>₦1,000</em></div>
                                <div class="s-ph-row"><span class="s-tile" style="--c:#0096D6">DSTV</span><div><b>DStv Compact</b><small>Renewed · 30 days</small></div><em>₦19,000</em></div>
                                <div class="s-ph-row"><span class="s-tile" style="--c:#E30613">IKE</span><div><b>Ikeja Electric</b><small>Prepaid token</small></div><em>₦5,000</em></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ================= STATS ================= --}}
<section class="s-section--tight">
    <div class="container">
        <div class="s-stats" data-reveal>
            <div class="s-stat"><strong><span data-count="{{ $stats['billers'] }}">{{ $stats['billers'] }}</span><sup>+</sup></strong><span>Networks &amp; billers</span></div>
            <div class="s-stat"><strong><span data-count="{{ $stats['plans'] }}">{{ $stats['plans'] }}</span><sup>+</sup></strong><span>Plans &amp; bouquets</span></div>
            <div class="s-stat"><strong><span data-count="{{ $stats['countries'] }}">{{ $stats['countries'] }}</span></strong><span>{{ \Illuminate\Support\Str::plural('Country', $stats['countries']) }} live</span></div>
            <div class="s-stat"><strong>24/7</strong><span>Automatic wallet funding</span></div>
        </div>
    </div>
</section>

{{-- ================= COUNTRIES ================= --}}
<section class="s-section">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-5" data-reveal>
                <span class="s-eyebrow">Pan-African</span>
                <h2 class="s-title">Built in Nigeria, growing across Africa</h2>
                <p class="s-lead">Send airtime and data home, or top up while you travel. One account, a wallet in each country's currency.</p>
            </div>
            <div class="col-lg-7" data-reveal="right">
                <div class="s-flags">
                    @foreach($countries as $c)
                        <span class="s-flag"><em>{{ $c->flag }}</em>{{ $c->name }}</span>
                    @endforeach
                    @foreach($soon as [$cn, $flag])
                        <span class="s-flag is-soon"><em>{{ $flag }}</em>{{ $cn }} <small>SOON</small></span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ================= BUSINESS ================= --}}
<section class="s-section s-dark">
    <div class="container position-relative">
        <div class="s-head text-center" data-reveal>
            <span class="s-eyebrow s-eyebrow--light">For business</span>
            <h2 class="s-title">Earn while your customers pay</h2>
            <p class="s-lead">Sell airtime, data and bill payments to your customers at your own price — from a shop, a website or your own app.</p>
        </div>
        <div class="row g-4">
            @foreach([
                ['Customer', 'For everyday payments', ['Every network and biller', 'Card & bank-transfer funding', 'Saved recipients & receipts', 'Referral rewards'], 'Sign up free', $register, false],
                ['Agent', 'For shops & POS operators', ['Discounted prices on every sale', 'Higher wallet & daily limits', 'Sales history to reconcile', 'Priority support'], 'Become an agent', route('site.page', 'business'), true],
                ['Reseller', 'For websites & apps', ['Our best wholesale prices', 'REST API with API keys', 'Webhooks for every purchase', 'White-label ready'], 'Get API access', route('site.page', 'developers'), false],
            ] as $j => [$t, $sub, $items, $cta, $href, $featured])
                <div class="col-lg-4" data-reveal style="--d: {{ $j * .1 }}s">
                    <div class="s-plan-card {{ $featured ? 'is-featured' : '' }}">
                        @if($featured)<span class="s-badge">MOST POPULAR</span>@endif
                        <h3 class="mb-1">{{ $t }}</h3>
                        <div style="color: rgba(255,255,255,.6)">{{ $sub }}</div>
                        <ul>@foreach($items as $it)<li><i class="ri-checkbox-circle-fill"></i>{{ $it }}</li>@endforeach</ul>
                        <a href="{{ $href }}" class="s-btn {{ $featured ? 's-btn--gold' : 's-btn--light' }} s-btn--block">{{ $cta }} <i class="ri-arrow-right-line"></i></a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ================= DEVELOPERS ================= --}}
<section class="s-section">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-5" data-reveal>
                <span class="s-eyebrow">Developers</span>
                <h2 class="s-title">Plug {{ $name }} into your product</h2>
                <p class="s-lead mb-4">A simple REST API for airtime, data, TV, electricity and exam PINs. Idempotent purchases, instant webhooks, and a sandbox to build against.</p>
                <a href="{{ route('site.page', 'developers') }}" class="s-btn s-btn--dark">Read the API guide <i class="ri-arrow-right-line"></i></a>
            </div>
            <div class="col-lg-7" data-reveal="right">
                <div class="s-code">
                    <div class="s-code-bar"><i></i><i></i><i></i><span>purchase.sh</span><button type="button" data-copy="#codeSample">Copy</button></div>
<pre id="codeSample"><span class="k">curl</span> -X POST {{ url('/api/reseller/v1/purchase') }} \
  -H <span class="s">"X-API-Key: afk_live_••••••••"</span> \
  -H <span class="s">"Content-Type: application/json"</span> \
  -d <span class="s">'{
    "service": "mtn-data",
    "product": "mtn-1gb-30d",
    "recipient": "08031234567",
    "client_reference": "order-1029"
  }'</span>

<span class="c"># → 200 OK</span>
{ <span class="n">"status"</span>: <span class="s">"successful"</span>, <span class="n">"reference"</span>: <span class="s">"AT-8F2K1Q"</span>, <span class="n">"total"</span>: <span class="k">980.00</span> }</pre>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ================= APP ================= --}}
<section class="s-section pt-0" id="app" style="scroll-margin-top: 90px">
    <div class="container">
        <div class="s-app" data-reveal>
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <span class="s-eyebrow s-eyebrow--gold">Mobile app</span>
                    <h2 class="s-title">Take {{ $name }} everywhere</h2>
                    <p class="s-lead mb-4">The same account and wallet on Android and iPhone — with Face ID / fingerprint, push notifications and one-tap repeat purchases.</p>
                    <div class="d-flex flex-wrap align-items-center gap-4">
                        @include('site.partials.app-badges')
                        <div class="s-qr d-none d-lg-inline-flex">
                            {!! \App\Support\Qr::svg(route('site.app'), 112) !!}
                            <span><b>Scan to download</b><small>Opens the right store<br>for your phone</small></span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5 text-center">
                    <div class="d-inline-grid gap-3" style="grid-template-columns: repeat(2, 1fr)">
                        @foreach([['ri-fingerprint-line', 'Biometric login'], ['ri-notification-badge-line', 'Live alerts'], ['ri-repeat-line', 'Repeat in one tap'], ['ri-moon-clear-line', 'Works on any phone']] as [$ic, $tx])
                            <div class="s-info flex-column align-items-start" style="min-width: 150px"><span class="s-ico"><i class="{{ $ic }}"></i></span><b class="mt-2">{{ $tx }}</b></div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ================= FAQ ================= --}}
<section class="s-section s-section--soft">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4" data-reveal>
                <span class="s-eyebrow">FAQ</span>
                <h2 class="s-title">Questions, answered</h2>
                <p class="s-lead mb-4">Can't find what you need? Our team is a message away.</p>
                <a href="{{ route('site.contact') }}" class="s-btn s-btn--ghost">Contact support <i class="ri-arrow-right-line"></i></a>
            </div>
            <div class="col-lg-8" data-reveal="right">
                <div class="s-faq">
                    @foreach($faqs as $j => [$q, $a])
                        <details @if($j === 0) open @endif><summary>{{ $q }}<i class="ri-add-line"></i></summary><div class="s-faq-a">{{ $a }}</div></details>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ================= CTA ================= --}}
<section class="s-section">
    <div class="container">
        <div class="s-cta" data-reveal="zoom">
            <h2>Your next top-up is 30 seconds away</h2>
            <p>Join {{ $name }} free. No monthly fees — you only pay for what you buy.</p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                @auth
                    <a href="{{ route('topup.buy') }}" class="s-btn s-btn--gold s-btn--lg">Make a payment <i class="ri-arrow-right-line"></i></a>
                @else
                    <a href="{{ $register }}" class="s-btn s-btn--gold s-btn--lg">Create free account <i class="ri-arrow-right-line"></i></a>
                    <a href="{{ route('login') }}" class="s-btn s-btn--light s-btn--lg">I have an account</a>
                @endauth
            </div>
        </div>
    </div>
</section>
@endsection
