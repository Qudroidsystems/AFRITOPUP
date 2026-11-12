{{ $heading }}

Hi {{ $firstName }},
{{ $intro }}

Your code: {{ $codeDisplay }}
(expires in {{ $minutes }} minutes)
@if($url)

{{ $buttonIntro }}
{{ $url }}
@endif

{{ $safety }}
We will never ask for this code by phone, SMS or chat.

--
{{ $brand['company'] ?? $brand['name'] ?? 'AfriTopup' }}
Need help? Reply to this e-mail or contact {{ $brand['support_email'] ?? config('mail.from.address') }}
