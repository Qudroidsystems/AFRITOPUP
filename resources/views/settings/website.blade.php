@extends('layouts.master')

@php
    $f = fn ($key) => old(\Illuminate\Support\Str::afterLast($key, '.'), $v[$key] ?? '');
    $socials = [
        'facebook'  => ['Facebook', 'ri-facebook-circle-fill', '#1877F2', 'https://facebook.com/afritopup'],
        'x'         => ['X (Twitter)', 'ri-twitter-x-line', '#111111', 'https://x.com/afritopup'],
        'instagram' => ['Instagram', 'ri-instagram-line', '#E4405F', 'https://instagram.com/afritopup'],
        'linkedin'  => ['LinkedIn', 'ri-linkedin-box-fill', '#0A66C2', 'https://linkedin.com/company/afritopup'],
        'youtube'   => ['YouTube', 'ri-youtube-fill', '#FF0000', 'https://youtube.com/@afritopup'],
        'tiktok'    => ['TikTok', 'ri-tiktok-fill', '#111111', 'https://tiktok.com/@afritopup'],
    ];
@endphp

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Settings" icon="ri-settings-4-line" subtitle="Platform settings. Changes here take effect straight away." />
    @include('settings._tabs')
    @include('partials.flash')

    <form method="POST" action="{{ route('portal-settings.website.save') }}" autocomplete="off">
        @csrf
        <div class="row g-3">
            <div class="col-xl-8">
                <x-cb.card title="Mobile app download links" icon="ri-smartphone-line">
                    <p class="text-muted small">Shown on the website (hero, header, footer, phone banner and QR code) and in the customer area. Leave blank until the app is published — the buttons then say “Coming soon”.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="android"><i class="ri-google-play-fill text-success me-1"></i>Google Play link</label>
                        <input id="android" name="android" class="form-control @error('android') is-invalid @enderror" value="{{ $f('app_links.android') }}" placeholder="https://play.google.com/store/apps/details?id=com.afritopup.app">
                        @error('android')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="form-label fw-semibold" for="ios"><i class="ri-apple-fill me-1"></i>App Store link</label>
                        <input id="ios" name="ios" class="form-control @error('ios') is-invalid @enderror" value="{{ $f('app_links.ios') }}" placeholder="https://apps.apple.com/app/afritopup/id0000000000">
                        @error('ios')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </x-cb.card>

                <x-cb.card title="Social media" icon="ri-share-line">
                    <p class="text-muted small">Only the ones you fill in appear in the website footer.</p>
                    <div class="row g-3">
                        @foreach($socials as $key => [$label, $icon, $color, $ph])
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="s_{{ $key }}"><i class="{{ $icon }} me-1" style="color: {{ $color }}"></i>{{ $label }}</label>
                                <input id="s_{{ $key }}" name="{{ $key }}" class="form-control @error($key) is-invalid @enderror" value="{{ $f('social_links.' . $key) }}" placeholder="{{ $ph }}">
                                @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="s_whatsapp"><i class="ri-whatsapp-line me-1" style="color:#25D366"></i>WhatsApp</label>
                            <input id="s_whatsapp" name="whatsapp" class="form-control @error('whatsapp') is-invalid @enderror" value="{{ $f('social_links.whatsapp') }}" placeholder="08031234567 or https://wa.me/2348031234567">
                            <div class="form-text">A phone number is turned into a wa.me chat link.</div>
                        </div>
                    </div>
                </x-cb.card>

                <x-cb.card title="Contact details" icon="ri-customer-service-2-line">
                    <p class="text-muted small">Shown on the contact and help pages, receipts and in e-mails.</p>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label fw-semibold" for="support_email">Support e-mail</label>
                            <input id="support_email" name="support_email" type="email" class="form-control @error('support_email') is-invalid @enderror" value="{{ $f('support_email') }}" placeholder="support@afritopup.com">
                            @error('support_email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label class="form-label fw-semibold" for="support_phone">Support phone</label>
                            <input id="support_phone" name="support_phone" class="form-control" value="{{ $f('support_phone') }}" placeholder="+234 803 123 4567"></div>
                        <div class="col-12"><label class="form-label fw-semibold" for="address">Office address</label>
                            <input id="address" name="address" class="form-control" value="{{ $f('address') }}" placeholder="12 Example Street, Ikeja, Lagos"></div>
                    </div>
                </x-cb.card>

                <div class="d-flex gap-2 align-items-center mb-4">
                    <button class="btn btn-primary"><i class="ri-save-3-line me-1"></i>Save</button>
                    <a href="{{ route('home') }}#app" target="_blank" class="btn btn-light"><i class="ri-external-link-line me-1"></i>View on website</a>
                    @if($last)<span class="text-muted small ms-auto">Last changed {{ $last->updated_at->diffForHumans() }}{{ $last->editor ? ' by ' . $last->editor->name : '' }}</span>@endif
                </div>
            </div>

            <div class="col-xl-4">
                <x-cb.card title="Preview" icon="ri-eye-line">
                    <div class="d-grid gap-2 mb-3">
                        @foreach(['android' => ['ri-google-play-fill', 'Get it on', 'Google Play'], 'ios' => ['ri-apple-fill', 'Download on the', 'App Store']] as $k => [$ic, $small, $big])
                            @php $link = $v['app_links.' . $k] ?? null; @endphp
                            <div class="d-flex align-items-center gap-2 rounded-3 px-3 py-2 text-white" style="background:#0D1B16">
                                <i class="{{ $ic }} fs-3"></i><span class="lh-sm"><small class="d-block" style="font-size:10px;opacity:.75">{{ $small }}</small><b>{{ $big }}</b></span>
                                <span class="ms-auto badge {{ $link ? 'bg-success' : 'bg-secondary' }}">{{ $link ? 'Live' : 'Coming soon' }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="text-center border rounded-3 p-3">
                        <div class="d-inline-block bg-white p-2 rounded-3">{!! \App\Support\Qr::svg(route('site.app'), 140) !!}</div>
                        <div class="small text-muted mt-2">Scan code shown on the website. It opens <code>{{ route('site.app') }}</code>, which sends Android phones to Google Play and iPhones to the App Store.</div>
                    </div>
                </x-cb.card>
            </div>
        </div>
    </form>
</div></div></div>
@endsection
