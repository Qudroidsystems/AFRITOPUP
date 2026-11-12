{{--
    Google Play / App Store buttons. Links come from Settings → Website & app.
    @include('site.partials.app-badges', ['size' => 'sm'|'md', 'light' => false, 'stack' => false])
    A store without a link shows "Coming soon" and points to the app section.
--}}
@php
    $links = config('afritopup.app_links', []);
    $stores = [
        'android' => ['ri-google-play-fill', 'Get it on', 'Google Play', 'Google Play'],
        'ios'     => ['ri-apple-fill', 'Download on the', 'App Store', 'the App Store'],
    ];
@endphp
<div class="s-stores {{ !empty($stack) ? 's-stores--stack' : '' }} {{ ($size ?? 'md') === 'sm' ? 's-stores--sm' : '' }}">
    @foreach($stores as $k => [$icon, $small, $big, $aria])
        @php $href = $links[$k] ?? null; @endphp
        <a class="s-store {{ !empty($light) ? 's-store--light' : '' }} {{ $href ? '' : 'is-soon' }}"
           href="{{ $href ?: route('home') . '#app' }}" @if($href) target="_blank" rel="noopener" @endif
           aria-label="{{ $href ? 'Get the app on ' . $aria : $big . ' app coming soon' }}">
            <i class="{{ $icon }}"></i>
            <span><small>{{ $href ? $small : 'Coming soon to' }}</small><b>{{ $big }}</b></span>
        </a>
    @endforeach
</div>
