{{--
    The AfriTopup logotype: a green tile with a lightning "A", then "Afri" in brand green and "Topup" in gold.
    The page must load public/css/brand-logo.css in its <head> (master, guest and auth layouts do).
      <x-brand-logo />                         tile + wordmark
      <x-brand-logo animate size="52px" />     with the entrance animation
      <x-brand-logo dark />                    for dark backgrounds
      <x-brand-logo mark />                    just the tile
    White-label: the wordmark splits the brand name from config/afritopup.php (AFRITOPUP_NAME)
    at its first capital after the first letter, so "AfriTopup" reads Afri|Topup and "PayNest" Pay|Nest.
--}}
@props(['animate' => false, 'dark' => false, 'mark' => false, 'size' => null, 'name' => null, 'rest' => true])
@php
    $blName = trim($name ?? (config('afritopup.name') ?: 'AfriTopup'));
    preg_match('/^(.[^A-Z]*)(.*)$/u', $blName, $blParts);
    $blA = $blParts[1] ?? $blName;
    $blB = $blParts[2] ?? '';
    $blLead = mb_strtoupper(mb_substr($blName, 0, 1));
@endphp
<span {{ $attributes->class(['blogo', 'blogo--animate' => $animate, 'blogo--dark' => $dark]) }} @if($size) style="font-size: {{ $size }}" @endif
      role="img" aria-label="{{ $blName }}">
    <span class="blogo-tile" aria-hidden="true"><span>{{ $blLead }}</span><i class="blogo-bolt"></i></span>@unless($mark)<span class="blogo-word" aria-hidden="true"><span class="blogo-a">{{ $blA }}</span><span class="blogo-b">{{ $blB }}</span></span>@endunless
</span>
