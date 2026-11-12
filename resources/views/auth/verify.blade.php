@extends('layouts.auth')

@section('title', 'Confirm your e-mail')
@section('badge')<span class="auth-badge"><i class="ri-mail-check-line"></i></span>@endsection
@section('heading', 'Check your inbox')
@section('subheading')We sent a 6-digit code to <b>{{ $email }}</b>@endsection

@section('content')
    <form method="POST" action="{{ route('verification.code') }}" id="codeForm" data-auth-form autocomplete="off">
        @csrf
        <input type="hidden" name="code" id="codeValue">
        <div class="otp" role="group" aria-label="6-digit code">
            @for($i = 0; $i < 6; $i++)
                <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="otp-box @error('code') is-invalid @enderror" aria-label="Digit {{ $i + 1 }}" @if($i === 0) autofocus autocomplete="one-time-code" @endif>
            @endfor
        </div>
        @error('code')<div class="text-danger small text-center mt-2 fw-semibold" role="alert">{{ $message }}</div>@enderror

        <button class="btn btn-primary w-100 apple-button mt-4" type="submit" id="codeBtn" data-loading-text="Checking..."><span>Confirm e-mail</span></button>
    </form>

    <div class="text-center mt-4 small text-muted">
        <div>You can also tap the button in the e-mail.</div>
        <form method="POST" action="{{ route('verification.send') }}" class="d-inline">
            @csrf
            <span>Didn't get it? Check spam or promotions, then</span>
            <button type="submit" class="btn btn-link btn-sm p-0 align-baseline fw-semibold" id="resendBtn" data-wait="{{ $wait }}">send a new code</button>
        </form>
    </div>
@endsection

@section('footer')
    <form method="POST" action="{{ route('logout') }}" class="d-inline">@csrf
        <p class="mb-0 text-muted small">Wrong address? <button type="submit" class="btn btn-link btn-sm p-0 align-baseline fw-semibold text-decoration-none">Sign out and start again</button></p>
    </form>
@endsection

@push('styles')
<style>
    .otp { display: flex; gap: 8px; justify-content: center; }
    .otp-box { width: 48px; height: 58px; text-align: center; font-size: 24px; font-weight: 700; border-radius: 14px; border: 1.5px solid #dfe5e2; background: #fff; transition: border-color .15s, box-shadow .15s, transform .15s; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    .otp-box:focus { outline: 0; border-color: #0B8457; box-shadow: 0 0 0 4px rgba(11,132,87,.15); transform: translateY(-1px); }
    .otp-box.is-invalid { border-color: #dc3545; animation: otpShake .35s; }
    @keyframes otpShake { 25% { transform: translateX(-4px); } 75% { transform: translateX(4px); } }
    @media (max-width: 380px) { .otp-box { width: 40px; height: 50px; font-size: 20px; } .otp { gap: 6px; } }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var boxes = Array.prototype.slice.call(document.querySelectorAll('.otp-box'));
    var form = document.getElementById('codeForm'), hidden = document.getElementById('codeValue');
    function value() { return boxes.map(function (b) { return b.value; }).join(''); }
    function fill(str) { str.replace(/\D/g, '').slice(0, 6).split('').forEach(function (d, i) { boxes[i].value = d; }); }
    boxes.forEach(function (b, i) {
        b.addEventListener('input', function () {
            if (b.value.length > 1) { fill(b.value); }
            b.value = b.value.replace(/\D/g, '').slice(0, 1);
            if (b.value && i < 5) boxes[i + 1].focus();
            if (value().length === 6) { hidden.value = value(); form.requestSubmit ? form.requestSubmit() : form.submit(); }
        });
        b.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && !b.value && i > 0) boxes[i - 1].focus();
            if (e.key === 'ArrowLeft' && i > 0) boxes[i - 1].focus();
            if (e.key === 'ArrowRight' && i < 5) boxes[i + 1].focus();
        });
        b.addEventListener('paste', function (e) {
            var t = (e.clipboardData || window.clipboardData).getData('text');
            if (/\d/.test(t)) { e.preventDefault(); fill(t); var n = Math.min(value().length, 5); boxes[n].focus(); if (value().length === 6) { hidden.value = value(); form.submit(); } }
        });
    });
    form.addEventListener('submit', function () { hidden.value = value(); });

    // resend countdown
    var btn = document.getElementById('resendBtn'), wait = parseInt(btn.dataset.wait || '0', 10), label = btn.textContent;
    function tick() {
        if (wait > 0) { btn.disabled = true; btn.textContent = 'send a new code in ' + wait + 's'; wait--; setTimeout(tick, 1000); }
        else { btn.disabled = false; btn.textContent = label; }
    }
    tick();
})();
</script>
@endpush
