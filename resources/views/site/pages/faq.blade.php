@extends('layouts.site')
@section('header', 'light')
@section('title', 'Help centre | ' . ($brand['name'] ?? 'AfriTopup'))

@section('content')
@include('site.partials.page-hero', ['crumb' => 'Help centre', 
    'eyebrow' => 'Help centre',
    'title' => 'How can we help?',
    'lead' => 'Answers to the questions we hear most. Still stuck? Send us a message and we\'ll get back to you quickly.',
])

<section class="s-section pt-4">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-8">
                <div class="s-search mb-4" data-reveal><i class="ri-search-line"></i><input class="form-control form-control-lg" style="border-radius: 16px; padding-left: 2.6rem" placeholder="Search help…" data-filter=".s-faq details" aria-label="Search help"></div>
                <div class="s-faq" data-reveal>
                    @foreach($faqs as $j => [$q, $a])
                        <details @if($j === 0) open @endif><summary>{{ $q }}<i class="ri-add-line"></i></summary><div class="s-faq-a">{{ $a }}</div></details>
                    @endforeach
                </div>
            </div>
            <div class="col-lg-4">
                <div class="d-grid gap-3" data-reveal="right">
                    <a href="{{ route('site.contact') }}" class="s-info"><span class="s-ico"><i class="ri-customer-service-2-line"></i></span><span><b>Contact support</b><small>Send us a message — we reply fast.</small></span></a>
                    @if(!empty($brand['support_email']))<a href="mailto:{{ $brand['support_email'] }}" class="s-info"><span class="s-ico" style="--c:#2563EB"><i class="ri-mail-line"></i></span><span><b>E-mail</b><small>{{ $brand['support_email'] }}</small></span></a>@endif
                    @if(!empty($brand['support_phone']))<a href="tel:{{ preg_replace('/\s+/', '', $brand['support_phone']) }}" class="s-info"><span class="s-ico" style="--c:#F59E0B"><i class="ri-phone-line"></i></span><span><b>Phone</b><small>{{ $brand['support_phone'] }}</small></span></a>@endif
                    @auth<a href="{{ route('topup.transactions') }}" class="s-info"><span class="s-ico" style="--c:#7C3AED"><i class="ri-file-list-3-line"></i></span><span><b>Check a payment</b><small>See the status of your purchases.</small></span></a>@endauth
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
