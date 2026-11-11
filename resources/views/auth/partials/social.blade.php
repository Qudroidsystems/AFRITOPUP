{{--
    "Continue with Google / Apple" for the sign-in and sign-up pages. Each button appears once its
    provider is configured (config/afritopup.php → social). The provider's script gives the page an
    ID token; it is posted to auth.social, verified on the server and the person is signed in.
--}}
@php
    $gId = config('afritopup.social.google.web_client_id');
    $aId = config('afritopup.social.apple.web_client_id');
    $showGoogle = $gId && \App\Services\Auth\SocialTokenVerifier::enabled('google');
    $showApple = $aId && \App\Services\Auth\SocialTokenVerifier::enabled('apple');
@endphp
@if($showGoogle || $showApple)
    <div class="social-auth">
        <div class="social-sep"><span>or continue with</span></div>
        <div class="d-grid gap-2">
            @if($showGoogle)
                <div id="gBtn" class="d-flex justify-content-center"></div>
            @endif
            @if($showApple)
                <button type="button" class="btn social-apple w-100" id="appleBtn">
                    <svg width="16" height="18" viewBox="0 0 814 1000" aria-hidden="true"><path fill="currentColor" d="M788 341c-6 4-108 62-108 190 0 148 130 200 134 202-1 3-21 72-69 142-43 62-88 124-156 124s-86-40-164-40c-77 0-104 41-167 41s-106-57-156-128C44 789 0 668 0 554 0 371 119 274 236 274c62 0 114 41 153 41 37 0 95-43 166-43 27 0 124 2 189 69zM567 165c29-35 50-83 50-131 0-7-1-14-2-19-48 2-104 32-138 71-27 30-52 78-52 127 0 7 1 15 2 17 3 1 8 1 13 1 43 0 97-29 127-66z"/></svg>
                    <span>Continue with Apple</span>
                </button>
            @endif
        </div>
        <div class="small text-danger text-center mt-2" id="socialError" role="alert" hidden></div>
    </div>

    @push('styles')
    <style>
        .social-auth { margin-top: 1.5rem; animation: fadeInScale .5s ease .1s both; }
        .social-sep { display:flex; align-items:center; gap:.75rem; color:#94a3b8; font-size:12.5px; margin-bottom:1rem; }
        .social-sep::before, .social-sep::after { content:""; flex:1; height:1px; background:#e2e8f0; }
        .social-apple { display:flex; align-items:center; justify-content:center; gap:.5rem; background:#000; color:#fff; border-radius:12px; height:44px; font-weight:600; transition: transform .15s, box-shadow .15s; }
        .social-apple:hover { color:#fff; transform: translateY(-1px); box-shadow:0 8px 20px -10px rgba(0,0,0,.6); }
    </style>
    @endpush

    @push('scripts')
    <script>
    (function () {
        const errBox = document.getElementById('socialError');
        const fail = (m) => { errBox.textContent = m || 'Sign-in failed. Please try again.'; errBox.hidden = false; };
        async function send(provider, idToken, name) {
            errBox.hidden = true;
            try {
                const res = await fetch(@json(url('/auth/social')) + '/' + provider, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                    body: JSON.stringify({ id_token: idToken, name: name || null }),
                });
                const j = await res.json().catch(() => ({}));
                if (res.ok && j.success) { window.location.href = j.redirect; } else { fail(j.message); }
            } catch (e) { fail('Could not reach the server. Check your connection.'); }
        }

        @if($showGoogle)
        window.addEventListener('load', function () {
            if (!window.google || !google.accounts) return;
            google.accounts.id.initialize({ client_id: @json($gId), callback: (r) => send('google', r.credential), ux_mode: 'popup' });
            const box = document.getElementById('gBtn');
            google.accounts.id.renderButton(box, { theme: 'outline', size: 'large', shape: 'pill', text: 'continue_with', width: Math.min(box.offsetWidth || 360, 400) });
        });
        @endif

        @if($showApple)
        document.getElementById('appleBtn').addEventListener('click', async function () {
            if (!window.AppleID) return fail('Apple sign-in is still loading. Try again in a moment.');
            try {
                AppleID.auth.init({ clientId: @json($aId), scope: 'name email', redirectURI: @json(url('/login')), usePopup: true });
                const r = await AppleID.auth.signIn();
                const n = r.user && r.user.name ? [r.user.name.firstName, r.user.name.lastName].filter(Boolean).join(' ') : null;
                send('apple', r.authorization.id_token, n);
            } catch (e) { if (!e || e.error !== 'popup_closed_by_user') fail(); }
        });
        @endif
    })();
    </script>
    @if($showGoogle)<script src="https://accounts.google.com/gsi/client" async defer></script>@endif
    @if($showApple)<script src="https://appleid.cdn-apple.com/appleauth/static/jsapi/appleid/1/en_US/appleid.auth.js" defer></script>@endif
    @endpush
@endif
