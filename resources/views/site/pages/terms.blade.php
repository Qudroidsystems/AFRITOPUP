@extends('layouts.site')
@section('header', 'light')
@section('title', 'Terms of service | ' . ($brand['name'] ?? 'AfriTopup'))
@php $name = $brand['name'] ?? 'AfriTopup'; $co = $brand['company'] ?? $name; @endphp

@section('content')
@include('site.partials.page-hero', ['crumb' => 'Terms', 'eyebrow' => 'Legal', 'title' => 'Terms of service', 'lead' => 'Last updated ' . now()->format('j F Y') . '. Please read these terms before using ' . $name . '.'])
<section class="s-section pt-4">
    <div class="container"><div class="s-prose">
        <h2>1. About these terms</h2>
        <p>These terms are an agreement between you and {{ $co }} ("we", "us") for the use of the {{ $name }} website, mobile apps and API (the "Service"). By creating an account or using the Service you accept them.</p>
        <h2>2. Your account</h2>
        <p>You must give accurate information, confirm your e-mail address, and keep your password and 4-digit transaction PIN secret. You are responsible for activity on your account. Tell us straight away if you think someone else has access to it.</p>
        <h2>3. Your wallet</h2>
        <p>You fund your wallet by card or bank transfer. Wallet balances are used only to buy services on {{ $name }}; they do not earn interest. Funding fees, where they apply, are shown before you pay. Limits on balance and daily spending depend on your verification level.</p>
        <h2>4. Purchases</h2>
        <p>Purchases are fulfilled by networks, billers and their partners. Check the phone number, smartcard or meter number before paying — a purchase sent to the details you entered cannot be reversed once delivered. If a purchase fails, the amount is returned to your wallet.</p>
        <h2>5. Agents, resellers and the API</h2>
        <p>Accounts upgraded to agent or reseller buy at discounted prices and may resell to their own customers. API keys must be kept secret and used only from your own systems. We may suspend keys that are abused or that put the Service at risk.</p>
        <h2>6. Acceptable use</h2>
        <p>You may not use the Service for fraud, money laundering, to send spam or unlawful SMS, or to resell stolen card funds. We may refuse, hold or reverse transactions and close accounts where we reasonably suspect misuse, and report it to the authorities.</p>
        <h2>7. Liability</h2>
        <p>We work hard to keep the Service available and accurate, but it is provided "as is". To the extent the law allows, we are not liable for indirect losses, or for delays caused by networks, billers or banks outside our control.</p>
        <h2>8. Changes</h2>
        <p>We may update these terms. If changes are significant we will tell you by e-mail or in the app before they take effect.</p>
        <h2>9. Contact</h2>
        <p>Questions? <a href="{{ route('site.contact') }}">Contact us</a>{{ !empty($brand['support_email']) ? ' or e-mail ' . $brand['support_email'] : '' }}.</p>
    </div></div>
</section>
@endsection
