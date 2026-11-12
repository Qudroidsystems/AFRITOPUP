@extends('layouts.site')
@section('header', 'light')
@section('title', 'Privacy policy | ' . ($brand['name'] ?? 'AfriTopup'))
@php $name = $brand['name'] ?? 'AfriTopup'; $co = $brand['company'] ?? $name; @endphp

@section('content')
@include('site.partials.page-hero', ['crumb' => 'Privacy', 'eyebrow' => 'Legal', 'title' => 'Privacy policy', 'lead' => 'Last updated ' . now()->format('j F Y') . '. How ' . $name . ' collects, uses and protects your information.'])
<section class="s-section pt-4">
    <div class="container"><div class="s-prose">
        <h2>What we collect</h2>
        <ul>
            <li><b>Account details</b> — your name, e-mail address, phone number and password (stored hashed).</li>
            <li><b>Payments</b> — what you buy, recipients you pay, wallet funding and receipts. Card details are handled by our payment partner, Paystack; we never see or store your full card number.</li>
            <li><b>Verification</b> — if you upgrade your account, ID details and documents you submit (ID numbers are stored encrypted).</li>
            <li><b>Device &amp; usage</b> — IP address, browser or app version, and sign-in times, to keep your account secure.</li>
        </ul>
        <h2>How we use it</h2>
        <p>To run your account and deliver your purchases; to send receipts, security codes and important notices; to prevent fraud; to meet legal and regulatory obligations; and to improve {{ $name }}. We do not sell your personal information.</p>
        <h2>Who we share it with</h2>
        <p>Only with the partners needed to complete what you ask — networks, billers and service providers that fulfil purchases, payment and banking partners, and e-mail/SMS providers — and with authorities where the law requires it.</p>
        <h2>How long we keep it</h2>
        <p>For as long as your account is open, and afterwards for as long as financial-records laws require.</p>
        <h2>Your rights</h2>
        <p>You can view and update your details in your account, and ask us for a copy of your data or for it to be deleted (subject to legal retention). Under the Nigeria Data Protection Act 2023 and similar laws you may also object to certain processing or complain to the regulator.</p>
        <h2>Security</h2>
        <p>Connections are encrypted, passwords and PINs are hashed, sensitive fields are encrypted at rest, and staff access is limited and logged.</p>
        <h2>Contact</h2>
        <p>{{ $co }} — <a href="{{ route('site.contact') }}">contact us</a>{{ !empty($brand['support_email']) ? ' or e-mail ' . $brand['support_email'] : '' }}.</p>
    </div></div>
</section>
@endsection
