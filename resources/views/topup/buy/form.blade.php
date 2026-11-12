@extends($shell ?? 'layouts.master')

{{--
    One form for every category. The server decides what each service needs
    (plan, typed amount, verification); the script below shows the matching
    fields, loads plans priced for this user, checks smartcards / meters and
    keeps the summary up to date. TopupService validates everything again.
--}}
@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    @include('topup.partials.styles')
    <x-cb.hero :title="$cat['name']" :icon="$cat['icon']" subtitle="Paid from your wallet. You will get a receipt straight away." :back="route('topup.buy')" back-label="All services">
        <x-slot:pills>
            <span class="cb-meta-pill">{{ $country->flag }} {{ $country->name }}</span>
            <span class="cb-meta-pill"><i class="ri-wallet-3-line me-1"></i>Balance {{ $wallet->formatted_balance }}</span>
        </x-slot:pills>
    </x-cb.hero>
    @include('partials.flash')

    @unless($hasPin)
        <div class="cb-banner warning"><i class="ri-lock-password-line"></i><div>You need a 4-digit transaction PIN to buy. <a href="{{ route('topup.wallet') }}#pin">Set your PIN</a> — it takes a few seconds.</div></div>
    @endunless

    <form method="POST" action="{{ route('topup.buy.store') }}" id="buyForm" autocomplete="off" novalidate>
        @csrf
        <div class="row g-3">
            <div class="col-xl-8">
                <x-cb.card :title="$category === 'education' ? 'What are you paying for?' : 'Choose ' . ($category === 'electricity' ? 'your distribution company' : ($category === 'cable' ? 'the provider' : ($category === 'sms' ? 'the service' : 'the network')))" icon="ri-apps-line">
                    <div class="at-pick" role="radiogroup">
                        @foreach($services as $s)
                            <input type="radio" name="service_id" id="svc{{ $s->id }}" value="{{ $s->id }}" @checked((int) old('service_id', $selected->id) === $s->id)
                                   data-products-url="{{ route('topup.services.products', $s) }}" data-verify-url="{{ route('topup.services.verify', $s) }}" data-quote-url="{{ route('topup.services.quote', $s) }}">
                            <label for="svc{{ $s->id }}">@include('topup.partials.service-icon', ['s' => $s, 'size' => 30]) {{ $s->display_name }}</label>
                        @endforeach
                    </div>
                </x-cb.card>

                <x-cb.card title="Details" icon="ri-edit-2-line">
                    {{-- Electricity: meter type --}}
                    <div class="mb-3 d-none" data-show="electricity">
                        <label class="form-label fw-semibold">Meter type</label>
                        <div class="at-pick">
                            @foreach(['prepaid' => 'Prepaid', 'postpaid' => 'Postpaid'] as $v => $l)
                                <input type="radio" name="meter_type" id="mt{{ $v }}" value="{{ $v }}" @checked(old('meter_type', request('meter_type', 'prepaid')) === $v)><label for="mt{{ $v }}">{{ $l }}</label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Cable: renew the current bouquet or change it --}}
                    <div class="mb-3 d-none" data-show="cable">
                        <label class="form-label fw-semibold">Subscription</label>
                        <div class="at-pick">
                            @foreach(['change' => 'New / change bouquet', 'renew' => 'Renew current bouquet'] as $v => $l)
                                <input type="radio" name="subscription_type" id="st{{ $v }}" value="{{ $v }}" @checked(old('subscription_type', 'change') === $v)><label for="st{{ $v }}">{{ $l }}</label>
                            @endforeach
                        </div>
                    </div>

                    {{-- School fees --}}
                    <div class="d-none" data-show="school">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="school_id">School</label>
                                <select class="form-select" name="school_id" id="school_id">
                                    <option value="">Choose the school…</option>
                                    @foreach($schools as $sc)<option value="{{ $sc->id }}" @selected(old('school_id') == $sc->id)>{{ $sc->name }}{{ $sc->city ? ' — ' . $sc->city : '' }}</option>@endforeach
                                </select>
                                @if($schools->isEmpty())<div class="form-text">No partner schools yet in {{ $country->name }}.</div>@endif
                            </div>
                            <div class="col-md-6"><label class="form-label fw-semibold" for="student_name">Student's full name</label><input class="form-control" name="student_name" id="student_name" value="{{ old('student_name') }}" maxlength="150"></div>
                            <div class="col-md-4"><label class="form-label fw-semibold" for="student_ref">Admission / reg. number</label><input class="form-control" name="student_ref" id="student_ref" value="{{ old('student_ref') }}" maxlength="60"></div>
                            <div class="col-md-4"><label class="form-label fw-semibold" for="session">Session / term</label><input class="form-control" name="session" id="session" value="{{ old('session') }}" placeholder="2026/2027 First term" maxlength="60"></div>
                            <div class="col-md-4"><label class="form-label fw-semibold" for="fee_item">Paying for</label><input class="form-control" name="fee_item" id="fee_item" value="{{ old('fee_item', 'School fees') }}" maxlength="80"></div>
                        </div>
                    </div>

                    {{-- SMS --}}
                    <div class="d-none" data-show="sms">
                        <div class="row g-3 mb-3">
                            <div class="col-md-5">
                                <label class="form-label fw-semibold" for="sender">Sender name</label>
                                <select class="form-select" name="sender" id="sender">
                                    @foreach($senders as $snd)<option value="{{ $snd }}" @selected(old('sender') === $snd)>{{ $snd }}</option>@endforeach
                                </select>
                                <div class="form-text"><a href="{{ route('topup.sender-ids') }}">Request your own sender name</a></div>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label fw-semibold">Route</label>
                                <div class="at-pick">
                                    <input type="radio" name="route" id="rtg" value="generic" @checked(old('route', 'generic') === 'generic')><label for="rtg">Standard</label>
                                    <input type="radio" name="route" id="rtd" value="dnd" @checked(old('route') === 'dnd')><label for="rtd">DND (reaches do-not-disturb lines)</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="recipients">Recipients</label>
                                <textarea class="form-control at-mono" name="recipients" id="recipients" rows="4" placeholder="08031234567, 08051234567&#10;One per line or separated by commas">{{ old('recipients') }}</textarea>
                                <div class="form-text"><span id="rcpCount">0</span> numbers</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="message">Message</label>
                                <textarea class="form-control" name="message" id="message" rows="4" maxlength="918">{{ old('message') }}</textarea>
                                <div class="form-text"><span id="smsChars">0</span> characters · <span id="smsPages">1</span> page(s) per recipient</div>
                            </div>
                        </div>
                    </div>

                    {{-- Plans --}}
                    <div class="mb-3 d-none" id="plansBox">
                        <label class="form-label fw-semibold d-flex justify-content-between"><span>Choose a plan</span><input type="search" class="form-control form-control-sm w-auto" id="planSearch" placeholder="Search plans"></label>
                        <input type="hidden" name="product_id" id="product_id" value="{{ old('product_id', request('product')) }}">
                        <div class="at-plans" id="plans"><div class="text-muted small">Loading plans…</div></div>
                    </div>

                    {{-- JAMB profile ID --}}
                    <div class="mb-3 d-none" id="profileBox">
                        <label class="form-label fw-semibold" for="profile_id">JAMB profile ID</label>
                        <div class="input-group">
                            <input class="form-control" name="profile_id" id="profile_id" value="{{ old('profile_id') }}" inputmode="numeric" maxlength="20">
                            <button class="btn btn-soft-primary" type="button" data-verify="profile_id">Check</button>
                        </div>
                        <div class="form-text">Text <strong>NIN 12345678901</strong> (your NIN) to 55019 to get your profile ID.</div>
                    </div>

                    {{-- Recipient --}}
                    <div class="mb-3" id="recipientBox">
                        <label class="form-label fw-semibold" for="recipient" id="recipientLabel">{{ $cat['recipient_label'] }}</label>
                        <div class="input-group">
                            <input class="form-control form-control-lg" name="recipient" id="recipient" value="{{ old('recipient', request('recipient', $category === 'airtime' || $category === 'data' ? auth()->user()->phone_number : '')) }}" inputmode="numeric" maxlength="30">
                            <button class="btn btn-soft-primary d-none" type="button" id="verifyBtn" data-verify="recipient">Verify</button>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-2" id="savedRecipients"></div>
                        <div class="at-verify mt-2 d-none" id="verifyResult"></div>
                    </div>

                    {{-- Amount --}}
                    <div class="mb-3 d-none" id="amountBox">
                        <label class="form-label fw-semibold" for="amount">Amount</label>
                        <div class="input-group input-group-lg"><span class="input-group-text">{{ \App\Support\Money::SYMBOLS[$country->currency] ?? $country->currency }}</span>
                            <input class="form-control" type="number" name="amount" id="amount" value="{{ old('amount', request('amount')) }}" min="0" step="1" inputmode="numeric"></div>
                        <div class="d-flex gap-2 mt-2 flex-wrap" id="quickAmounts"></div>
                        <div class="form-text" id="amountHint"></div>
                    </div>

                    {{-- Exam PIN quantity --}}
                    <div class="mb-3 d-none" id="qtyBox">
                        <label class="form-label fw-semibold" for="quantity">How many PINs?</label>
                        <input class="form-control w-auto" type="number" name="quantity" id="quantity" value="{{ old('quantity', 1) }}" min="1" max="10">
                    </div>

                    {{-- Contact phone (for cable / electricity / education receipts) --}}
                    <div class="mb-1 d-none" id="phoneBox">
                        <label class="form-label fw-semibold" for="phone">Your phone number <span class="text-muted fw-normal">(for the receipt)</span></label>
                        <input class="form-control" name="phone" id="phone" value="{{ old('phone', auth()->user()->phone_number) }}" inputmode="tel" maxlength="20">
                    </div>
                </x-cb.card>
            </div>

            <div class="col-xl-4">
                <div class="position-sticky" style="top: 90px">
                    <x-cb.card title="Summary" icon="ri-receipt-line">
                        <div class="at-summary mb-3">
                            <div class="row-l"><span>Price</span><span id="sAmount">—</span></div>
                            <div class="row-l text-success d-none" id="sDiscRow"><span>Your discount</span><span id="sDiscount">—</span></div>
                            <div class="row-l d-none" id="sFeeRow"><span>Fee</span><span id="sFee">—</span></div>
                            <div class="row-l total"><span>You pay</span><span id="sTotal">—</span></div>
                        </div>
                        <div class="small text-muted mb-3">Balance after: <strong id="sAfter">{{ $wallet->formatted_balance }}</strong></div>
                        <label class="form-label fw-semibold" for="pin">Transaction PIN</label>
                        <input class="form-control form-control-lg text-center mb-3 @error('pin') is-invalid @enderror" type="password" name="pin" id="pin" inputmode="numeric" maxlength="4" pattern="\d{4}" placeholder="••••" autocomplete="one-time-code" style="letter-spacing:.6em">
                        <button class="btn btn-success btn-lg w-100" type="submit" id="payBtn" @disabled(!$hasPin)><i class="ri-flashlight-line me-1"></i>Pay now</button>
                        <div class="small text-muted mt-2 text-center">If it fails, the money comes straight back to your wallet.</div>
                    </x-cb.card>
                </div>
            </div>
        </div>
    </form>
</div></div></div>
@endsection

@push('scripts')
<script>
(function () {
    const CATEGORY = @json($category);
    const CUR = @json(\App\Support\Money::SYMBOLS[$country->currency] ?? ($country->currency . ' '));
    const BALANCE = {{ (float) $wallet->balance }};
    const QUICK = { airtime: [100, 200, 500, 1000, 2000, 5000], electricity: [2000, 5000, 10000, 20000] };
    const csrf = document.querySelector('input[name=_token]').value;
    const $ = (id) => document.getElementById(id);
    const fmt = (n) => CUR + Number(n || 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const show = (el, on) => el && el.classList.toggle('d-none', !on);

    let svc = null, products = [], oldProduct = @json(old('product_id', request('product'))), quoteTimer = null;

    function selected() { return document.querySelector('input[name=service_id]:checked'); }

    async function loadService() {
        const radio = selected(); if (!radio) return;
        const res = await fetch(radio.dataset.productsUrl, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        svc = data.service; products = data.products || [];
        const opts = svc.options || {};
        const isSchool = !!opts.school;

        $('recipientLabel').textContent = svc.recipient_label;
        show($('plansBox'), svc.needs_product);
        show($('amountBox'), svc.needs_amount);
        show($('verifyBtn'), svc.needs_verification);
        show($('profileBox'), !!opts.profile_required);
        show($('qtyBox'), CATEGORY === 'education' && svc.needs_product && !opts.profile_required);
        show($('phoneBox'), ['cable', 'electricity'].includes(CATEGORY));
        show($('recipientBox'), CATEGORY !== 'sms' && !isSchool);
        document.querySelectorAll('[data-show]').forEach(el => show(el, el.dataset.show === CATEGORY || (el.dataset.show === 'school' && isSchool)));
        if (CATEGORY === 'education' && !isSchool) $('recipient').setAttribute('placeholder', 'Phone number to receive the PIN');
        $('verifyResult').classList.add('d-none');

        const hint = [];
        if (svc.min_amount) hint.push('From ' + fmt(svc.min_amount));
        if (svc.max_amount) hint.push('up to ' + fmt(svc.max_amount));
        $('amountHint').textContent = hint.join(' ');
        $('quickAmounts').innerHTML = (QUICK[CATEGORY] || []).map(a => `<button type="button" class="btn btn-sm btn-light" data-amt="${a}">${fmt(a).replace('.00', '')}</button>`).join('');

        renderPlans();
        requote();
    }

    function renderPlans(filter = '') {
        if (!svc || !svc.needs_product) return;
        const box = $('plans'); const q = filter.toLowerCase();
        const list = products.filter(p => !q || p.name.toLowerCase().includes(q));
        if (!list.length) { box.innerHTML = '<div class="text-muted small">No plans available right now.</div>'; return; }
        box.innerHTML = list.map(p => `
            <div class="at-plan ${String(p.id) === String($('product_id').value) ? 'sel' : ''}" data-id="${p.id}" tabindex="0" role="button">
                <div class="pn">${escapeHtml(p.name)}</div>
                ${p.validity ? `<div class="pv">${escapeHtml(p.validity)}</div>` : ''}
                <div class="pp">${fmt(p.total)}${p.discount > 0 ? `<s>${fmt(p.price)}</s>` : ''}</div>
            </div>`).join('');
    }

    function escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

    function pickPlan(id) {
        $('product_id').value = id;
        document.querySelectorAll('.at-plan').forEach(el => el.classList.toggle('sel', el.dataset.id === String(id)));
        requote();
    }

    function smsCounts() {
        const nums = ($('recipients').value.match(/\d{10,15}/g) || []);
        const uniq = [...new Set(nums)];
        const len = $('message').value.length;
        const pages = len <= 160 ? 1 : Math.ceil(len / 153);
        $('rcpCount').textContent = uniq.length; $('smsChars').textContent = len; $('smsPages').textContent = pages;
        return { count: uniq.length, pages };
    }

    function requote() {
        clearTimeout(quoteTimer);
        quoteTimer = setTimeout(async () => {
            if (!svc) return;
            const p = new URLSearchParams();
            if (svc.needs_product) { if (!$('product_id').value) return paint(null); p.set('product_id', $('product_id').value); p.set('quantity', $('quantity').value || 1); }
            else if (CATEGORY === 'sms') {
                const c = smsCounts(); if (!c.count) return paint(null);
                p.set('amount', (parseFloat((svc.options || {}).price_per_page || 4) * c.pages).toFixed(2)); p.set('quantity', c.count);
            } else { const a = parseFloat($('amount').value); if (!a) return paint(null); p.set('amount', a); }
            const res = await fetch(selected().dataset.quoteUrl + '?' + p, { headers: { Accept: 'application/json' } });
            paint(await res.json());
        }, 250);
    }

    function paint(q) {
        $('sAmount').textContent = q ? fmt(q.amount) : '—';
        $('sTotal').textContent = q ? fmt(q.total) : '—';
        $('sDiscount').textContent = q ? '−' + fmt(q.discount) : '';
        $('sFee').textContent = q ? fmt(q.fee) : '';
        show($('sDiscRow'), q && parseFloat(q.discount) > 0);
        show($('sFeeRow'), q && parseFloat(q.fee) > 0);
        const after = BALANCE - (q ? parseFloat(q.total) : 0);
        $('sAfter').textContent = fmt(after);
        $('sAfter').classList.toggle('text-danger', after < 0);
    }

    async function verify(field) {
        const input = $(field), out = $('verifyResult');
        if (!input.value.trim()) { input.focus(); return; }
        out.className = 'at-verify mt-2 bg-light'; out.textContent = 'Checking…';
        const body = new FormData();
        body.append('customer_id', input.value.trim());
        if ($('product_id').value) body.append('product_id', $('product_id').value);
        const mt = document.querySelector('input[name=meter_type]:checked'); if (mt) body.append('meter_type', mt.value);
        try {
            const res = await fetch(selected().dataset.verifyUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, body });
            const r = await res.json();
            if (r.valid) {
                const d = r.details || {};
                out.className = 'at-verify mt-2 bg-success-subtle text-success';
                out.innerHTML = '<i class="ri-checkbox-circle-fill me-1"></i><strong>' + escapeHtml(r.name || 'Verified') + '</strong>'
                    + (d.address ? '<div class="small">' + escapeHtml(d.address) + '</div>' : '')
                    + (d.current_bouquet ? '<div class="small">Current bouquet: ' + escapeHtml(d.current_bouquet) + '</div>' : '');
            } else {
                out.className = 'at-verify mt-2 bg-danger-subtle text-danger';
                out.innerHTML = '<i class="ri-error-warning-fill me-1"></i>' + escapeHtml(r.message || 'Not found.');
            }
        } catch (e) {
            out.className = 'at-verify mt-2 bg-danger-subtle text-danger'; out.textContent = 'Could not check right now. Please try again.';
        }
    }

    document.querySelectorAll('input[name=service_id]').forEach(r => r.addEventListener('change', () => { $('product_id').value = ''; loadService(); }));
    $('plans').addEventListener('click', e => { const el = e.target.closest('.at-plan'); if (el) pickPlan(el.dataset.id); });
    $('plans').addEventListener('keydown', e => { const el = e.target.closest('.at-plan'); if (el && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); pickPlan(el.dataset.id); } });
    $('planSearch').addEventListener('input', e => renderPlans(e.target.value));
    $('quickAmounts').addEventListener('click', e => { const b = e.target.closest('[data-amt]'); if (b) { $('amount').value = b.dataset.amt; requote(); } });
    ['amount', 'quantity', 'recipients', 'message'].forEach(id => $(id) && $(id).addEventListener('input', requote));
    document.querySelectorAll('[data-verify]').forEach(b => b.addEventListener('click', () => verify(b.dataset.verify)));
    $('buyForm').addEventListener('submit', e => {
        if (!/^\d{4}$/.test($('pin').value)) { e.preventDefault(); $('pin').classList.add('is-invalid'); $('pin').focus(); return; }
        $('payBtn').disabled = true; $('payBtn').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing…';
    });

    // Saved recipients (remembered after each successful purchase): one tap fills the number.
    fetch(@json(route('topup.beneficiaries', ['category' => $category])), { headers: { Accept: 'application/json' } })
        .then(r => r.json()).then(j => {
            const box = $('savedRecipients');
            (j.beneficiaries || []).slice(0, 8).forEach(b => {
                const btn = document.createElement('button');
                btn.type = 'button'; btn.className = 'btn btn-sm btn-light rounded-pill';
                btn.innerHTML = '<i class="ri-user-star-line me-1"></i>' + escapeHtml(b.label ? b.label + ' · ' + b.recipient : b.recipient);
                btn.addEventListener('click', () => {
                    $('recipient').value = b.recipient;
                    if (b.service) { const r = document.querySelector('input[name=service_id][value="' + b.service.id + '"]'); if (r && !r.checked) { r.checked = true; r.dispatchEvent(new Event('change')); } }
                });
                box.appendChild(btn);
            });
        }).catch(() => {});

    loadService().then(() => { if (oldProduct) pickPlan(oldProduct); });
})();
</script>
@endpush
