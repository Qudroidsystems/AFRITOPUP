@extends('layouts.site')
@section('title', 'Developers — VTU & bill payment API | ' . ($brand['name'] ?? 'AfriTopup'))
@php
    $name = $brand['name'] ?? 'AfriTopup';
    $base = url('/api/reseller/v1');
    $register = Route::has('register') ? route('register') : route('login');
    $endpoints = [
        ['GET', '/balance', 'Your wallet balance'],
        ['GET', '/services', 'Every service you can buy, with codes'],
        ['GET', '/services/{code}/products', 'Plans and bouquets for a service, at your price'],
        ['POST', '/verify', 'Check a smartcard or meter and get the customer name'],
        ['POST', '/purchase', 'Buy airtime, data, TV, electricity or exam PINs'],
        ['GET', '/transactions/{reference}', 'Status of a purchase (or use webhooks)'],
    ];
@endphp

@section('content')
@include('site.partials.page-hero', ['crumb' => 'Developers', 
    'dark' => true,
    'eyebrow' => 'Developers',
    'title' => 'One API for airtime, data and bills',
    'lead' => 'Add recharges and bill payments to your website or app in an afternoon. JSON over HTTPS, API-key authentication, idempotent purchases and signed webhooks.',
])

<section class="s-section">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-5" data-reveal>
                <h2 class="s-title">Endpoints</h2>
                <p class="s-lead mb-4">Base URL: <code>{{ $base }}</code><br>Send your key in the <code>X-API-Key</code> header.</p>
                <div class="d-grid gap-2">
                    @foreach($endpoints as [$m, $path, $d])
                        <div class="s-info align-items-center">
                            <span class="badge rounded-pill {{ $m === 'GET' ? 'text-bg-success' : 'text-bg-warning' }}" style="min-width: 52px">{{ $m }}</span>
                            <span><b class="font-monospace small">{{ $path }}</b><small class="d-block">{{ $d }}</small></span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="col-lg-7" data-reveal="right">
                <div class="s-code mb-4">
                    <div class="s-code-bar"><i></i><i></i><i></i><span>Buy data</span><button type="button" data-copy="#c1">Copy</button></div>
<pre id="c1"><span class="k">curl</span> -X POST {{ $base }}/purchase \
  -H <span class="s">"X-API-Key: afk_live_••••••••"</span> \
  -H <span class="s">"Content-Type: application/json"</span> \
  -d <span class="s">'{"service":"mtn-data","product":"mtn-1gb-30d","recipient":"08031234567","client_reference":"order-1029"}'</span></pre>
                </div>
                <div class="s-code mb-4">
                    <div class="s-code-bar"><i></i><i></i><i></i><span>Response</span></div>
<pre>{
  <span class="n">"success"</span>: <span class="k">true</span>,
  <span class="n">"data"</span>: {
    <span class="n">"reference"</span>: <span class="s">"AT-8F2K1Q7"</span>,
    <span class="n">"status"</span>: <span class="s">"successful"</span>,
    <span class="n">"total"</span>: <span class="k">980.00</span>,
    <span class="n">"client_reference"</span>: <span class="s">"order-1029"</span>
  }
}</pre>
                </div>
                <div class="s-code">
                    <div class="s-code-bar"><i></i><i></i><i></i><span>Verify a webhook (PHP)</span><button type="button" data-copy="#c3">Copy</button></div>
<pre id="c3"><span class="n">$signature</span> = hash_hmac(<span class="s">'sha256'</span>, <span class="n">$rawBody</span>, <span class="n">$webhookSecret</span>);
<span class="k">if</span> (!hash_equals(<span class="n">$signature</span>, <span class="n">$_SERVER</span>[<span class="s">'HTTP_X_SIGNATURE'</span>] ?? <span class="s">''</span>)) {
    http_response_code(<span class="k">401</span>); <span class="k">exit</span>;
}</pre>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="s-section s-section--soft">
    <div class="container">
        <div class="row g-4">
            @foreach([['ri-repeat-2-line', 'Idempotent', 'Send a client_reference — retries never charge twice.'], ['ri-webhook-line', 'Webhooks', 'Signed callbacks the moment a purchase completes or is refunded.'], ['ri-test-tube-line', 'Sandbox', 'Build safely against test services before you go live.'], ['ri-shield-keyhole-line', 'Secure keys', 'Create, rename and revoke keys; restrict them to your server IPs.']] as $j => [$ic, $t, $d])
                <div class="col-md-6 col-lg-3" data-reveal style="--d: {{ $j * .06 }}s"><div class="s-card h-100 p-4"><span class="s-ico"><i class="{{ $ic }}"></i></span><h3 class="h5 mt-3">{{ $t }}</h3><p class="mb-0" style="color: var(--s-muted)">{{ $d }}</p></div></div>
            @endforeach
        </div>
        <div class="text-center mt-5" data-reveal>
            <a href="{{ auth()->check() ? route('topup.developer') : $register }}" class="s-btn s-btn--lg">{{ auth()->check() ? 'Get your API keys' : 'Create an account to get keys' }} <i class="ri-arrow-right-line"></i></a>
            <p class="small mt-3 mb-0" style="color: var(--s-muted)">Full reference with every field and error code is in your account under <b>Resellers &amp; API → Docs</b>.</p>
        </div>
    </div>
</section>
@endsection
