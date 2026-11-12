@php
    $siteName = $brand['name'] ?? 'AfriTopup';
    $hexRgb = fn ($hex, $fallback) => preg_match('/^#[0-9a-f]{6}$/i', (string) $hex) ? implode(', ', array_map('hexdec', str_split(substr($hex, 1), 2))) : $fallback;
    $primary = ($brand['primary_color'] ?? null) ?: '#0B8457';
    $accent = ($brand['accent_color'] ?? null) ?: '#F5A623';
    $cats = config('afritopup.categories');
    $blurbs = \App\Http\Controllers\Site\SiteController::BLURBS;
    $me = auth()->user();
    $headerMode = trim($__env->yieldContent('header', 'dark'));   // dark (over a dark hero) | light
    $metaTitle = trim($__env->yieldContent('title')) ?: ($siteName . ' — ' . ($brand['tagline'] ?? 'Recharge & pay bills'));
    $metaDesc = trim($__env->yieldContent('description')) ?: 'Buy airtime and data, pay for DStv, GOtv and electricity, buy exam PINs and send bulk SMS — instantly, from one wallet. ' . $siteName . ' is ' . lcfirst(rtrim($brand['tagline'] ?? '', '.')) . '.';
    $social = array_filter(config('afritopup.social_links', []));
    $apps = config('afritopup.app_links', []);
@endphp
<!doctype html>
<html lang="en" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDesc }}">
    <meta name="theme-color" content="#04281D">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDesc }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="{{ asset($brand['favicon'] ?? 'images/brand/afritopup-mark.svg') }}">

    <link rel="preload" href="{{ asset('site/vendor/fonts/plus-jakarta-sans-latin-800-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('site/vendor/fonts/plus-jakarta-sans-latin-500-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link href="{{ asset('site/vendor/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('site/vendor/remixicon/remixicon.css') }}" rel="stylesheet">
    <link rel="preload" href="{{ asset('site/vendor/remixicon/remixicon.woff2') }}?t=1716990450" as="font" type="font/woff2" crossorigin>
    <link href="{{ asset('css/brand-logo.css') }}?v={{ @filemtime(public_path('css/brand-logo.css')) }}" rel="stylesheet">
    <link href="{{ asset('site/site.css') }}?v={{ @filemtime(public_path('site/site.css')) }}" rel="stylesheet">
    <style>:root { --s-green: {{ $primary }}; --s-green-rgb: {{ $hexRgb($primary, '11, 132, 87') }}; --s-gold: {{ $accent }}; --s-gold-rgb: {{ $hexRgb($accent, '245, 166, 35') }}; }</style>
    <script>document.documentElement.classList.remove('no-js');</script>
    @stack('head')
</head>
<body class="site">
<a href="#main" class="s-skip">Skip to content</a>

{{-- ============ HEADER ============ --}}
<header class="s-header {{ $headerMode === 'light' ? 'is-light' : 'is-dark' }}" id="siteHeader">
    <div class="container">
        <nav class="s-nav" aria-label="Main">
            <a href="{{ route('home') }}" aria-label="{{ $siteName }} home"><x-brand-logo /></a>

            <ul class="s-menu">
                <li>
                    <button type="button" aria-haspopup="true">Services <i class="ri-arrow-down-s-line"></i></button>
                    <div class="s-mega" role="menu">
                        @foreach($cats as $k => $c)
                            <a href="{{ route('site.service', $k) }}" role="menuitem">
                                <span class="s-ico s-ico--sm" style="--c: {{ $c['color'] }}"><i class="{{ $c['icon'] }}"></i></span>
                                <span><strong>{{ $c['name'] }}</strong><small>{{ $blurbs[$k][0] ?? '' }}</small></span>
                            </a>
                        @endforeach
                        <div class="s-mega-foot">
                            <span><b>New:</b> fund your wallet by bank transfer — it credits automatically, 24/7.</span>
                            <a href="{{ route('site.pricing') }}" class="s-link">See prices <i class="ri-arrow-right-line"></i></a>
                        </div>
                    </div>
                </li>
                <li><a href="{{ route('site.pricing') }}" class="{{ request()->routeIs('site.pricing') ? 'active' : '' }}">Pricing</a></li>
                <li><a href="{{ route('site.page', 'business') }}" class="{{ request()->is('business') ? 'active' : '' }}">Business</a></li>
                <li><a href="{{ route('site.page', 'developers') }}" class="{{ request()->is('developers') ? 'active' : '' }}">Developers</a></li>
                <li><a href="{{ route('site.page', 'faq') }}" class="{{ request()->is('faq') ? 'active' : '' }}">Help</a></li>
                <li><a href="{{ route('home') }}#app"><i class="ri-smartphone-line"></i> Get the app</a></li>
            </ul>

            <div class="s-nav-cta">
                @auth
                    @if($me->isStaff())
                        <a href="{{ route('dashboard') }}" class="s-btn s-btn--gold s-btn--sm">Admin <i class="ri-arrow-right-up-line"></i></a>
                    @else
                        <a href="{{ route('topup.wallet') }}" class="s-balance-pill s-hide-md"><i class="ri-wallet-3-line"></i>{{ $me->wallet()->formatted_balance }}</a>
                        <a href="{{ route('dashboard') }}" class="s-btn s-btn--gold s-btn--sm">My account <i class="ri-arrow-right-line"></i></a>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="s-btn s-btn--ghost s-btn--sm s-hide-md">Log in</a>
                    @if(config('afritopup.allow_registration'))
                        <a href="{{ route('register') }}" class="s-btn s-btn--gold s-btn--sm">Get started</a>
                    @endif
                @endauth
            </div>
            <button class="s-burger" type="button" data-bs-toggle="offcanvas" data-bs-target="#siteDrawer" aria-controls="siteDrawer" aria-label="Open menu"><i class="ri-menu-4-line"></i></button>
        </nav>
    </div>
</header>

{{-- mobile drawer --}}
<div class="offcanvas offcanvas-end s-drawer" tabindex="-1" id="siteDrawer" aria-label="Menu">
    <div class="offcanvas-header">
        <x-brand-logo size="20px" />
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <div class="s-drawer-label">Pay for</div>
        @foreach($cats as $k => $c)
            <a class="s-drawer-link" href="{{ route('site.service', $k) }}"><span class="s-ico s-ico--sm" style="--c: {{ $c['color'] }}"><i class="{{ $c['icon'] }}"></i></span>{{ $c['name'] }}</a>
        @endforeach
        <div class="s-drawer-label">{{ $siteName }}</div>
        <a class="s-drawer-link" href="{{ route('site.pricing') }}"><i class="ri-price-tag-3-line"></i> Pricing</a>
        <a class="s-drawer-link" href="{{ route('site.page', 'business') }}"><i class="ri-store-2-line"></i> Agents &amp; resellers</a>
        <a class="s-drawer-link" href="{{ route('site.page', 'developers') }}"><i class="ri-code-s-slash-line"></i> Developers</a>
        <a class="s-drawer-link" href="{{ route('site.page', 'faq') }}"><i class="ri-question-line"></i> Help centre</a>
        <a class="s-drawer-link" href="{{ route('site.contact') }}"><i class="ri-customer-service-2-line"></i> Contact us</a>
        <div class="s-drawer-label">Get the app</div>
        <div class="px-2">@include('site.partials.app-badges', ['size' => 'sm'])</div>
        <div class="mt-auto pt-4 d-grid gap-2">
            @auth
                <a href="{{ route('dashboard') }}" class="s-btn s-btn--block">Go to my account <i class="ri-arrow-right-line"></i></a>
            @else
                @if(config('afritopup.allow_registration'))<a href="{{ route('register') }}" class="s-btn s-btn--block">Create free account</a>@endif
                <a href="{{ route('login') }}" class="s-btn s-btn--ghost s-btn--block">Log in</a>
            @endauth
        </div>
    </div>
</div>

<main id="main">
    @yield('content')
</main>

{{-- ============ FOOTER ============ --}}
<footer class="s-footer">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4">
                <x-brand-logo dark size="22px" />
                <p class="mt-3 mb-0" style="max-width: 340px">{{ $brand['tagline'] ?? '' }} Airtime, data, TV, electricity, exam PINs and SMS — paid in seconds from one wallet.</p>
                @if($social)
                    <div class="s-social">
                        @foreach(['facebook' => 'ri-facebook-fill', 'x' => 'ri-twitter-x-line', 'instagram' => 'ri-instagram-line', 'linkedin' => 'ri-linkedin-fill', 'youtube' => 'ri-youtube-fill', 'tiktok' => 'ri-tiktok-fill', 'whatsapp' => 'ri-whatsapp-line'] as $net => $icon)
                            @if(!empty($social[$net]))<a href="{{ $social[$net] }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($net) }}"><i class="{{ $icon }}"></i></a>@endif
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <h6>Pay for</h6>
                <ul>@foreach($cats as $k => $c)<li><a href="{{ route('site.service', $k) }}">{{ $c['name'] }}</a></li>@endforeach</ul>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <h6>Company</h6>
                <ul>
                    <li><a href="{{ route('site.page', 'about') }}">About us</a></li>
                    <li><a href="{{ route('site.page', 'business') }}">Agents &amp; resellers</a></li>
                    <li><a href="{{ route('site.page', 'developers') }}">Developers &amp; API</a></li>
                    <li><a href="{{ route('site.pricing') }}">Pricing</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <h6>Help</h6>
                <ul>
                    <li><a href="{{ route('site.page', 'faq') }}">Help centre</a></li>
                    <li><a href="{{ route('site.contact') }}">Contact us</a></li>
                    <li><a href="{{ route('site.page', 'terms') }}">Terms of service</a></li>
                    <li><a href="{{ route('site.page', 'privacy') }}">Privacy policy</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <h6>Get the app</h6>
                @include('site.partials.app-badges', ['size' => 'sm', 'light' => true, 'stack' => true])
            </div>
        </div>
        <div class="s-footer-bottom">
            <span>&copy; {{ now()->year }} {{ $brand['company'] ?? $siteName }}. All rights reserved.</span>
            <div class="s-pay-logos" aria-label="Payment methods"><span>Visa</span><span>Mastercard</span><span>Verve</span><span>Bank transfer</span><span>Secured by Paystack</span></div>
            @if(!empty($brand['developer']))
                <span>Developed by <a href="{{ $brand['developer_url'] ?? '#' }}" target="_blank" rel="noopener"><b>{{ $brand['developer'] }}</b></a></span>
            @endif
        </div>
    </div>
</footer>

<button class="s-top" id="toTop" type="button" aria-label="Back to top"><i class="ri-arrow-up-line"></i></button>
@if(array_filter(config('afritopup.app_links', [])))
    <div class="s-appbar" id="appBar" role="complementary" aria-label="Get the app">
        <button type="button" class="s-appbar-x" data-appbar-close aria-label="Close"><i class="ri-close-line"></i></button>
        <x-brand-logo mark size="30px" />
        <span><b>{{ $siteName }} app</b><small>Faster payments, fingerprint login</small></span>
        <a href="{{ route('site.app') }}" class="s-btn s-btn--gold s-btn--sm">Get the app</a>
    </div>
@endif
<div class="s-toast" id="siteToast" role="status" aria-live="polite"></div>

<script src="{{ asset('site/vendor/bootstrap.bundle.min.js') }}" defer></script>
<script src="{{ asset('site/site.js') }}?v={{ @filemtime(public_path('site/site.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
