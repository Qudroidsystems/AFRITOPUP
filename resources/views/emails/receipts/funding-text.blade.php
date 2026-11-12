{{ $subject }}

Hi {{ $firstName }}, we've added {{ \App\Support\Money::format($f->amount, $f->currency) }} to your wallet.
New balance: {{ $f->wallet?->formatted_balance }}
Reference: {{ $f->reference }}
Date: {{ ($f->paid_at ?? $f->created_at)->format('j M Y, g:i A') }}

Open your wallet: {{ $url }}

--
{{ $brand['company'] ?? $brand['name'] ?? 'AfriTopup' }}
