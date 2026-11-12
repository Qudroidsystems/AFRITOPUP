@php
    $m = fn ($v) => \App\Support\Money::format($v, $f->currency);
    $name = config('afritopup.name', 'AfriTopup');
    $green = config('afritopup.primary_color') ?: '#0B8457';
@endphp
<!doctype html>
<html><head><meta charset="utf-8"><title>Receipt {{ $f->reference }}</title>
<style>
    @page { margin: 28px 30px; } body { font-family: DejaVu Sans, sans-serif; color: #0D1B16; font-size: 11px; }
    .brand { font-size: 18px; font-weight: bold; color: {{ $green }}; } .muted { color: #6B7C75; }
    .amount { font-size: 26px; font-weight: bold; margin: 14px 0 4px; color: #08744B; }
    table.rows { width: 100%; border-collapse: collapse; margin-top: 14px; } table.rows td { padding: 7px 0; border-bottom: 1px solid #E4ECE8; } table.rows td.v { text-align: right; font-weight: bold; }
    .foot { margin-top: 22px; font-size: 9px; color: #6B7C75; text-align: center; }
</style></head>
<body>
    <table width="100%"><tr>
        <td><span class="brand">{{ $name }}</span><br><span class="muted">Wallet funding receipt</span></td>
        <td style="text-align:right" class="muted">Reference<br><b style="color:#0D1B16">{{ $f->reference }}</b></td>
    </tr></table>
    <div class="amount">+{{ $m($f->amount) }}</div>
    <table class="rows">
        <tr><td class="muted">Method</td><td class="v">{{ \App\Models\WalletFunding::GATEWAYS[$f->gateway] ?? ucfirst($f->gateway) }}</td></tr>
        @if((float) $f->fee > 0)<tr><td class="muted">Fee</td><td class="v">{{ $m($f->fee) }}</td></tr><tr><td class="muted">You paid</td><td class="v">{{ $m($f->charged) }}</td></tr>@endif
        <tr><td class="muted">Credited to wallet</td><td class="v">{{ $m($f->amount) }}</td></tr>
        <tr><td class="muted">Date</td><td class="v">{{ ($f->paid_at ?? $f->created_at)->format('j M Y, g:i A') }}</td></tr>
        <tr><td class="muted">Customer</td><td class="v">{{ $f->user?->name }}</td></tr>
    </table>
    <div class="foot">{{ config('afritopup.company') ?: $name }} · Generated {{ now()->format('j M Y, g:i A') }} · Powered &amp; developed by {{ config('afritopup.developer', 'Qudroid Systems') }}</div>
</body></html>
