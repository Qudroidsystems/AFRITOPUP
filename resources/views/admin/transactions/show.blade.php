@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    @include('topup.partials.styles')
    @php $m = fn ($v) => \App\Support\Money::format($v, $t->currency); $meta = collect((array) $t->meta)->except(['recipients']); @endphp
    <x-cb.hero :title="$t->reference" icon="ri-exchange-funds-line" :subtitle="$t->summary" :back="route('admin.transactions.index')" back-label="Transactions">
        <x-slot:pills>
            @include('topup.partials.status', ['t' => $t])
            <span class="cb-meta-pill">{{ strtoupper($t->channel) }}</span>
            <span class="cb-meta-pill">{{ $t->provider }}</span>
        </x-slot:pills>
    </x-cb.hero>
    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-7">
            <x-cb.card title="Purchase" icon="ri-shopping-bag-3-line">
                <div class="at-kv"><span>Customer</span><span><a href="{{ route('admin.wallets.show', $t->user) }}">{{ $t->user?->name }}</a> · {{ $t->user?->email }}</span></div>
                <div class="at-kv"><span>Service</span><span>{{ $t->service?->name }} ({{ $t->category_label }})</span></div>
                @if($t->product)<div class="at-kv"><span>Plan</span><span>{{ $t->product->name }} <span class="at-mono text-muted">{{ $t->product->code }}</span></span></div>@endif
                <div class="at-kv"><span>Recipient</span><span>{{ $t->recipient }}</span></div>
                @if($t->customer_name)<div class="at-kv"><span>Verified name</span><span>{{ $t->customer_name }}</span></div>@endif
                @if($t->phone)<div class="at-kv"><span>Contact phone</span><span>{{ $t->phone }}</span></div>@endif
                <div class="at-kv"><span>Quantity</span><span>{{ number_format($t->quantity) }}</span></div>
                <div class="at-kv"><span>Price / discount / fee</span><span>{{ $m($t->amount) }} / −{{ $m($t->discount) }} / {{ $m($t->fee) }}</span></div>
                <div class="at-kv"><span>Charged to wallet</span><span>{{ $m($t->total) }}</span></div>
                <div class="at-kv"><span>Provider cost</span><span>{{ $t->cost !== null ? $m($t->cost) : '—' }}</span></div>
                <div class="at-kv"><span>Provider reference</span><span class="at-mono">{{ $t->provider_reference ?: '—' }}</span></div>
                @if($t->client_reference)<div class="at-kv"><span>Reseller reference</span><span class="at-mono">{{ $t->client_reference }}</span></div>@endif
                <div class="at-kv"><span>Created / completed</span><span>{{ $t->created_at->format('j M Y g:i:s a') }} / {{ $t->completed_at?->format('j M Y g:i:s a') ?? '—' }}</span></div>
                <div class="at-kv"><span>Checks with provider</span><span>{{ $t->attempts }}{{ $t->last_checked_at ? ' · last ' . $t->last_checked_at->diffForHumans() : '' }}</span></div>
                @if($t->message)<div class="at-kv"><span>Message</span><span>{{ $t->message }}</span></div>@endif
                @foreach($meta as $k => $v)
                    <div class="at-kv"><span>{{ ucfirst(str_replace('_', ' ', $k)) }}</span><span>{{ is_scalar($v) ? $v : json_encode($v) }}</span></div>
                @endforeach
                @if($t->category === 'sms' && !empty($t->meta['recipients']))
                    <details class="mt-2"><summary class="small">{{ count($t->meta['recipients']) }} recipients</summary><div class="at-mono small mt-1">{{ implode(', ', array_slice($t->meta['recipients'], 0, 500)) }}{{ count($t->meta['recipients']) > 500 ? ' …' : '' }}</div></details>
                @endif
            </x-cb.card>
            @if($t->token)
                <x-cb.card title="Token / PINs" icon="ri-key-line"><div class="at-token">{{ $t->token }}</div></x-cb.card>
            @endif
            <x-cb.card title="Wallet entries" icon="ri-file-list-3-line" :flush="true">
                <div class="table-responsive"><table class="cb-table"><tbody>
                    @forelse($entries as $e)
                        <tr><td>{{ $e->created_at->format('j M g:i:sa') }}</td><td>{{ $e->description }}</td><td class="num {{ $e->isCredit() ? 'text-success' : 'text-danger' }}">{{ $e->isCredit() ? '+' : '−' }}{{ $m($e->amount) }}</td><td class="num text-muted">{{ $m($e->balance_after) }}</td></tr>
                    @empty
                        <tr><td class="text-muted p-3">No wallet movement.</td></tr>
                    @endforelse
                </tbody></table></div>
            </x-cb.card>
        </div>
        <div class="col-xl-5">
            @can('Manage transactions')
                <x-cb.card title="Actions" icon="ri-tools-line">
                    @if($t->isOpen())
                        <form method="POST" action="{{ route('admin.transactions.requery', $t) }}" class="mb-3">@csrf
                            <button class="btn btn-soft-primary w-100"><i class="ri-refresh-line me-1"></i>Check with {{ $t->provider }} now</button>
                        </form>
                        <form method="POST" action="{{ route('admin.transactions.deliver', $t) }}" class="mb-3 border rounded p-3">@csrf
                            <div class="fw-semibold mb-2">Mark as delivered</div>
                            <input class="form-control form-control-sm mb-2" name="note" placeholder="Note to the customer (optional)" maxlength="200">
                            <textarea class="form-control form-control-sm mb-2" name="token" rows="2" placeholder="Token / PIN, if any"></textarea>
                            <button class="btn btn-success btn-sm w-100" onclick="return confirm('Confirm this purchase was delivered?')">Mark delivered</button>
                        </form>
                    @endif
                    @if(in_array($t->status, ['pending', 'processing', 'successful'], true))
                        <form method="POST" action="{{ route('admin.transactions.refund', $t) }}" class="border rounded p-3 border-danger-subtle">@csrf
                            <div class="fw-semibold mb-2 text-danger">Refund {{ $m($t->total) }} to the wallet</div>
                            <input class="form-control form-control-sm mb-2" name="reason" placeholder="Reason (shown to the customer)" required maxlength="200">
                            <button class="btn btn-danger btn-sm w-100" onclick="return confirm('Refund this purchase? Only do this if the customer did not receive it.')">Refund</button>
                        </form>
                    @endif
                    @if($t->status === 'failed' || $t->status === 'refunded')
                        <p class="small text-muted mb-0">Closed. {{ $m($t->total) }} went back to the wallet {{ $t->refunded_at?->diffForHumans() }}.</p>
                    @endif
                </x-cb.card>
            @endcan
            <x-cb.card title="Provider response" icon="ri-code-s-slash-line">
                <pre class="small mb-0" style="white-space:pre-wrap">{{ json_encode($t->provider_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '—' }}</pre>
            </x-cb.card>
        </div>
    </div>
</div></div></div>
@endsection
