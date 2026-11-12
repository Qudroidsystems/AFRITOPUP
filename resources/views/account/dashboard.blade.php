@extends('layouts.account')

@php
    use App\Support\Money;
    use App\Support\Color;
    $me = auth()->user();
    $first = \Illuminate\Support\Str::before(trim($me->name), ' ') ?: $me->name;
    $hour = (int) now()->format('G');
    $greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $pill = fn ($status) => match ($status) { 'successful' => 'ok', 'processing', 'pending' => 'wait', 'failed' => 'bad', default => 'muted' };
    $tile = function ($s, $size = 40) { return view('topup.partials.service-icon', ['s' => $s, 'size' => $size])->render(); };
@endphp

@section('content')
<div class="a-page">

    <div class="a-hello">
        <div>
            <h1>{{ $greet }}, {{ $first }} 👋</h1>
            <p>{{ $country?->flag }} Buying in {{ $country?->name ?? 'Nigeria' }} · {{ $me->user_type_label }} account</p>
        </div>
        <a href="{{ route('topup.buy') }}" class="s-btn d-none d-md-inline-flex"><i class="ri-flashlight-fill"></i> Make a payment</a>
    </div>

    @include('partials.flash')

    @if(!$me->hasVerifiedEmail())
        <div class="a-banner a-banner--info">
            <i class="ri-mail-check-line fs-4"></i>
            <div>Confirm your e-mail address to keep your account secure and receive receipts.</div>
            @if(Route::has('verification.notice'))<a href="{{ route('verification.notice') }}" class="s-btn s-btn--sm">Confirm now</a>@endif
        </div>
    @endif
    @unless($hasPin)
        <div class="a-banner a-banner--warn">
            <i class="ri-lock-password-line fs-4"></i>
            <div>Set your 4-digit transaction PIN — you need it to pay.</div>
            <a href="{{ route('topup.wallet') }}#pin" class="s-btn s-btn--sm s-btn--gold">Set PIN</a>
        </div>
    @endunless

    <div class="row g-4">
        {{-- LEFT --}}
        <div class="col-xl-8">
            <div class="a-wallet mb-4" id="walletCard" data-hide-balance="0">
                <div class="d-flex justify-content-between align-items-start gap-3 position-relative" style="z-index:1">
                    <div>
                        <small>Wallet balance · {{ $wallet->currency }}</small>
                        <div class="a-wallet-bal"><span data-amount-mask>{{ $wallet->formatted_balance }}</span>
                            <button type="button" id="toggleBal" aria-label="Hide balance"><i class="ri-eye-line"></i></button></div>
                    </div>
                    <x-brand-logo mark dark size="34px" />
                </div>
                <div class="a-wallet-acts">
                    <a href="{{ route('topup.wallet') }}#fund" class="s-btn s-btn--gold"><i class="ri-add-line"></i> Fund wallet</a>
                    <a href="{{ route('topup.buy') }}" class="s-btn s-btn--light"><i class="ri-flashlight-line"></i> Pay</a>
                    <a href="{{ route('topup.transactions') }}" class="s-btn s-btn--light"><i class="ri-history-line"></i> History</a>
                </div>
                <div class="a-wallet-meta">
                    <span>Spent this month <b data-amount-mask>{{ Money::format($spentMonth, $wallet->currency) }}</b></span>
                    <span>Payments <b>{{ number_format($countMonth) }}</b></span>
                    @if((float) $earnings->balance > 0)<span>Earnings <b data-amount-mask>{{ $earnings->formatted_balance }}</b></span>@endif
                    @if($account)
                        <span class="ms-md-auto">Transfer to <b>{{ $account->account_number }}</b> · {{ $account->bank_name }}
                            <button type="button" class="btn btn-link p-0 ms-1 text-white" data-copy-text="{{ $account->account_number }}" aria-label="Copy account number"><i class="ri-file-copy-line"></i></button></span>
                    @endif
                </div>
            </div>

            <div class="a-card mb-4">
                <div class="a-card-head"><h2>Pay for</h2><a href="{{ route('topup.buy') }}" class="s-link small">All services <i class="ri-arrow-right-line"></i></a></div>
                <div class="a-quick">
                    @foreach($categories as $k => $c)
                        <a href="{{ route('topup.buy.form', $k) }}" style="--c: {{ $c['color'] }}">
                            <span class="s-ico s-ico--lg" style="--c: {{ $c['color'] }}"><i class="{{ $c['icon'] }}"></i></span>{{ $c['name'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="a-card">
                <div class="a-card-head"><h2>Recent payments</h2><a href="{{ route('topup.transactions') }}" class="s-link small">See all <i class="ri-arrow-right-line"></i></a></div>
                @forelse($recent as $t)
                    <a href="{{ route('topup.transactions.show', $t->reference) }}" class="a-txn">
                        {!! $tile($t->service, 42) !!}
                        <div class="a-txn-main">
                            <b>{{ $t->service?->display_name ?? $t->category_label }} @if($t->product_id && $t->meta && isset($t->meta['product_name'])) · {{ $t->meta['product_name'] }} @endif</b>
                            <small>{{ $t->recipient }} · {{ $t->created_at->diffForHumans() }}</small>
                        </div>
                        <div class="a-txn-amt">
                            <b>{{ Money::format($t->total, $t->currency) }}</b>
                            <span class="a-pill a-pill--{{ $pill($t->status) }}">{{ $t->status_label }}</span>
                        </div>
                    </a>
                @empty
                    <div class="a-empty"><i class="ri-receipt-line"></i>No payments yet — your receipts will show up here.<div class="mt-3"><a href="{{ route('topup.buy') }}" class="s-btn s-btn--sm">Make your first payment</a></div></div>
                @endforelse
            </div>
        </div>

        {{-- RIGHT --}}
        <div class="col-xl-4">
            <div class="a-card mb-4">
                <div class="a-card-head"><h2>Pay again</h2></div>
                @forelse($saved as $b)
                    @php $svc = $b->service; @endphp
                    <a class="a-txn" href="{{ route('topup.buy.form', [$b->category, 'service' => $svc?->code, 'recipient' => $b->recipient]) }}">
                        {!! $tile($svc, 38) !!}
                        <div class="a-txn-main"><b>{{ $b->label ?: $b->recipient }}</b><small>{{ $svc?->display_name }} {{ strtolower(config('afritopup.categories.' . $b->category . '.name', '')) }}{{ $b->label ? ' · ' . $b->recipient : '' }}</small></div>
                        <i class="ri-arrow-right-s-line fs-5" style="color: var(--s-muted)"></i>
                    </a>
                @empty
                    <div class="a-empty py-3"><i class="ri-contacts-book-3-line"></i>Numbers, smartcards and meters you pay will appear here for one-tap repeat.</div>
                @endforelse
            </div>

            <div class="a-promo mb-4">
                <span class="s-eyebrow s-eyebrow--gold"><i class="ri-gift-line"></i> Invite &amp; earn</span>
                <h2 class="h5 mt-3 mb-1">Share {{ $brand['name'] ?? 'AfriTopup' }} with friends</h2>
                <p class="small mb-0" style="color: var(--s-muted)">Earn rewards when people you invite start paying. {{ $referrals ? 'You\'ve invited ' . $referrals . ' ' . \Illuminate\Support\Str::plural('person', $referrals) . ' so far.' : '' }}</p>
                <div class="a-code"><b>{{ $code }}</b><button type="button" class="s-btn s-btn--sm" data-copy-text="{{ Route::has('register') ? route('register', ['ref' => $code]) : $code }}"><i class="ri-share-forward-line"></i> Copy link</button></div>
            </div>

            <div class="a-card mb-4">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="s-ico s-ico--lg"><i class="ri-smartphone-line"></i></span>
                    <span><b style="color: var(--s-ink)">Get the {{ $brand['name'] ?? 'AfriTopup' }} app</b><small class="d-block" style="color: var(--s-muted)">Same account and wallet — with fingerprint login and alerts.</small></span>
                </div>
                @include('site.partials.app-badges', ['size' => 'sm'])
            </div>

            @if($me->isCustomer())
                <a href="{{ route('site.page', 'business') }}" class="a-card d-flex align-items-center gap-3 text-decoration-none" style="color: inherit">
                    <span class="s-ico s-ico--lg" style="--c:#7C3AED"><i class="ri-store-2-line"></i></span>
                    <span><b style="color: var(--s-ink)">Sell &amp; earn</b><small class="d-block" style="color: var(--s-muted)">Become an agent and buy at a discount.</small></span>
                    <i class="ri-arrow-right-line ms-auto" style="color: var(--s-muted)"></i>
                </a>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var card = document.getElementById('walletCard'), btn = document.getElementById('toggleBal');
    var hidden = false;
    try { hidden = localStorage.getItem('at-hide-balance') === '1'; } catch (e) {}
    function paint() { card.dataset.hideBalance = hidden ? '1' : '0'; btn.innerHTML = hidden ? '<i class="ri-eye-off-line"></i>' : '<i class="ri-eye-line"></i>'; btn.setAttribute('aria-label', hidden ? 'Show balance' : 'Hide balance'); }
    btn.addEventListener('click', function () { hidden = !hidden; try { localStorage.setItem('at-hide-balance', hidden ? '1' : '0'); } catch (e) {} paint(); });
    paint();
    document.querySelectorAll('[data-copy-text]').forEach(function (b) {
        b.addEventListener('click', function (e) { e.preventDefault(); navigator.clipboard && navigator.clipboard.writeText(b.dataset.copyText).then(function () { window.siteToast('Copied'); }); });
    });
})();
</script>
@endpush
