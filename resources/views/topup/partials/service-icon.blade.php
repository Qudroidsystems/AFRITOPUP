{{-- A biller's logo, or its initials on its brand colour. @include('topup.partials.service-icon', ['s' => $service, 'size' => 40]) --}}
@php $sz = $size ?? 40; @endphp
@if($s?->logo_url)
    <img src="{{ $s->logo_url }}" alt="{{ $s->display_name }}" width="{{ $sz }}" height="{{ $sz }}" style="border-radius:{{ round($sz / 4) }}px;object-fit:contain;background:#fff" loading="lazy">
@else
    <span class="at-svc-ico" style="--c:{{ $s?->color ?: '#0B8457' }};width:{{ $sz }}px;height:{{ $sz }}px;font-size:{{ round($sz * .34) }}px">{{ mb_strtoupper(mb_substr($s?->display_name ?? '?', 0, $sz >= 36 ? 3 : 1)) }}</span>
@endif
