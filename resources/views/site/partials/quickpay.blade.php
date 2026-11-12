{{-- The quick-pay box. @include('site.partials.quickpay', ['catalogue' => $catalogue, 'only' => 'data']) --}}
<div class="qp" data-qp @if(!empty($only)) data-only="{{ $only }}" @endif>
    {{-- Shown until the script builds the widget (and if scripts are off). --}}
    <div class="qp-body text-center py-5">
        <div class="qp-skel mb-2"></div><div class="qp-skel mb-2"></div><div class="qp-skel"></div>
        <noscript><a class="s-btn mt-3" href="{{ auth()->check() ? route('topup.buy') : (Route::has('register') ? route('register') : route('login')) }}">Start paying</a></noscript>
    </div>
</div>
@once
    @push('scripts')
        @php
            $qpData = [
                'catalogue'   => $catalogue,
                'plansUrl'    => route('site.plans', ['service' => '__ID__']),
                'action'      => route('site.quickpay'),
                'csrf'        => csrf_token(),
                'signedIn'    => auth()->check(),
                'brand'       => $brand['name'] ?? 'AfriTopup',
                'registerUrl' => Route::has('register') ? route('register') : route('login'),
            ];
        @endphp
        <script type="application/json" id="qpData">{!! json_encode($qpData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush
@endonce
