@php
    $m = fn ($v) => \App\Support\Money::format($v, $s['currency']);
    $name = config('afritopup.name', 'AfriTopup');
    $green = config('afritopup.primary_color') ?: '#0B8457';
    $tz = config('app.timezone');
    $u = $s['user'];
@endphp
<!doctype html>
<html><head><meta charset="utf-8"><title>Statement {{ $s['number'] }}</title>
<style>
    @page { margin: 34px 34px 50px; }
    body { font-family: DejaVu Sans, sans-serif; color: #0D1B16; font-size: 9.5px; }
    .brand { font-size: 20px; font-weight: bold; color: {{ $green }}; }
    .muted { color: #6B7C75; }
    h1 { font-size: 15px; margin: 0; }
    .box { border: 1px solid #E4ECE8; border-radius: 8px; padding: 10px 12px; }
    .sum td { width: 25%; padding: 8px 10px; border-right: 1px solid #E4ECE8; vertical-align: top; }
    .sum td:last-child { border-right: 0; }
    .sum b { font-size: 13px; display: block; margin-top: 3px; }
    table.tx { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.tx th { background: #F2F7F5; color: #4A5B54; font-size: 8.5px; text-transform: uppercase; letter-spacing: .5px; text-align: left; padding: 6px 6px; }
    table.tx td { padding: 6px 6px; border-bottom: 1px solid #EEF2F0; vertical-align: top; }
    table.tx .r { text-align: right; white-space: nowrap; }
    .in { color: #08744B; } .out { color: #B42318; }
    .foot { position: fixed; bottom: -32px; left: 0; right: 0; font-size: 8px; color: #6B7C75; text-align: center; }
    .page:after { content: counter(page); }
</style></head>
<body>
    <div class="foot">{{ config('afritopup.company') ?: $name }} · Statement {{ $s['number'] }} · Generated {{ now($tz)->format('j M Y, g:i A') }} · Page <span class="page"></span> · Powered &amp; developed by {{ config('afritopup.developer', 'Qudroid Systems') }}</div>

    <table width="100%"><tr>
        <td><span class="brand">{{ $name }}</span><br><span class="muted">{{ config('afritopup.tagline') }}</span></td>
        <td style="text-align:right"><h1>Wallet statement</h1><span class="muted">{{ $s['from']->format('j M Y') }} – {{ $s['to']->format('j M Y') }}</span></td>
    </tr></table>

    <table width="100%" style="margin-top:14px"><tr>
        <td class="box" style="width:55%"><span class="muted">Account holder</span><br><b style="font-size:11px">{{ $u->name }}</b><br>{{ $u->email }}{{ $u->phone_number ? ' · ' . $u->phone_number : '' }}<br><span class="muted">{{ $u->user_type_label }} · Wallet ({{ $s['currency'] }})</span></td>
        <td style="width:3%"></td>
        <td class="box"><span class="muted">Statement no.</span><br><b>{{ $s['number'] }}</b><br><span class="muted">Transactions: {{ $s['entries']->count() }}</span></td>
    </tr></table>

    <table width="100%" class="box sum" style="margin-top:12px; padding:0"><tr>
        <td><span class="muted">Opening balance</span><b>{{ $m($s['opening']) }}</b></td>
        <td><span class="muted">Money in</span><b class="in">+{{ $m($s['credits']) }}</b></td>
        <td><span class="muted">Money out</span><b class="out">−{{ $m($s['debits']) }}</b></td>
        <td><span class="muted">Closing balance</span><b>{{ $m($s['closing']) }}</b></td>
    </tr></table>

    @if($s['spend']->isNotEmpty())
        <p style="margin:14px 0 4px"><b>Spending by service</b></p>
        <table class="tx"><tr><th>Service</th><th class="r">Payments</th><th class="r">Total</th></tr>
            @foreach($s['spend'] as $row)<tr><td>{{ $row['label'] }}</td><td class="r">{{ $row['count'] }}</td><td class="r">{{ $m($row['total']) }}</td></tr>@endforeach
        </table>
    @endif

    <p style="margin:14px 0 4px"><b>Transactions</b></p>
    <table class="tx">
        <thead><tr><th style="width:15%">Date</th><th>Description</th><th style="width:19%">Reference</th><th class="r" style="width:12%">Money in</th><th class="r" style="width:12%">Money out</th><th class="r" style="width:13%">Balance</th></tr></thead>
        <tbody>
            <tr><td class="muted">{{ $s['from']->format('j M Y') }}</td><td class="muted">Opening balance</td><td></td><td></td><td></td><td class="r"><b>{{ $m($s['opening']) }}</b></td></tr>
            @forelse($s['entries'] as $e)
                <tr>
                    <td>{{ $e->created_at->timezone($tz)->format('j M Y') }}<br><span class="muted">{{ $e->created_at->timezone($tz)->format('g:i A') }}</span></td>
                    <td>{{ $e->description }}</td>
                    <td class="muted" style="font-size:8px">{{ $e->reference }}</td>
                    <td class="r in">{{ $e->type === 'credit' ? $m($e->amount) : '' }}</td>
                    <td class="r out">{{ $e->type === 'debit' ? $m($e->amount) : '' }}</td>
                    <td class="r">{{ $m($e->balance_after) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted" style="text-align:center;padding:16px">No wallet activity in this period.</td></tr>
            @endforelse
            <tr><td></td><td><b>Closing balance</b></td><td></td><td class="r in"><b>{{ $m($s['credits']) }}</b></td><td class="r out"><b>{{ $m($s['debits']) }}</b></td><td class="r"><b>{{ $m($s['closing']) }}</b></td></tr>
        </tbody>
    </table>
    @if($s['truncated'])<p class="muted">Only the first {{ number_format(\App\Services\Statements\StatementService::MAX_ROWS) }} entries are shown. Choose a shorter period for the rest.</p>@endif
</body></html>
