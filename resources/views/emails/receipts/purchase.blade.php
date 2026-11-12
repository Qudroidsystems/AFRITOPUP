@extends('emails.layouts.brand')
@php
    use App\Models\Transaction; use App\Support\Money;
    $green = ($brand['primary_color'] ?? null) ?: '#0B8457';
    $m = fn ($v) => Money::format($v, $t->currency);
    [$badgeBg, $badgeFg, $badge] = match ($t->status) {
        Transaction::SUCCESSFUL => ['#E3F7EE', '#08744B', 'Successful'],
        Transaction::REFUNDED   => ['#EAF2FF', '#1D4ED8', 'Refunded'],
        default                 => ['#FDE8E8', '#B42318', 'Failed'],
    };
    $rows = array_filter([
        ['Service', $what],
        [$t->category === 'sms' ? 'Sent to' : 'Recipient', $t->recipient],
        $t->customer_name ? ['Customer name', $t->customer_name] : null,
        !empty($t->meta['meter_type']) ? ['Meter type', ucfirst($t->meta['meter_type'])] : null,
        ['Amount', $m($t->amount)],
        (float) $t->discount > 0 ? ['Discount', '−' . $m($t->discount)] : null,
        (float) $t->fee > 0 ? ['Fee', $m($t->fee)] : null,
        ['Paid from wallet', $m($t->total)],
        ['Reference', $t->reference],
        ['Date', $t->created_at->timezone(config('app.timezone'))->format('j M Y, g:i A')],
    ]);
@endphp
@section('body')
    <p style="margin:0 0 6px;"><span style="display:inline-block;padding:3px 10px;border-radius:999px;background:{{ $badgeBg }};color:{{ $badgeFg }};font-size:12px;font-weight:bold;">{{ $badge }}</span></p>
    <p style="margin:8px 0 4px;font-size:28px;font-weight:bold;color:#0D1B16;">{{ $m($t->total) }}</p>
    <p style="margin:0 0 18px;">Hi {{ $firstName }},
        @if($t->status === Transaction::SUCCESSFUL) your {{ strtolower($t->category_label) }} payment was successful.
        @elseif($t->status === Transaction::REFUNDED) your payment didn't go through, so we've returned {{ $m($t->total) }} to your wallet.
        @else your payment didn't go through. If any money left your wallet it will be returned automatically. @endif
    </p>

    @if($t->token && $t->status === Transaction::SUCCESSFUL)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 18px;"><tr>
            <td style="background:#FFF8E6;border:1px dashed #F3D58B;border-radius:14px;padding:14px 16px;">
                <div style="font:bold 12px Arial,Helvetica,sans-serif;color:#7A5200;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">{{ $t->category === 'electricity' ? 'Your token' : 'Your PIN' }}</div>
                <div style="font:bold 20px 'Courier New',Courier,monospace;letter-spacing:2px;color:#0D1B16;word-break:break-all;">{!! nl2br(e($t->token)) !!}</div>
            </td></tr></table>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #E4ECE8;margin:0 0 20px;">
        @foreach($rows as [$k, $v])
            <tr>
                <td style="padding:9px 0;border-bottom:1px solid #E4ECE8;color:#6B7C75;font-size:13px;">{{ $k }}</td>
                <td style="padding:9px 0;border-bottom:1px solid #E4ECE8;color:#0D1B16;font-size:13px;font-weight:bold;text-align:right;">{{ $v }}</td>
            </tr>
        @endforeach
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px;"><tr>
        <td style="border-radius:999px;background:{{ $green }};"><a href="{{ $url }}" style="display:inline-block;padding:12px 24px;font:bold 14px Arial,Helvetica,sans-serif;color:#ffffff;text-decoration:none;border-radius:999px;">View receipt</a></td>
    </tr></table>
    @if($t->status === Transaction::SUCCESSFUL)<p style="margin:0;font-size:12px;color:#6B7C75;">A PDF copy of this receipt is attached.</p>@endif
    <p style="margin:6px 0 0;font-size:12px;color:#6B7C75;">Something wrong? Reply to this e-mail or open the receipt and tap “Report a problem”.</p>
@endsection
