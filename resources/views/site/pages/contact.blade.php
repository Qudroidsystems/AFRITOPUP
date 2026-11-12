@extends('layouts.site')
@section('header', 'light')
@section('title', 'Contact us | ' . ($brand['name'] ?? 'AfriTopup'))
@php $me = auth()->user(); @endphp

@section('content')
@include('site.partials.page-hero', ['crumb' => 'Contact', 
    'eyebrow' => 'Contact',
    'title' => 'Talk to us',
    'lead' => 'Questions about a payment, your wallet, or becoming an agent? We\'re here to help.',
])

<section class="s-section pt-4">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-7" data-reveal>
                <div class="s-card">
                    @if(session('contact_sent'))
                        <div class="text-center py-5">
                            <span class="s-ico s-ico--lg mb-3"><i class="ri-mail-check-line"></i></span>
                            <h2 class="h3">Message received</h2>
                            <p style="color: var(--s-muted)">Thanks — our team will reply to your e-mail shortly.</p>
                            <a href="{{ route('home') }}" class="s-btn s-btn--ghost mt-2">Back to home</a>
                        </div>
                    @else
                        <form method="POST" action="{{ route('site.contact.send') }}" class="s-form" novalidate>
                            @csrf
                            <div class="row g-3">
                                <div class="col-sm-6"><label for="cName">Your name</label><input id="cName" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $me?->name) }}" required autocomplete="name">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-sm-6"><label for="cEmail">E-mail</label><input id="cEmail" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $me?->email) }}" required autocomplete="email">@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="col-sm-6"><label for="cPhone">Phone <span class="fw-normal" style="color: var(--s-muted)">(optional)</span></label><input id="cPhone" name="phone" class="form-control" value="{{ old('phone', $me?->phone_number) }}" autocomplete="tel"></div>
                                <div class="col-sm-6"><label for="cTopic">About</label><select id="cTopic" name="topic" class="form-select">@foreach($topics as $k => $l)<option value="{{ $k }}" @selected(old('topic', request('topic')) === $k)>{{ $l }}</option>@endforeach</select></div>
                                <div class="col-12"><label for="cMsg">Message</label><textarea id="cMsg" name="message" rows="6" class="form-control @error('message') is-invalid @enderror" required placeholder="Include a transaction reference if it's about a payment.">{{ old('message') }}</textarea>@error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="d-none" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
                                <div class="col-12"><button class="s-btn s-btn--lg" type="submit">Send message <i class="ri-send-plane-line"></i></button></div>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
            <div class="col-lg-5">
                <div class="d-grid gap-3" data-reveal="right">
                    @if(!empty($brand['support_email']))<a href="mailto:{{ $brand['support_email'] }}" class="s-info"><span class="s-ico"><i class="ri-mail-line"></i></span><span><b>E-mail us</b><small>{{ $brand['support_email'] }}</small></span></a>@endif
                    @if(!empty($brand['support_phone']))<a href="tel:{{ preg_replace('/\s+/', '', $brand['support_phone']) }}" class="s-info"><span class="s-ico" style="--c:#F59E0B"><i class="ri-phone-line"></i></span><span><b>Call us</b><small>{{ $brand['support_phone'] }}</small></span></a>@endif
                    @if(config('afritopup.social_links.whatsapp'))<a href="{{ config('afritopup.social_links.whatsapp') }}" target="_blank" rel="noopener" class="s-info"><span class="s-ico" style="--c:#25D366"><i class="ri-whatsapp-line"></i></span><span><b>WhatsApp</b><small>Chat with support</small></span></a>@endif
                    @if(!empty($brand['address']))<div class="s-info"><span class="s-ico" style="--c:#7C3AED"><i class="ri-map-pin-line"></i></span><span><b>Office</b><small>{{ $brand['address'] }}</small></span></div>@endif
                    <a href="{{ route('site.page', 'faq') }}" class="s-info"><span class="s-ico" style="--c:#2563EB"><i class="ri-question-line"></i></span><span><b>Help centre</b><small>Quick answers to common questions.</small></span></a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
