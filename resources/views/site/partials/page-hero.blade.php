{{-- @include('site.partials.page-hero', ['eyebrow' => '…', 'title' => '…', 'lead' => '…', 'dark' => false]) --}}
<section class="s-page-hero {{ !empty($dark) ? 's-page-hero--dark' : '' }}">
    <div class="container position-relative">
        <div class="s-crumbs"><a href="{{ route('home') }}">Home</a><span>/</span><span>{{ $crumb ?? $title }}</span></div>
        <div class="row align-items-end g-4 mt-1">
            <div class="col-lg-8" data-reveal>
                @if(!empty($eyebrow))<span class="s-eyebrow {{ !empty($dark) ? 's-eyebrow--light' : '' }}">{{ $eyebrow }}</span>@endif
                <h1>{!! $titleHtml ?? e($title) !!}</h1>
                @if(!empty($lead))<p class="s-lead mb-0">{{ $lead }}</p>@endif
            </div>
            @if(!empty($aside))<div class="col-lg-4 text-lg-end" data-reveal>{!! $aside !!}</div>@endif
        </div>
    </div>
</section>
