@extends('layouts.account')

@php
    $initials = collect(explode(' ', trim($user->name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->join('');
@endphp

@section('content')
<div class="a-page">
    <div class="a-hello">
        <div><h1>Profile &amp; security</h1><p>Your details, sign-in and transaction PIN.</p></div>
    </div>
    @include('partials.flash')

    <div class="row g-4">
        <div class="col-lg-3 d-none d-lg-block">
            <nav class="a-side-nav">
                <a href="#details" class="is-on"><i class="ri-user-3-line"></i> Your details</a>
                <a href="#email"><i class="ri-mail-line"></i> E-mail</a>
                <a href="#password"><i class="ri-key-2-line"></i> Password</a>
                <a href="#pin"><i class="ri-lock-password-line"></i> Transaction PIN</a>
            </nav>
        </div>
        <div class="col-lg-9 d-grid gap-4">

            {{-- details --}}
            <section class="a-card" id="details">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="a-avatar-lg" id="avatarBox">@if($user->avatar)<img src="{{ $user->avatar_url }}" alt="">@else{{ $initials }}@endif</div>
                    <div>
                        <h2 class="h5 mb-1">{{ $user->name }}</h2>
                        <div class="small" style="color: var(--s-muted)">{{ $user->user_type_label }} · member since {{ $user->created_at?->format('M Y') }}</div>
                        <label class="s-link small mt-1" style="cursor:pointer"><i class="ri-camera-line"></i> Change photo<input type="file" accept="image/png,image/jpeg,image/webp" id="avatarInput" hidden></label>
                    </div>
                </div>
                <form method="POST" action="{{ route('profile.update-info') }}" class="a-form">
                    @csrf
                    <input type="hidden" name="id" value="{{ $user->id }}">
                    <div class="row g-3">
                        <div class="col-md-6"><label for="pName">Full name</label><input id="pName" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required autocomplete="name">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label for="pPhone">Phone number</label><input id="pPhone" name="phone_number" class="form-control @error('phone_number') is-invalid @enderror" value="{{ old('phone_number', $user->phone_number) }}" inputmode="tel" autocomplete="tel">@error('phone_number')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label for="pUser">Username</label><input id="pUser" name="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username', $user->username) }}" autocomplete="username">@error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label for="pDob">Date of birth</label><input id="pDob" type="date" name="date_of_birth" class="form-control" value="{{ old('date_of_birth', $user->date_of_birth?->format('Y-m-d')) }}"></div>
                        <div class="col-12"><label for="pAddr">Address</label><input id="pAddr" name="address" class="form-control" value="{{ old('address', $user->address) }}" autocomplete="street-address"></div>
                        <div class="col-12"><button class="s-btn" type="submit">Save details</button></div>
                    </div>
                </form>
            </section>

            {{-- e-mail --}}
            <section class="a-card" id="email">
                <div class="a-card-head"><h2>E-mail address</h2>
                    @if($user->hasVerifiedEmail())<span class="a-pill a-pill--ok"><i class="ri-checkbox-circle-fill"></i> Confirmed</span>@else<span class="a-pill a-pill--wait">Not confirmed</span>@endif
                </div>
                <p class="small" style="color: var(--s-muted)">Currently <b>{{ $user->email }}</b>. Changing it means confirming the new address.</p>
                <form class="a-form" data-json="{{ route('profile.update-email') }}">
                    <input type="hidden" name="userid" value="{{ $user->id }}">
                    <div class="row g-3">
                        <div class="col-md-6"><label for="eNew">New e-mail</label><input id="eNew" type="email" name="emailaddress" class="form-control" required autocomplete="email"></div>
                        <div class="col-md-6"><label for="eNew2">Repeat new e-mail</label><input id="eNew2" type="email" name="emailaddress_confirmation" class="form-control" required></div>
                        <div class="col-12"><button class="s-btn s-btn--ghost" type="submit">Change e-mail</button></div>
                    </div>
                </form>
            </section>

            {{-- password --}}
            <section class="a-card" id="password">
                <div class="a-card-head"><h2>Password</h2></div>
                <form class="a-form" data-json="{{ route('profile.update-password') }}">
                    <input type="hidden" name="userid" value="{{ $user->id }}">
                    <div class="row g-3">
                        <div class="col-md-4"><label for="pwCur">Current password</label><input id="pwCur" type="password" name="current_password" class="form-control" autocomplete="current-password" required></div>
                        <div class="col-md-4"><label for="pwNew">New password</label><input id="pwNew" type="password" name="password" class="form-control" autocomplete="new-password" minlength="8" required></div>
                        <div class="col-md-4"><label for="pwNew2">Repeat new password</label><input id="pwNew2" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required></div>
                        <div class="col-12 small" style="color: var(--s-muted)">At least 8 characters with letters and numbers.</div>
                        <div class="col-12"><button class="s-btn s-btn--ghost" type="submit">Update password</button></div>
                    </div>
                </form>
            </section>

            {{-- receipts --}}
            <section class="a-card d-flex flex-wrap align-items-center gap-3" id="receipts">
                <span class="s-ico s-ico--lg"><i class="ri-mail-check-line"></i></span>
                <div class="flex-grow-1"><h2 class="h5 mb-1">E-mail receipts</h2><div class="small" style="color: var(--s-muted)">We e-mail a receipt (with a PDF copy) for every payment, refund and wallet top-up.</div></div>
                <form method="POST" action="{{ route('account.receipts') }}">@csrf
                    <input type="hidden" name="email_receipts" value="{{ $user->wantsEmailReceipts() ? 0 : 1 }}">
                    <button class="s-btn {{ $user->wantsEmailReceipts() ? 's-btn--ghost' : '' }}" type="submit">{{ $user->wantsEmailReceipts() ? 'Turn off' : 'Turn on' }}</button>
                </form>
            </section>

            {{-- PIN --}}
            <section class="a-card d-flex flex-wrap align-items-center gap-3" id="pin">
                <span class="s-ico s-ico--lg" style="--c:#F59E0B"><i class="ri-lock-password-line"></i></span>
                <div class="flex-grow-1"><h2 class="h5 mb-1">Transaction PIN</h2><div class="small" style="color: var(--s-muted)">{{ $hasPin ? 'Your 4-digit PIN protects every payment.' : 'You have not set a PIN yet — you need one to pay.' }}</div></div>
                <a href="{{ route('topup.wallet') }}#pin" class="s-btn {{ $hasPin ? 's-btn--ghost' : 's-btn--gold' }}">{{ $hasPin ? 'Change PIN' : 'Set PIN' }}</a>
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var token = document.querySelector('meta[name=csrf-token]').content;
    function post(url, body) {
        return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }, body: body })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok && j.success !== false, j: j }; }); });
    }
    function firstError(j) { if (j.errors) { var k = Object.keys(j.errors)[0]; return j.errors[k][0]; } return j.message || 'Something went wrong.'; }

    document.querySelectorAll('form[data-json]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = f.querySelector('[type=submit]'); btn.classList.add('is-loading');
            post(f.dataset.json, new FormData(f)).then(function (res) {
                btn.classList.remove('is-loading');
                window.siteToast(res.ok ? (res.j.message || 'Saved') : firstError(res.j));
                if (res.ok) { f.reset(); if (f.querySelector('[name=emailaddress]')) setTimeout(function () { location.reload(); }, 900); }
            }).catch(function () { btn.classList.remove('is-loading'); window.siteToast('Network error — try again.'); });
        });
    });

    var input = document.getElementById('avatarInput');
    input.addEventListener('change', function () {
        if (!input.files[0]) return;
        var fd = new FormData(); fd.append('avatar', input.files[0]); fd.append('id', '{{ $user->id }}');
        window.siteToast('Uploading…');
        post('{{ route('profile.update-avatar') }}', fd).then(function (res) {
            if (res.ok && res.j.avatar_url) { document.getElementById('avatarBox').innerHTML = '<img src="' + res.j.avatar_url + '" alt="">'; window.siteToast('Photo updated'); }
            else window.siteToast(firstError(res.j));
        });
    });

    // side nav highlight
    var links = document.querySelectorAll('.a-side-nav a');
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (es) { es.forEach(function (en) { if (en.isIntersecting) links.forEach(function (l) { l.classList.toggle('is-on', l.getAttribute('href') === '#' + en.target.id); }); }); }, { rootMargin: '-40% 0px -55% 0px' });
        document.querySelectorAll('section.a-card[id]').forEach(function (s) { io.observe(s); });
    }
})();
</script>
@endpush
