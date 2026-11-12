@extends('emails.layouts.brand')
@section('body')
    <p style="margin:0 0 14px;font-size:20px;font-weight:bold;color:#0D1B16;">Your wallet statement</p>
    <p style="margin:0 0 16px;">Hi {{ $firstName }}, here is the statement you asked for. The full list of transactions is in the attached PDF.</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #E4ECE8;margin:0 0 16px;">
        @foreach($lines as [$k, $v])
            <tr><td style="padding:9px 0;border-bottom:1px solid #E4ECE8;color:#6B7C75;font-size:13px;">{{ $k }}</td><td style="padding:9px 0;border-bottom:1px solid #E4ECE8;color:#0D1B16;font-size:13px;font-weight:bold;text-align:right;">{{ $v }}</td></tr>
        @endforeach
    </table>
    <p style="margin:0;font-size:12px;color:#6B7C75;">Didn't ask for this? Someone may have access to your account — change your password and contact us.</p>
@endsection
