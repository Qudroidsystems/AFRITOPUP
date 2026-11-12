{{ $subject }}

Hi {{ $firstName }}, here is the statement you asked for (PDF attached).

@foreach($lines as [$k, $v]){{ str_pad($k . ':', 18) }}{{ $v }}
@endforeach

--
{{ $brand['company'] ?? $brand['name'] ?? 'AfriTopup' }}
