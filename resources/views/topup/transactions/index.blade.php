@extends($shell ?? 'layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    @include('topup.partials.styles')
    <x-cb.hero title="My Transactions" icon="ri-file-list-3-line" subtitle="Every purchase you have made, with receipts, tokens and PINs." />
    @include('partials.flash')

    <x-cb.card :flush="true">
        <form class="d-flex gap-2 flex-wrap p-3 border-bottom" method="GET">
            <input class="form-control" style="max-width:260px" name="q" value="{{ request('q') }}" placeholder="Reference or number">
            <select class="form-select" style="max-width:180px" name="category"><option value="">All services</option>
                @foreach(config('afritopup.categories') as $k => $c)<option value="{{ $k }}" @selected(request('category') === $k)>{{ $c['name'] }}</option>@endforeach</select>
            <select class="form-select" style="max-width:160px" name="status"><option value="">Any status</option>
                @foreach(\App\Models\Transaction::STATUSES as $k => [$l])<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select>
            <button class="btn btn-soft-primary">Filter</button>
        </form>
        @if($transactions->isEmpty())
            <div class="empty-state"><i class="ri-shopping-bag-3-line"></i><h6>No purchases found</h6></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Purchase</th><th>Date</th><th class="text-end">Paid</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($transactions as $t)
                    <tr>
                        <td data-label="Purchase"><div class="d-flex align-items-center gap-2">@include('topup.partials.service-icon', ['s' => $t->service, 'size' => 34])
                            <div><a href="{{ route('topup.transactions.show', $t->reference) }}" class="fw-semibold">{{ $t->product?->name ?? ($t->service?->display_name . ' ' . $t->category_label) }}</a>
                                <div class="small text-muted">{{ $t->recipient }}</div></div></div></td>
                        <td data-label="Date" class="text-nowrap">{{ $t->created_at->format('j M Y, g:ia') }}</td>
                        <td data-label="Paid" class="num">{{ \App\Support\Money::format($t->total, $t->currency) }}</td>
                        <td data-label="Status">@include('topup.partials.status', ['t' => $t])</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="p-3">{{ $transactions->links() }}</div>
        @endif
    </x-cb.card>
</div></div></div>
@endsection
