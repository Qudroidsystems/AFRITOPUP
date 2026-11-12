{{--
    Transactional e-mail layout. Built for inbox placement:
    tables + inline styles (Gmail/Outlook), real text (no image-only content),
    a plain-text twin for every e-mail, the sender's company and address in the footer,
    and links only to our own domain. Keep copy plain — no "FREE!!!", no shorteners.
--}}
@php
    $name = $brand['name'] ?? config('afritopup.name', 'AfriTopup');
    $green = ($brand['primary_color'] ?? null) ?: '#0B8457';
    $gold = ($brand['accent_color'] ?? null) ?: '#F5A623';
    preg_match('/^(.[^A-Z]*)(.*)$/u', $name, $p);
@endphp
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <title>{{ $subject ?? $name }}</title>
</head>
<body style="margin:0;padding:0;background:#F3F6F5;-webkit-text-size-adjust:100%;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{{ $preheader ?? '' }}&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F3F6F5;">
    <tr><td align="center" style="padding:32px 14px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">
            <tr><td style="padding:0 6px 18px;font-family:Arial,Helvetica,sans-serif;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                    <td style="background:{{ $green }};border-radius:9px;width:32px;height:32px;text-align:center;color:#ffffff;font:bold 17px Arial,Helvetica,sans-serif;">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</td>
                    <td style="padding-left:9px;font:bold 20px Arial,Helvetica,sans-serif;letter-spacing:-0.3px;"><span style="color:{{ $green }};">{{ $p[1] ?? $name }}</span><span style="color:{{ $gold }};">{{ $p[2] ?? '' }}</span></td>
                </tr></table>
            </td></tr>
            <tr><td style="background:#ffffff;border-radius:18px;border:1px solid #E4ECE8;padding:34px 32px;font-family:Arial,Helvetica,sans-serif;color:#33443D;font-size:15px;line-height:1.6;">
                @yield('body')
            </td></tr>
            <tr><td style="padding:20px 10px 0;font-family:Arial,Helvetica,sans-serif;color:#6B7C75;font-size:12px;line-height:1.6;text-align:center;">
                {{ $footerNote ?? 'You are receiving this e-mail because of activity on your ' . $name . ' account.' }}<br>
                Need help? Reply to this e-mail or contact <a href="mailto:{{ $brand['support_email'] ?? config('mail.from.address') }}" style="color:{{ $green }};">{{ $brand['support_email'] ?? config('mail.from.address') }}</a>.<br>
                &copy; {{ now()->year }} {{ $brand['company'] ?? $name }}@if(!empty($brand['address'])) &middot; {{ $brand['address'] }}@endif
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
