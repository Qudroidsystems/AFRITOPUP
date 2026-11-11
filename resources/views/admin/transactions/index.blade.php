@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    @include('topup.partials.styles')
    @php $m = fn ($v) => \App\Support\Money::format($v); @endphp
    <x-cb.hero title="Transactions" icon="ri-exchange-funds-line" subtitle="Every purchase on the platform. Re-check stuck ones with the provider, refund, or confirm manual deliveries.">
        <x-slot:actions><a href="{{ route('admin.transactions.export', request()->query()) }}" class="cb-hero-btn"><i class="ri-download-2-line"></i>Export CSV</a></x-slot:actions>
    </x-cb.hero>
    @include('partials.flash')

    @if($stuck)
        <div class="cb-banner warning"><i class="ri-time-line"></i><div><a href="{{ route('admin.transactions.index', ['status' => 'processing']) }}">{{ $stuck }} processing for over 15 minutes.</a> They are re-checked automatically every 5 minutes; you can also check one now.</div></div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-4"><x-cb.stat label="Matching purchases" :value="number_format($totals->n ?? 0)" icon="ri-list-check" accent="sky" /></div>
        <div class="col-md-4"><x-cb.stat label="Successful sales" :value="$m($totals->sales ?? 0)" icon="ri-shopping-bag-3-line" accent="green" /></div>
        <div class="col-md-4"><x-cb.stat label="Margin (sales − provider cost)" :value="$m($totals->margin ?? 0)" icon="ri-line-chart-line" accent="teal" /></div>
    </div>

    <x-cb.card :flush="true">
        <form class="row g-2 p-3 border-bottom" method="GET">
            <div class="col-lg-3"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Reference, number, customer"></div>
            <div class="col-lg-2"><select class="form-select" name="category"><option value="">All services</option>@foreach(config('afritopup.categories') as $k => $c)<option value="{{ $k }}" @selected(request('category') === $k)>{{ $c['name'] }}</option>@endforeach</select></div>
            <div class="col-lg-2"><select class="form-select" name="status"><option value="">Any status</option>@foreach(\App\Models\Transaction::STATUSES as $k => [$l])<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="col-lg-1"><select class="form-select" name="channel"><option value="">Channel</option>@foreach(['web', 'app', 'api'] as $c)<option value="{{ $c }}" @selected(request('channel') === $c)>{{ strtoupper($c) }}</option>@endforeach</select></div>
            <div class="col-lg-3 d-flex gap-2"><input type="date" class="form-control" name="from" value="{{ request('from') }}"><input type="date" class="form-control" name="to" value="{{ request('to') }}"></div>
            <div class="col-lg-1"><button class="btn btn-soft-primary w-100">Filter</button></div>
        </form>
        @if($transactions->isEmpty())
            <div class="empty-state"><i class="ri-exchange-funds-line"></i><h6>No transactions found</h6></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Purchase</th><th>Customer</th><th class="text-end">Total</th><th>Status</th><th>Provider</th><th>Date</th></tr></thead>
                <tbody>
                @foreach($transactions as $t)
                    <tr>
                        <td data-label="Purchase"><div class="d-flex align-items-center gap-2">@include('topup.partials.service-icon', ['s' => $t->service, 'size' => 32])
                            <div><a href="{{ route('admin.transactions.show', $t) }}" class="fw-semibold">{{ $t->product?->name ?? ($t->service?->display_name . ' ' . $t->category_label) }}</a>
                                <div class="small text-muted">{{ $t->recipient }} · <span class="at-mono">{{ $t->reference }}</span></div></div></div></td>
                        <td data-label="Customer">{{ $t->user?->name }}<div class="small text-muted">{{ $t->user?->email }}</div></td>
                        <td data-label="Total" class="num">{{ \App\Support\Money::format($t->total, $t->currency) }}@if((float) $t->discount > 0)<div class="small text-success">−{{ \App\Support\Money::format($t->discount, $t->currency) }}</div>@endif</td>
                        <td data-label="Status">@include('topup.partials.status', ['t' => $t])</td>
                        <td data-label="Provider" class="small">{{ $t->provider }}<div class="text-muted">{{ strtoupper($t->channel) }}</div></td>
                        <td data-label="Date" class="small text-nowrap">{{ $t->created_at->format('j M, g:ia') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="p-3">{{ $transactions->links() }}</div>
        @endif
    </x-cb.card>
</div></div></div>
@endsection
