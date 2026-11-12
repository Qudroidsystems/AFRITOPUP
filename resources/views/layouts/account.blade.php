{{--
    Customer area (customers, agents, resellers): the website's look with a
    top navigation bar on desktop and a bottom tab bar on phones. Staff use
    layouts.master. Pages choose with @extends($shell ?? 'layouts.master');
    $shell is set for every view in AppServiceProvider.
--}}
@php
    $me = auth()->user();
    $siteName = $brand['name'] ?? 'AfriTopup';
    $primary = ($brand['primary_color'] ?? null) ?: '#0B8457';
    $accent = ($brand['accent_color'] ?? null) ?: '#F5A623';
    $rgb = fn ($hex) => implode(', ', array_map('hexdec', str_split(substr($hex, 1), 2)));
    $wallet = $me->wallet();
    $unread = \Illuminate\Support\Facades\Schema::hasTable('notifications') ? $me->unreadNotifications()->count() : 0;
    $nav = array_values(array_filter([
        ['dashboard', 'Home', 'ri-home-5-line', 'ri-home-5-fill', ['dashboard']],
        ['topup.buy', 'Pay', 'ri-flashlight-line', 'ri-flashlight-fill', ['topup.buy*']],
        ['topup.wallet', 'Wallet', 'ri-wallet-3-line', 'ri-wallet-3-fill', ['topup.wallet*']],
        ['topup.transactions', 'History', 'ri-file-list-3-line', 'ri-file-list-3-fill', ['topup.transactions*']],
        $me->can('Use reseller API') ? ['topup.developer', 'API', 'ri-code-s-slash-line', 'ri-code-s-slash-fill', ['topup.developer*', 'topup.sender-ids*']] : null,
        ['support.index', 'Help', 'ri-customer-service-2-line', 'ri-customer-service-2-fill', ['support.*']],
    ]));
    $supportUnread = (int) (\App\Models\SupportConversation::where('user_id', $me->id)->value('customer_unread') ?? 0);
    $theme = file_exists(public_path('theme/layouts/assets/css/app.min.css'));
    $initials = collect(explode(' ', trim($me->name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->join('');
@endphp
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $pagetitle ?? 'My account' }} | {{ $siteName }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ffffff">
    <meta name="robots" content="noindex">
    <link rel="icon" href="{{ asset($brand['favicon'] ?? 'images/brand/afritopup-mark.svg') }}">
    <link rel="preload" href="{{ asset('site/vendor/fonts/plus-jakarta-sans-latin-700-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    @if($theme)
        {{-- The portal's Bootstrap build keeps every existing page's components identical. --}}
        <link href="{{ asset('theme/layouts/assets/css/bootstrap.min.css') }}" rel="stylesheet">
        <link href="{{ asset('theme/layouts/assets/css/app.min.css') }}" rel="stylesheet">
    @else
        <link href="{{ asset('site/vendor/bootstrap.min.css') }}" rel="stylesheet">
    @endif
    <link href="{{ asset('site/vendor/remixicon/remixicon.css') }}" rel="stylesheet">
    <link href="{{ asset('css/brand-logo.css') }}?v={{ @filemtime(public_path('css/brand-logo.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/cb-ui.css') }}?v={{ @filemtime(public_path('css/cb-ui.css')) }}" rel="stylesheet">
    <link href="{{ asset('site/site.css') }}?v={{ @filemtime(public_path('site/site.css')) }}" rel="stylesheet">
    <link href="{{ asset('site/account.css') }}?v={{ @filemtime(public_path('site/account.css')) }}" rel="stylesheet">
    <link href="{{ asset('site/chat.css') }}?v={{ @filemtime(public_path('site/chat.css')) }}" rel="stylesheet">
    <style>:root { --s-green: {{ $primary }}; --s-green-rgb: {{ $rgb($primary) }}; --s-gold: {{ $accent }}; --s-gold-rgb: {{ $rgb($accent) }}; --qd-tint: {{ $primary }}; --qd-tint-rgb: {{ $rgb($primary) }}; }</style>
    @stack('styles')
</head>
<body class="acct cb-ui">
<a href="#acctMain" class="s-skip">Skip to content</a>

<header class="a-top" id="acctTop">
    <div class="a-wrap a-top-row">
        <a href="{{ route('dashboard') }}" class="a-logo" aria-label="{{ $siteName }} — my account"><x-brand-logo size="20px" /></a>

        <nav class="a-nav" aria-label="Account">
            @foreach($nav as [$route, $label, $icon, $iconOn, $match])
                @php $on = request()->routeIs(...$match); @endphp
                <a href="{{ route($route) }}" class="{{ $on ? 'is-on' : '' }}" @if($on) aria-current="page" @endif><i class="{{ $on ? $iconOn : $icon }}"></i>{{ $label }}@if($route === 'support.index')<span class="a-navdot" data-support-badge @if(!$supportUnread) hidden @endif>{{ $supportUnread }}</span>@endif</a>
            @endforeach
        </nav>

        <div class="a-tools">
            <a href="{{ route('topup.wallet') }}" class="a-balance" title="Wallet balance">
                <i class="ri-wallet-3-fill"></i><span data-balance>{{ $wallet->formatted_balance }}</span>
            </a>
            <a href="{{ route('notifications.index') }}" class="a-icon-btn" aria-label="Notifications{{ $unread ? ' (' . $unread . ' unread)' : '' }}">
                <i class="ri-notification-3-line"></i>@if($unread)<span class="a-dot">{{ $unread > 9 ? '9+' : $unread }}</span>@endif
            </a>
            <div class="dropdown">
                <button class="a-avatar" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
                    @if($me->avatar)<img src="{{ $me->avatar_url }}" alt="">@else<span>{{ $initials }}</span>@endif
                </button>
                <div class="dropdown-menu dropdown-menu-end a-menu">
                    <div class="a-menu-head">
                        <b>{{ $me->name }}</b>
                        <small>{{ $me->email }}</small>
                        <span class="a-chip">{{ $me->user_type_label }}</span>
                    </div>
                    <a class="dropdown-item" href="{{ route('account.profile') }}"><i class="ri-user-3-line"></i> Profile &amp; security</a>
                    <a class="dropdown-item" href="{{ route('account.statement') }}"><i class="ri-file-list-3-line"></i> Statement</a>
                    <a class="dropdown-item" href="{{ route('topup.wallet') }}#pin"><i class="ri-lock-password-line"></i> Transaction PIN</a>
                    <a class="dropdown-item" href="{{ route('notifications.index') }}"><i class="ri-notification-3-line"></i> Notifications</a>
                    <a class="dropdown-item" href="{{ route('home') }}"><i class="ri-global-line"></i> Visit website</a>
                    <a class="dropdown-item" href="{{ route('support.index') }}"><i class="ri-chat-smile-3-line"></i> Chat with support</a>
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item text-danger" type="submit"><i class="ri-logout-box-r-line"></i> Log out</button></form>
                </div>
            </div>
        </div>
    </div>
</header>

<main id="acctMain" class="a-main">
    @yield('content')
</main>

<footer class="a-foot">
    <div class="a-wrap">
        <span>&copy; {{ now()->year }} {{ $brand['company'] ?? $siteName }}</span>
        <span class="a-foot-links"><a href="{{ route('site.page', 'faq') }}">Help</a><a href="{{ route('site.page', 'terms') }}">Terms</a><a href="{{ route('site.page', 'privacy') }}">Privacy</a></span>
        @if(!empty($brand['developer']))<span>Developed by <a href="{{ $brand['developer_url'] ?? '#' }}" target="_blank" rel="noopener"><b>{{ $brand['developer'] }}</b></a></span>@endif
    </div>
</footer>

{{-- phone tab bar --}}
<nav class="a-tabbar" aria-label="Account">
    @foreach(array_slice($nav, 0, 4) as [$route, $label, $icon, $iconOn, $match])
        @php $on = request()->routeIs(...$match); @endphp
        <a href="{{ route($route) }}" class="{{ $on ? 'is-on' : '' }} {{ $route === 'topup.buy' ? 'a-tab-pay' : '' }}" @if($on) aria-current="page" @endif>
            <i class="{{ $on ? $iconOn : $icon }}"></i><span>{{ $label }}</span>
        </a>
    @endforeach
    <a href="{{ route('account.profile') }}" class="{{ request()->routeIs('account.profile*') ? 'is-on' : '' }}"><i class="{{ request()->routeIs('account.profile*') ? 'ri-user-3-fill' : 'ri-user-3-line' }}"></i><span>Me</span></a>
</nav>

@unless(request()->routeIs('support.*'))
    <a href="{{ route('support.index') }}" class="ch-fab" aria-label="Chat with support"><i class="ri-chat-smile-3-fill"></i><span class="a-dot" data-support-badge @if(!$supportUnread) hidden @endif>{{ $supportUnread }}</span></a>
@endunless
<div class="s-toast" id="siteToast" role="status" aria-live="polite"></div>
<script src="{{ $theme ? asset('theme/layouts/assets/libs/bootstrap/js/bootstrap.bundle.min.js') : asset('site/vendor/bootstrap.bundle.min.js') }}"></script>
<script>
    window.siteToast = function (msg) { var t = document.getElementById('siteToast'); if (!t) return; t.textContent = msg; t.classList.add('is-on'); clearTimeout(t._h); t._h = setTimeout(function () { t.classList.remove('is-on'); }, 2600); };
    // Support replies: keep the badge fresh on every page.
    (function () {
        if (location.pathname.indexOf('/support') === 0) return;
        function check() {
            if (document.visibilityState !== 'visible') return;
            fetch(@json(route('support.summary')), { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
                document.querySelectorAll('[data-support-badge]').forEach(function (b) {
                    var was = parseInt(b.textContent || '0', 10);
                    b.textContent = j.unread; b.hidden = !j.unread;
                    if (j.unread > was && b.closest('.ch-fab')) window.siteToast && siteToast('New reply from support');
                });
            }).catch(function () {});
        }
        setInterval(check, 30000);
        document.addEventListener('visibilitychange', check);
    })();
    (function () { var top = document.getElementById('acctTop'); function s() { top.classList.toggle('is-scrolled', window.scrollY > 4); } window.addEventListener('scroll', s, { passive: true }); s(); })();
</script>
@stack('scripts')
</body>
</html>
