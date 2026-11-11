@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    @include('topup.partials.styles')
    @php $m = fn ($v) => \App\Support\Money::format($v, $wallet->currency); @endphp
    <x-cb.hero :title="$u->name" icon="ri-wallet-3-line" :subtitle="$u->email . ($u->phone_number ? ' · ' . $u->phone_number : '')" :back="route('admin.wallets.index')" back-label="Wallets">
        <x-slot:pills>
            <span class="cb-meta-pill">{{ $u->user_type_label }}</span>
            <span class="cb-meta-pill">{{ $u->priceTier?->name ?? 'Default' }} tier</span>
            @if($wallet->is_locked)<span class="cb-meta-pill bg-danger text-white">On hold</span>@endif
        </x-slot:pills>
        <x-slot:actions>@can('View user')<a href="{{ route('users.overview', $u->id) }}" class="cb-hero-btn"><i class="ri-user-line"></i>Account</a>@endcan</x-slot:actions>
    </x-cb.hero>
    @include('partials.flash')
    <div class="row g-3">
        <div class="col-xl-4">
            <div class="at-wallet mb-3"><div class="lbl">Balance</div><div class="amt">{{ $wallet->formatted_balance }}</div></div>
            @can('Update user')
                @unless($u->isStaff())
                <x-cb.card title="Account type & prices" icon="ri-price-tag-3-line">
                    <form method="POST" action="{{ route('admin.wallets.account', $u) }}">@csrf
                        <select class="form-select mb-2" name="user_type">@foreach(['customer' => 'Customer', 'agent' => 'Agent', 'reseller' => 'Reseller (API access)'] as $k => $l)<option value="{{ $k }}" @selected($u->user_type === $k)>{{ $l }}</option>@endforeach</select>
                        <select class="form-select mb-2" name="price_tier_id"><option value="">Default tier</option>@foreach($tiers as $t)<option value="{{ $t->id }}" @selected($u->price_tier_id === $t->id)>{{ $t->name }}</option>@endforeach</select>
                        <button class="btn btn-soft-primary w-100">Save</button>
                    </form>
                </x-cb.card>
                @endunless
            @endcan
            @can('Manage wallets')
                <x-cb.card title="Credit or debit" icon="ri-scales-3-line">
                    <form method="POST" action="{{ route('admin.wallets.adjust', $u) }}">@csrf
                        <div class="at-pick mb-2">
                            <input type="radio" name="type" id="tc" value="credit" checked><label for="tc">Credit (+)</label>
                            <input type="radio" name="type" id="td" value="debit"><label for="td">Debit (−)</label>
                        </div>
                        <input class="form-control mb-2" type="number" name="amount" min="1" step="0.01" placeholder="Amount" required>
                        <input class="form-control mb-2" name="note" maxlength="200" placeholder="Reason (e.g. bank deposit 11 Nov, ref …)" required>
                        <button class="btn btn-primary w-100" onclick="return confirm('Record this adjustment? It is logged with your name.')">Record</button>
                    </form>
                </x-cb.card>
                <form method="POST" action="{{ route('admin.wallets.lock', $u) }}">@csrf
                    <button class="btn {{ $wallet->is_locked ? 'btn-soft-success' : 'btn-soft-danger' }} w-100">{{ $wallet->is_locked ? 'Release the hold' : 'Put wallet on hold' }}</button>
                </form>
            @endcan
        </div>
        <div class="col-xl-8">
            <x-cb.card title="Statement" icon="ri-file-list-3-line" :flush="true">
                <div class="table-responsive"><table class="cb-table stack">
                    <thead><tr><th>Date</th><th>Description</th><th class="text-end">Amount</th><th class="text-end">Balance</th></tr></thead>
                    <tbody>
                    @forelse($entries as $e)
                        <tr>
                            <td data-label="Date" class="text-nowrap small">{{ $e->created_at->format('j M Y, g:ia') }}</td>
                            <td data-label="Description">{{ $e->description }}<div class="small text-muted"><span class="at-mono">{{ $e->reference }}</span>{{ $e->creator ? ' · by ' . $e->creator->name : '' }}</div></td>
                            <td data-label="Amount" class="num fw-semibold {{ $e->isCredit() ? 'text-success' : 'text-danger' }}">{{ $e->isCredit() ? '+' : '−' }}{{ $m($e->amount) }}</td>
                            <td data-label="Balance" class="num">{{ $m($e->balance_after) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty-state"><i class="ri-file-list-3-line"></i><h6>No movement yet</h6></div></td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                <div class="p-3">{{ $entries->links() }}</div>
            </x-cb.card>
        </div>
    </div>
</div></div></div>
@endsection
