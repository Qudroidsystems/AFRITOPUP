@extends($shell ?? 'layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    @include('topup.partials.styles')
    @php $base = url('/api/reseller/v1'); @endphp
    <x-cb.hero title="Reseller API" icon="ri-book-2-line" subtitle="JSON over HTTPS. Every response is { success, data } or { success: false, code, message }." :back="route('topup.developer')" back-label="API keys" />
    <style>.doc pre{background:#0f172a;color:#e2e8f0;border-radius:12px;padding:14px 16px;font-size:12.5px;overflow:auto}.doc h5{margin-top:1.4rem}.doc code{font-size:12.5px}</style>

    <div class="row g-3 doc">
        <div class="col-xl-8">
            <x-cb.card title="Authentication" icon="ri-key-2-line">
                <p>Send your key in the <code>Authorization</code> header. Base URL: <code>{{ $base }}</code></p>
<pre>curl {{ $base }}/balance \
  -H "Authorization: Bearer afk_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" \
  -H "Accept: application/json"</pre>
                <p class="small text-muted mb-0">120 requests a minute per key. Keys can be limited to your server's IP addresses.</p>
            </x-cb.card>

            <x-cb.card title="Endpoints" icon="ri-route-line">
                <h5>GET /balance</h5>
<pre>{ "success": true, "data": { "balance": 152340.5, "currency": "NGN" } }</pre>

                <h5>GET /services?category=data</h5>
                <p>Categories: <code>{{ implode('</code>, <code>', array_keys(config('afritopup.categories'))) }}</code>. Each service has a <code>code</code> (e.g. <code>mtn</code>, <code>mtn-data</code>, <code>dstv</code>, <code>ikeja-electric</code>, <code>waec</code>) and says whether it needs a <code>product</code>, an <code>amount</code> or verification.</p>

                <h5>GET /services/{code}/products</h5>
                <p>Plans with <code>code</code>, <code>name</code>, <code>validity</code> and <code>your_price</code>.</p>

                <h5>POST /verify</h5>
                <p>Check a smartcard, meter or JAMB profile before buying.</p>
<pre>{ "service": "ikeja-electric", "customer_id": "45012345678", "meter_type": "prepaid" }</pre>

                <h5>POST /purchase</h5>
                <p><code>reference</code> is <strong>your</strong> unique ID for the purchase. Sending the same reference again returns the original purchase — it never buys twice, so retry safely after a timeout.</p>
<pre>// Airtime
{ "reference": "ORD-10045", "service": "mtn", "recipient": "08031234567", "amount": 500 }

// Data
{ "reference": "ORD-10046", "service": "mtn-data", "product": "mtn-1gb-30d", "recipient": "08031234567" }

// Cable TV
{ "reference": "ORD-10047", "service": "dstv", "product": "dstv-compact", "recipient": "7012345678",
  "subscription_type": "change", "phone": "08031234567" }

// Electricity
{ "reference": "ORD-10048", "service": "ikeja-electric", "recipient": "45012345678",
  "meter_type": "prepaid", "amount": 5000, "phone": "08031234567" }

// Exam PINs
{ "reference": "ORD-10049", "service": "waec", "product": "waecdirect", "quantity": 2, "recipient": "08031234567" }

// Bulk SMS (sender must be approved on your account)
{ "reference": "ORD-10050", "service": "bulk-sms", "sender": "MyShop",
  "recipients": ["2348031234567", "2348051234567"], "message": "Your order is on its way." }</pre>
                <p>Response (<code>201</code> for a new purchase, <code>200</code> for a repeated reference):</p>
<pre>{ "success": true, "data": {
    "reference": "AFT-20261111-8F3K2Q9X", "client_reference": "ORD-10048",
    "category": "electricity", "status": "successful", "message": "Delivered.",
    "amount": 5000, "discount": 50, "fee": 0, "total": 4950, "currency": "NGN",
    "token": "1234-5678-9012-3456-7890", "customer_name": "ADEBAYO JOHN", ... } }</pre>
                <p><code>status</code> is <code>successful</code>, <code>processing</code> (we are still waiting for the network — check again or wait for the webhook), <code>failed</code> or <code>refunded</code>. Failed and refunded purchases are credited back to your wallet automatically.</p>

                <h5>GET /transactions/{reference}</h5>
                <p>Look up a purchase by our reference or yours.</p>
            </x-cb.card>

            <x-cb.card title="Webhooks" icon="ri-links-line">
                <p>When a purchase finishes (<code>transaction.successful</code>, <code>transaction.failed</code>, <code>transaction.refunded</code>) we POST it to your key's webhook URL:</p>
<pre>POST https://your-server/webhook
X-AfriTopup-Signature: &lt;HMAC-SHA256 of the raw body with your webhook secret&gt;

{ "event": "transaction.successful", "data": { ...same as the purchase response... } }</pre>
                <p class="mb-0">Reply with any 2xx status. We retry for about 40 minutes if you don't. Each key's webhook secret is on the API keys page, under <em>Edit</em>.</p>
            </x-cb.card>
        </div>
        <div class="col-xl-4">
            <x-cb.card title="Error codes" icon="ri-error-warning-line">
                @foreach(['unauthenticated' => 'Missing or wrong API key', 'ip_not_allowed' => 'Request from an IP not on the key', 'validation_failed' => 'A field is missing or wrong (see errors)', 'insufficient_funds' => 'Fund your wallet', 'service_unavailable' => 'Service switched off', 'product_required' => 'Choose a plan', 'invalid_recipient' => 'Phone / smartcard / meter not valid', 'verification_failed' => 'Provider could not find the customer', 'amount_too_small' => 'Below the service minimum', 'amount_too_large' => 'Above the service maximum', 'duplicate' => 'Same purchase within a minute', 'sender_not_approved' => 'SMS sender ID not approved', 'too_many_requests' => 'Slow down'] as $c => $d)
                    <div class="at-kv"><span class="at-mono">{{ $c }}</span><span class="fw-normal">{{ $d }}</span></div>
                @endforeach
            </x-cb.card>
        </div>
    </div>
</div></div></div>
@endsection
