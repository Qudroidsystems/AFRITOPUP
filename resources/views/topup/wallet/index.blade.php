@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    @include('topup.partials.styles')
    @php $m = fn ($v) => \App\Support\Money::format($v, $wallet->currency); @endphp
    <x-cb.hero title="My Wallet" icon="ri-wallet-3-line" subtitle="Fund once, buy anything. Every naira in and out is listed below." />
    @include('partials.flash')

    @if($wallet->is_locked)
        <div class="cb-banner danger"><i class="ri-lock-line"></i><div>Your wallet is on hold, so purchases are paused. Please contact {{ $brand['support_email'] }}.</div></div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-xl-4">
            <div class="at-wallet mb-3">
                <div class="lbl">Available balance</div>
                <div class="amt">{{ $wallet->formatted_balance }}</div>
                <div class="d-flex gap-2 position-relative" style="z-index:1">
                    <a href="{{ route('topup.buy') }}" class="btn btn-light btn-sm"><i class="ri-shopping-bag-3-line me-1"></i>Buy something</a>
                    <a href="{{ route('topup.transactions') }}" class="btn btn-outline-light btn-sm">Purchases</a>
                </div>
            </div>

            <x-cb.card title="Fund wallet" icon="ri-add-circle-line" id="fund">
                <form method="POST" action="{{ route('topup.wallet.fund') }}" id="fundForm">
                    @csrf
                    <label class="form-label fw-semibold" for="fundAmount">Amount to add</label>
                    <div class="input-group input-group-lg mb-2"><span class="input-group-text">{{ \App\Support\Money::SYMBOLS[$wallet->currency] ?? $wallet->currency }}</span>
                        <input type="number" class="form-control" name="amount" id="fundAmount" min="{{ $cfg['min'] }}" max="{{ $cfg['max'] }}" step="1" value="{{ old('amount') }}" required></div>
                    <div class="d-flex gap-2 flex-wrap mb-2">
                        @foreach([1000, 2000, 5000, 10000, 20000] as $a)<button type="button" class="btn btn-sm btn-light" data-fund="{{ $a }}">{{ $m($a) }}</button>@endforeach
                    </div>
                    <div class="small text-muted mb-3" id="fundFee">
                        @if($cfg['fee_percent'] > 0)Card / transfer fee: {{ rtrim(rtrim(number_format($cfg['fee_percent'], 2), '0'), '.') }}%@if($cfg['fee_cap'] > 0) (at most {{ $m($cfg['fee_cap']) }})@endif.@else No funding fee.@endif
                    </div>
                    <button class="btn btn-success w-100"><i class="ri-bank-card-line me-1"></i>Pay with card, transfer or USSD</button>
                    <div class="small text-muted mt-2 text-center">Secured by Paystack. Your wallet is credited as soon as Paystack confirms.</div>
                </form>
            </x-cb.card>

            <x-cb.card :title="$hasPin ? 'Change transaction PIN' : 'Set your transaction PIN'" icon="ri-lock-password-line" id="pin">
                <p class="small text-muted">You enter this 4-digit PIN to confirm every purchase. Never share it.</p>
                <form method="POST" action="{{ route('topup.wallet.pin') }}" autocomplete="off">
                    @csrf
                    <div class="row g-2 mb-2">
                        <div class="col-6"><input class="form-control text-center" type="password" name="pin" inputmode="numeric" maxlength="4" pattern="\d{4}" placeholder="New PIN" required></div>
                        <div class="col-6"><input class="form-control text-center" type="password" name="pin_confirmation" inputmode="numeric" maxlength="4" pattern="\d{4}" placeholder="Repeat PIN" required></div>
                    </div>
                    <input class="form-control mb-2" type="password" name="password" placeholder="Your account password" autocomplete="current-password" required>
                    <button class="btn btn-soft-primary w-100">Save PIN</button>
                </form>
            </x-cb.card>
        </div>

        <div class="col-xl-8">
            @if($fundings->isNotEmpty())
                <x-cb.card title="Recent top-ups" icon="ri-bank-card-line" :flush="true">
                    <div class="table-responsive"><table class="cb-table stack"><tbody>
                        @foreach($fundings as $f)
                            <tr>
                                <td data-label="Top-up"><span class="fw-semibold">{{ $f->gateway_label }}</span><div class="small text-muted at-mono">{{ $f->reference }}</div></td>
                                <td data-label="Amount" class="num">{{ $m($f->amount) }}@if((float) $f->fee > 0)<div class="small text-muted">+ {{ $m($f->fee) }} fee</div>@endif</td>
                                <td data-label="Status"><span class="badge bg-{{ ['successful' => 'success', 'pending' => 'warning', 'failed' => 'danger'][$f->status] ?? 'secondary' }}-subtle text-{{ ['successful' => 'success', 'pending' => 'warning', 'failed' => 'danger'][$f->status] ?? 'secondary' }}">{{ ucfirst($f->status) }}</span>
                                    <div class="small text-muted">{{ $f->created_at->format('j M, g:ia') }}</div></td>
                            </tr>
                        @endforeach
                    </tbody></table></div>
                </x-cb.card>
            @endif

            <x-cb.card title="Statement" icon="ri-file-list-3-line" :flush="true">
                @if($entries->isEmpty())
                    <div class="empty-state"><i class="ri-file-list-3-line"></i><h6>Nothing yet</h6><p>Fund your wallet to get started.</p></div>
                @else
                    <div class="table-responsive"><table class="cb-table stack">
                        <thead><tr><th>Date</th><th>Description</th><th class="text-end">Amount</th><th class="text-end">Balance</th></tr></thead>
                        <tbody>
                        @foreach($entries as $e)
                            <tr>
                                <td data-label="Date" class="text-nowrap">{{ $e->created_at->format('j M Y') }}<div class="small text-muted">{{ $e->created_at->format('g:ia') }}</div></td>
                                <td data-label="Description">{{ $e->description }}<div class="small text-muted at-mono">{{ $e->reference }}</div></td>
                                <td data-label="Amount" class="num fw-semibold {{ $e->isCredit() ? 'text-success' : 'text-danger' }}">{{ $e->isCredit() ? '+' : '−' }}{{ $m($e->amount) }}</td>
                                <td data-label="Balance" class="num">{{ $m($e->balance_after) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                    <div class="p-3">{{ $entries->links() }}</div>
                @endif
            </x-cb.card>
        </div>
    </div>
</div></div></div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-fund]').forEach(b => b.addEventListener('click', () => { document.getElementById('fundAmount').value = b.dataset.fund; }));
</script>
@endpush
