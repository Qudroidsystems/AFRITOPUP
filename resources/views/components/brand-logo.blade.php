{{--
    The Pericharge logo: a green tile with a white "P", a lightning bolt in its bowl and a gold
    charging ring around it, then "Peri" in brand green and "charge" in gold.
    The page must load public/css/brand-logo.css in its <head> (master, site, account and auth layouts do).
      <x-brand-logo />                         tile + wordmark
      <x-brand-logo animate size="52px" />     entrance animation: the P draws itself, the bolt strikes, the ring charges
      <x-brand-logo animate :rest="false" />   …and keeps charging (page loader)
      <x-brand-logo dark />                    for dark backgrounds
      <x-brand-logo mark />                    just the tile
    White-label: the wordmark splits the brand name using config('pericharge.wordmark') (Peri|charge),
    or else at its first capital after the first letter ("PayNest" → Pay|Nest).
--}}
@props(['animate' => false, 'dark' => false, 'mark' => false, 'size' => null, 'name' => null, 'rest' => true])
@php
    $blName = trim($name ?? (config('pericharge.name') ?: 'Pericharge'));
    [$blA, $blB] = \App\Support\Brand::wordmark($blName);
    $blId = 'pc' . substr(md5(uniqid('', true)), 0, 6);   // gradient ids must be unique on the page
@endphp
<span {{ $attributes->class(['blogo', 'blogo--animate' => $animate, 'blogo--charging' => $animate && !$rest, 'blogo--dark' => $dark]) }} @if($size) style="font-size: {{ $size }}" @endif
      role="img" aria-label="{{ $blName }}">
    <svg class="blogo-tile" viewBox="0 0 64 64" aria-hidden="true" focusable="false">
        <defs>
            <linearGradient id="{{ $blId }}g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#14B871"/><stop offset=".55" stop-color="#0B8457"/><stop offset="1" stop-color="#065F3F"/></linearGradient>
            <linearGradient id="{{ $blId }}b" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FFD36E"/><stop offset="1" stop-color="#F59E0B"/></linearGradient>
            <clipPath id="{{ $blId }}c"><rect width="64" height="64" rx="17"/></clipPath>
            <radialGradient id="{{ $blId }}s" cx=".2" cy=".1" r=".95"><stop offset="0" stop-color="#fff" stop-opacity=".26"/><stop offset=".55" stop-color="#fff" stop-opacity="0"/></radialGradient>
        </defs>
        <rect class="blogo-bg" width="64" height="64" rx="17" fill="url(#{{ $blId }}g)"/>
        <rect width="64" height="64" rx="17" fill="url(#{{ $blId }}s)"/>
        <g transform="translate(-1.5 1.5)">
            <circle class="blogo-track" cx="34" cy="28" r="18.5" fill="none" stroke="#fff" stroke-opacity=".16" stroke-width="2.4"/>
            <circle class="blogo-ring" cx="34" cy="28" r="18.5" fill="none" stroke="#FFD36E" stroke-width="2.4" stroke-linecap="round"
                    pathLength="100" stroke-dasharray="33 100" transform="rotate(-90 34 28)"/>
            <path class="blogo-p" d="M24 50 V17.5 H34 a10.5 10.5 0 0 1 0 21 H24" pathLength="100" fill="none" stroke="#fff" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
            <path class="blogo-bolt" d="M37 19.6 29.6 30.2h5l-2.4 8.4 7.8-11.1h-5.2z" fill="url(#{{ $blId }}b)"/>
        </g>
        <g clip-path="url(#{{ $blId }}c)"><rect class="blogo-sweep" x="-40" y="-8" width="22" height="80" fill="#fff" opacity="0"/></g>
    </svg>@unless($mark)<span class="blogo-word" aria-hidden="true"><span class="blogo-a">{{ $blA }}</span><span class="blogo-b">{{ $blB }}</span></span>@endunless
</span>
