@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Wallet balances" icon="ri-wallet-3-line" subtitle="What customers hold on the platform — the float you must keep with your providers." />
    @include('partials.flash')
    <div class="row g-3 mb-3">
        @foreach($float as $f)
            <div class="col-md-4"><x-cb.stat :label="'Total in ' . $f->currency . ' wallets'" :value="\App\Support\Money::format($f->total, $f->currency)" icon="ri-safe-2-line" accent="amber" :hint="number_format($f->n) . ' wallets'" /></div>
        @endforeach
    </div>
    <x-cb.card :flush="true">
        <form class="d-flex gap-2 p-3 border-bottom" method="GET"><input class="form-control" style="max-width:320px" name="q" value="{{ request('q') }}" placeholder="Name, e-mail or phone"><button class="btn btn-soft-primary">Search</button></form>
        <div class="table-responsive"><table class="cb-table stack">
            <thead><tr><th>Customer</th><th>Type</th><th class="text-end">Balance</th><th></th></tr></thead>
            <tbody>
            @forelse($wallets as $w)
                <tr>
                    <td data-label="Customer"><a href="{{ route('admin.wallets.show', $w->user) }}" class="fw-semibold">{{ $w->user?->name }}</a><div class="small text-muted">{{ $w->user?->email }}</div></td>
                    <td data-label="Type">{{ $w->user?->user_type_label }}@if($w->is_locked) <span class="badge bg-danger-subtle text-danger">On hold</span>@endif</td>
                    <td data-label="Balance" class="num fw-semibold">{{ $w->formatted_balance }}</td>
                    <td class="text-end"><a href="{{ route('admin.wallets.show', $w->user) }}" class="btn btn-sm btn-light">Statement</a></td>
                </tr>
            @empty
                <tr><td colspan="4"><div class="empty-state"><i class="ri-wallet-3-line"></i><h6>No wallets</h6></div></td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="p-3">{{ $wallets->links() }}</div>
    </x-cb.card>
</div></div></div>
@endsection
