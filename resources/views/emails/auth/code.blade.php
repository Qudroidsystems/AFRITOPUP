@extends('emails.layouts.brand')
@php $green = ($brand['primary_color'] ?? null) ?: '#0B8457'; @endphp
@section('body')
    <p style="margin:0 0 14px;font-size:20px;font-weight:bold;color:#0D1B16;">{{ $heading }}</p>
    <p style="margin:0 0 20px;">Hi {{ $firstName }},<br>{{ $intro }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 22px;">
        <tr><td align="center" style="background:#F4F8F6;border:1px dashed #BFD8CC;border-radius:14px;padding:18px 10px;">
            <div style="font:600 12px Arial,Helvetica,sans-serif;color:#6B7C75;letter-spacing:1px;text-transform:uppercase;margin-bottom:6px;">Your code</div>
            <div style="font:bold 34px 'Courier New',Courier,monospace;letter-spacing:10px;color:#0D1B16;">{{ $codeDisplay }}</div>
            <div style="font:12px Arial,Helvetica,sans-serif;color:#6B7C75;margin-top:6px;">Expires in {{ $minutes }} minutes</div>
        </td></tr>
    </table>

    @if($url)
        <p style="margin:0 0 10px;">{{ $buttonIntro }}</p>
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 22px;"><tr>
            <td style="border-radius:999px;background:{{ $green }};"><a href="{{ $url }}" style="display:inline-block;padding:13px 26px;font:bold 15px Arial,Helvetica,sans-serif;color:#ffffff;text-decoration:none;border-radius:999px;">{{ $button }}</a></td>
        </tr></table>
    @endif

    <p style="margin:0 0 6px;font-size:13px;color:#6B7C75;">{{ $safety }}</p>
    <p style="margin:0;font-size:13px;color:#6B7C75;">We will never ask for this code by phone, SMS or chat.</p>
    @if($url)
        <p style="margin:18px 0 0;font-size:12px;color:#94A3A0;word-break:break-all;">If the button doesn't work, copy this link into your browser:<br><a href="{{ $url }}" style="color:#6B7C75;">{{ $url }}</a></p>
    @endif
@endsection
