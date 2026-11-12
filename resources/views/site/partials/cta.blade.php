<section class="s-section pt-0">
    <div class="container">
        <div class="s-cta" data-reveal="zoom">
            <h2>{{ $ctaTitle ?? 'Your next payment is 30 seconds away' }}</h2>
            <p>{{ $ctaText ?? 'Join ' . ($brand['name'] ?? 'AfriTopup') . ' free. No monthly fees — you only pay for what you buy.' }}</p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                @auth
                    <a href="{{ route('topup.buy') }}" class="s-btn s-btn--gold s-btn--lg">Make a payment <i class="ri-arrow-right-line"></i></a>
                @else
                    <a href="{{ Route::has('register') ? route('register') : route('login') }}" class="s-btn s-btn--gold s-btn--lg">Create free account <i class="ri-arrow-right-line"></i></a>
                    <a href="{{ route('login') }}" class="s-btn s-btn--light s-btn--lg">I have an account</a>
                @endauth
            </div>
        </div>
    </div>
</section>
