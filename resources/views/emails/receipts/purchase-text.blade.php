@php use App\Models\Transaction; $m = fn ($v) => \App\Support\Money::format($v, $t->currency); @endphp
{{ $subject }}

Hi {{ $firstName }},
@if($t->status === Transaction::SUCCESSFUL)
Your {{ strtolower($t->category_label) }} payment was successful.
@elseif($t->status === Transaction::REFUNDED)
Your payment didn't go through, so we've returned {{ $m($t->total) }} to your wallet.
@else
Your payment didn't go through. Any money taken will be returned automatically.
@endif
@if($t->token && $t->status === Transaction::SUCCESSFUL)

{{ $t->category === 'electricity' ? 'Your token' : 'Your PIN' }}: {{ $t->token }}
@endif

Service:    {{ $what }}
Recipient:  {{ $t->recipient }}
@if($t->customer_name)Customer:   {{ $t->customer_name }}
@endif
Amount:     {{ $m($t->amount) }}
Paid:       {{ $m($t->total) }}
Reference:  {{ $t->reference }}
Date:       {{ $t->created_at->format('j M Y, g:i A') }}

View receipt: {{ $url }}

--
{{ $brand['company'] ?? $brand['name'] ?? 'AfriTopup' }} · Reply to this e-mail if something is wrong.
