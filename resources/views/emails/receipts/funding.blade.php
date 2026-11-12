@extends('emails.layouts.brand')
@php
    $green = ($brand['primary_color'] ?? null) ?: '#0B8457';
    $m = fn ($v) => \App\Support\Money::format($v, $f->currency);
    $how = \App\Models\WalletFunding::GATEWAYS[$f->gateway] ?? ucfirst($f->gateway);
@endphp
@section('body')
    <p style="margin:0 0 6px;"><span style="display:inline-block;padding:3px 10px;border-radius:999px;background:#E3F7EE;color:#08744B;font-size:12px;font-weight:bold;">Wallet funded</span></p>
    <p style="margin:8px 0 4px;font-size:28px;font-weight:bold;color:#0D1B16;">+{{ $m($f->amount) }}</p>
    <p style="margin:0 0 18px;">Hi {{ $firstName }}, we've added {{ $m($f->amount) }} to your wallet. Your balance is now <b>{{ $f->wallet?->formatted_balance }}</b>.</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #E4ECE8;margin:0 0 20px;">
        @foreach(array_filter([['Method', $how], (float) $f->fee > 0 ? ['Fee', $m($f->fee)] : null, (float) $f->fee > 0 ? ['You paid', $m($f->charged)] : null, ['Reference', $f->reference], ['Date', ($f->paid_at ?? $f->created_at)->format('j M Y, g:i A')]]) as [$k, $v])
            <tr><td style="padding:9px 0;border-bottom:1px solid #E4ECE8;color:#6B7C75;font-size:13px;">{{ $k }}</td><td style="padding:9px 0;border-bottom:1px solid #E4ECE8;color:#0D1B16;font-size:13px;font-weight:bold;text-align:right;">{{ $v }}</td></tr>
        @endforeach
    </table>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
        <td style="border-radius:999px;background:{{ $green }};"><a href="{{ $url }}" style="display:inline-block;padding:12px 24px;font:bold 14px Arial,Helvetica,sans-serif;color:#ffffff;text-decoration:none;border-radius:999px;">Open my wallet</a></td>
    </tr></table>
@endsection
