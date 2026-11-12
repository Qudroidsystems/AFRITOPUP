@php
    use App\Models\Transaction; use App\Support\Money;
    $m = fn ($v) => Money::format($v, $t->currency);
    $name = config('afritopup.name', 'AfriTopup');
    $green = config('afritopup.primary_color') ?: '#0B8457';
    $what = $t->product?->name ?? trim(($t->service?->display_name ?? '') . ' ' . $t->category_label);
    $status = ['successful' => ['#08744B', '#E3F7EE'], 'failed' => ['#B42318', '#FDE8E8'], 'refunded' => ['#1D4ED8', '#EAF2FF']][$t->status] ?? ['#946200', '#FFF4DE'];
@endphp
<!doctype html>
<html><head><meta charset="utf-8"><title>Receipt {{ $t->reference }}</title>
<style>
    @page { margin: 28px 30px; }
    body { font-family: DejaVu Sans, sans-serif; color: #0D1B16; font-size: 11px; }
    .brand { font-size: 18px; font-weight: bold; color: {{ $green }}; }
    .muted { color: #6B7C75; }
    .amount { font-size: 26px; font-weight: bold; margin: 14px 0 4px; }
    .pill { display: inline-block; padding: 3px 9px; border-radius: 10px; font-weight: bold; font-size: 10px; color: {{ $status[0] }}; background: {{ $status[1] }}; }
    table.rows { width: 100%; border-collapse: collapse; margin-top: 14px; }
    table.rows td { padding: 7px 0; border-bottom: 1px solid #E4ECE8; }
    table.rows td.v { text-align: right; font-weight: bold; }
    .token { margin-top: 14px; padding: 10px 12px; border: 1px dashed #D9B45A; background: #FFF8E6; border-radius: 8px; }
    .token b { font-family: DejaVu Sans Mono, monospace; font-size: 15px; letter-spacing: 1px; }
    .foot { margin-top: 22px; font-size: 9px; color: #6B7C75; text-align: center; }
</style></head>
<body>
    <table width="100%"><tr>
        <td><span class="brand">{{ $name }}</span><br><span class="muted">{{ config('afritopup.tagline') }}</span></td>
        <td style="text-align:right" class="muted">Receipt<br><b style="color:#0D1B16">{{ $t->reference }}</b></td>
    </tr></table>

    <div class="amount">{{ $m($t->total) }}</div>
    <span class="pill">{{ strtoupper($t->status_label) }}</span>

    @if($t->token && $t->status === Transaction::SUCCESSFUL)
        <div class="token"><span class="muted">{{ $t->category === 'electricity' ? 'Token' : 'PIN' }}</span><br><b>{!! nl2br(e($t->token)) !!}</b></div>
    @endif

    <table class="rows">
        <tr><td class="muted">Service</td><td class="v">{{ $what }}</td></tr>
        <tr><td class="muted">{{ $t->category === 'sms' ? 'Sent to' : 'Recipient' }}</td><td class="v">{{ $t->recipient }}</td></tr>
        @if($t->customer_name)<tr><td class="muted">Customer name</td><td class="v">{{ $t->customer_name }}</td></tr>@endif
        @if(!empty($t->meta['meter_type']))<tr><td class="muted">Meter type</td><td class="v">{{ ucfirst($t->meta['meter_type']) }}</td></tr>@endif
        @if($t->quantity > 1)<tr><td class="muted">Quantity</td><td class="v">{{ $t->quantity }}</td></tr>@endif
        <tr><td class="muted">Amount</td><td class="v">{{ $m($t->amount) }}</td></tr>
        @if((float) $t->discount > 0)<tr><td class="muted">Discount</td><td class="v">−{{ $m($t->discount) }}</td></tr>@endif
        @if((float) $t->fee > 0)<tr><td class="muted">Fee</td><td class="v">{{ $m($t->fee) }}</td></tr>@endif
        <tr><td class="muted">Paid from wallet</td><td class="v">{{ $m($t->total) }}</td></tr>
        <tr><td class="muted">Date</td><td class="v">{{ $t->created_at->format('j M Y, g:i A') }}</td></tr>
        <tr><td class="muted">Customer</td><td class="v">{{ $t->user?->name }}</td></tr>
    </table>

    <div class="foot">
        {{ config('afritopup.company') ?: $name }}{{ config('afritopup.support_email') ? ' · ' . config('afritopup.support_email') : '' }}{{ config('afritopup.support_phone') ? ' · ' . config('afritopup.support_phone') : '' }}<br>
        Generated {{ now()->format('j M Y, g:i A') }} · Powered &amp; developed by {{ config('afritopup.developer', 'Qudroid Systems') }}
    </div>
</body></html>
