@extends($shell ?? 'layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    @include('topup.partials.styles')
    @php $m = fn ($v) => \App\Support\Money::format($v, $t->currency); $meta = (array) $t->meta; @endphp
    @include('partials.flash')

    <div class="at-receipt">
        <x-cb.card>
            <div class="text-center mb-3">
                <div class="mb-2">@include('topup.partials.service-icon', ['s' => $t->service, 'size' => 56])</div>
                <div class="text-muted small">{{ $t->service?->name }}</div>
                <div class="big">{{ $m($t->total) }}</div>
                <div class="mt-1">@include('topup.partials.status', ['t' => $t])</div>
                @if($t->message)<div class="small text-muted mt-2">{{ $t->message }}</div>@endif
            </div>

            @if($t->token)
                <div class="mb-3">
                    <div class="small fw-semibold mb-1">{{ $t->category === 'electricity' ? 'Your token' : 'Your PIN(s)' }}</div>
                    <div class="at-token" id="tok">{{ $t->token }}</div>
                    <button class="btn btn-sm btn-light mt-2" type="button" onclick="navigator.clipboard.writeText(document.getElementById('tok').innerText); this.textContent='Copied'"><i class="ri-file-copy-line me-1"></i>Copy</button>
                </div>
            @endif

            <div class="at-kv"><span>{{ $t->category === 'sms' ? 'Sent to' : ($t->service?->recipientLabel() ?? 'Recipient') }}</span><span>{{ $t->recipient }}</span></div>
            @if($t->customer_name)<div class="at-kv"><span>Customer name</span><span>{{ $t->customer_name }}</span></div>@endif
            @if($t->product)<div class="at-kv"><span>Plan</span><span>{{ $t->product->name }}</span></div>@endif
            @if(!empty($meta['meter_type']))<div class="at-kv"><span>Meter type</span><span>{{ ucfirst($meta['meter_type']) }}</span></div>@endif
            @if(!empty($meta['school']))
                <div class="at-kv"><span>School</span><span>{{ $meta['school'] }}</span></div>
                @if(!empty($meta['student_ref']))<div class="at-kv"><span>Student number</span><span>{{ $meta['student_ref'] }}</span></div>@endif
                @if(!empty($meta['session']))<div class="at-kv"><span>Session</span><span>{{ $meta['session'] }}</span></div>@endif
                <div class="at-kv"><span>For</span><span>{{ $meta['fee_item'] ?? 'School fees' }}</span></div>
            @endif
            @if($t->category === 'sms')
                <div class="at-kv"><span>Sender</span><span>{{ $meta['sender'] ?? '' }}</span></div>
                <div class="at-kv"><span>Pages × recipients</span><span>{{ $meta['pages'] ?? 1 }} × {{ number_format($t->quantity) }}</span></div>
            @elseif($t->quantity > 1)
                <div class="at-kv"><span>Quantity</span><span>{{ $t->quantity }}</span></div>
            @endif
            <div class="at-kv"><span>Price</span><span>{{ $m($t->amount) }}</span></div>
            @if((float) $t->discount > 0)<div class="at-kv"><span>Discount</span><span class="text-success">−{{ $m($t->discount) }}</span></div>@endif
            @if((float) $t->fee > 0)<div class="at-kv"><span>Fee</span><span>{{ $m($t->fee) }}</span></div>@endif
            <div class="at-kv"><span>Paid from wallet</span><span>{{ $m($t->total) }}</span></div>
            <div class="at-kv"><span>Reference</span><span class="at-mono">{{ $t->reference }}</span></div>
            <div class="at-kv"><span>Date</span><span>{{ $t->created_at->format('j M Y, g:i a') }}</span></div>

            <div class="d-flex gap-2 mt-3 flex-wrap justify-content-center d-print-none">
                <button class="btn btn-light" onclick="window.print()"><i class="ri-printer-line me-1"></i>Print</button>
                <a class="btn btn-success" href="{{ route('topup.buy.form', ['category' => $t->category, 'service' => $t->service?->code]) }}"><i class="ri-repeat-line me-1"></i>Buy again</a>
                @if(auth()->id() === $t->user_id)<a class="btn btn-light" href="{{ route('support.index', ['transaction' => $t->reference]) }}"><i class="ri-chat-smile-3-line me-1"></i>Report a problem</a>@endif
                @can('View transactions')<a class="btn btn-soft-primary" href="{{ route('admin.transactions.show', $t) }}">Admin view</a>@endcan
            </div>
        </x-cb.card>
        @if($t->isOpen())
            <p class="text-center small text-muted">This page does not refresh by itself. We will notify you as soon as the provider confirms.</p>
        @endif
    </div>
</div></div></div>
@endsection
