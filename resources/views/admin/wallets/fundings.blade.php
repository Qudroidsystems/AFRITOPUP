@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    @include('topup.partials.styles')
    <x-cb.hero title="Wallet Fundings" icon="ri-bank-card-line" subtitle="Money into wallets: Paystack top-ups and manual credits." />
    @include('partials.flash')
    <x-cb.card :flush="true">
        <form class="d-flex gap-2 flex-wrap p-3 border-bottom" method="GET">
            <input class="form-control" style="max-width:280px" name="q" value="{{ request('q') }}" placeholder="Reference or customer">
            <select class="form-select" style="max-width:170px" name="gateway"><option value="">Any method</option>@foreach(\App\Models\WalletFunding::GATEWAYS as $k => $l)<option value="{{ $k }}" @selected(request('gateway') === $k)>{{ $l }}</option>@endforeach</select>
            <select class="form-select" style="max-width:170px" name="status"><option value="">Any status</option>@foreach(['successful', 'pending', 'failed', 'abandoned'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach</select>
            <button class="btn btn-soft-primary">Filter</button>
        </form>
        @if($fundings->isEmpty())
            <div class="empty-state"><i class="ri-bank-card-line"></i><h6>No fundings yet</h6></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Customer</th><th>Reference</th><th class="text-end">Credited</th><th>Status</th><th>Date</th><th></th></tr></thead>
                <tbody>
                @foreach($fundings as $f)
                    <tr>
                        <td data-label="Customer"><a href="{{ route('admin.wallets.show', $f->user) }}" class="fw-semibold">{{ $f->user?->name }}</a><div class="small text-muted">{{ $f->user?->email }}</div></td>
                        <td data-label="Reference"><span class="at-mono">{{ $f->reference }}</span><div class="small text-muted">{{ $f->gateway_label }}{{ $f->creator ? ' by ' . $f->creator->name : '' }}{{ $f->note ? ' — ' . $f->note : '' }}</div></td>
                        <td data-label="Credited" class="num">{{ \App\Support\Money::format($f->amount, $f->currency) }}@if((float) $f->fee > 0)<div class="small text-muted">fee {{ \App\Support\Money::format($f->fee, $f->currency) }}</div>@endif</td>
                        <td data-label="Status"><span class="badge bg-{{ ['successful' => 'success', 'pending' => 'warning', 'failed' => 'danger'][$f->status] ?? 'secondary' }}-subtle text-{{ ['successful' => 'success', 'pending' => 'warning', 'failed' => 'danger'][$f->status] ?? 'secondary' }}">{{ ucfirst($f->status) }}</span></td>
                        <td data-label="Date" class="small text-nowrap">{{ $f->created_at->format('j M, g:ia') }}</td>
                        <td class="text-end">@if($f->status === 'pending' && $f->gateway === 'paystack')@can('Manage wallets')<form method="POST" action="{{ route('admin.fundings.recheck', $f) }}">@csrf<button class="btn btn-sm btn-light">Check Paystack</button></form>@endcan @endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="p-3">{{ $fundings->links() }}</div>
        @endif
    </x-cb.card>
</div></div></div>
@endsection
