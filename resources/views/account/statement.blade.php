@extends('layouts.account')

@php
    $m = fn ($v) => \App\Support\Money::format($v, $s['currency']);
    $tz = config('app.timezone');
    $q = ['from' => $s['from']->format('Y-m-d'), 'to' => $s['to']->format('Y-m-d')];
@endphp

@section('content')
<div class="a-page">
    <div class="a-hello">
        <div><h1>Statement</h1><p>Every credit and debit on your wallet, with the running balance.</p></div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('account.statement.download', $q + ['format' => 'pdf']) }}" class="s-btn"><i class="ri-file-pdf-2-line"></i> PDF</a>
            <a href="{{ route('account.statement.download', $q + ['format' => 'csv']) }}" class="s-btn s-btn--ghost"><i class="ri-file-excel-2-line"></i> Excel (CSV)</a>
            <form method="POST" action="{{ route('account.statement.email') }}">@csrf
                <input type="hidden" name="from" value="{{ $q['from'] }}"><input type="hidden" name="to" value="{{ $q['to'] }}">
                <button class="s-btn s-btn--ghost" type="submit"><i class="ri-mail-send-line"></i> E-mail me</button>
            </form>
        </div>
    </div>
    @include('partials.flash')

    <div class="a-card mb-4">
        <form method="GET" class="row g-2 align-items-end a-form">
            <div class="col-12 d-flex flex-wrap gap-2 mb-1">
                @foreach($presets as $key => [$label, $pf, $pt])
                    @php $on = $pf->format('Y-m-d') === $q['from'] && $pt->format('Y-m-d') === $q['to']; @endphp
                    <a href="?from={{ $pf->format('Y-m-d') }}&to={{ $pt->format('Y-m-d') }}" class="btn btn-sm rounded-pill {{ $on ? 'btn-dark' : 'btn-light' }}">{{ $label }}</a>
                @endforeach
            </div>
            <div class="col-sm-4"><label for="sFrom">From</label><input id="sFrom" type="date" name="from" class="form-control" value="{{ $q['from'] }}" max="{{ now($tz)->format('Y-m-d') }}"></div>
            <div class="col-sm-4"><label for="sTo">To</label><input id="sTo" type="date" name="to" class="form-control" value="{{ $q['to'] }}" max="{{ now($tz)->format('Y-m-d') }}"></div>
            <div class="col-sm-4"><button class="s-btn w-100" type="submit">Show statement</button></div>
        </form>
    </div>

    <div class="row g-3 mb-4">
        @foreach([['Opening balance', $m($s['opening']), 'ri-flag-line', '#64748B'], ['Money in', '+' . $m($s['credits']), 'ri-arrow-down-circle-line', '#0B8457'], ['Money out', '−' . $m($s['debits']), 'ri-arrow-up-circle-line', '#E5484D'], ['Closing balance', $m($s['closing']), 'ri-wallet-3-line', '#F59E0B']] as [$label, $value, $icon, $c])
            <div class="col-6 col-lg-3"><div class="a-card h-100"><div class="a-stat"><span class="s-ico" style="--c: {{ $c }}"><i class="{{ $icon }}"></i></span><div><small>{{ $label }}</small><b>{{ $value }}</b></div></div></div></div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="a-card">
                <div class="a-card-head"><h2>{{ $s['from']->format('j M Y') }} – {{ $s['to']->format('j M Y') }}</h2><span class="small" style="color: var(--s-muted)">{{ $s['entries']->count() }} entries</span></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" style="font-size:.88rem">
                        <thead><tr class="text-muted" style="font-size:.75rem;text-transform:uppercase"><th>Date</th><th>Description</th><th class="text-end">In</th><th class="text-end">Out</th><th class="text-end">Balance</th></tr></thead>
                        <tbody>
                            <tr class="text-muted"><td>{{ $s['from']->format('j M') }}</td><td>Opening balance</td><td></td><td></td><td class="text-end fw-semibold">{{ $m($s['opening']) }}</td></tr>
                            @forelse($s['entries']->reverse()->take(300)->reverse() as $e)
                                <tr>
                                    <td class="text-nowrap">{{ $e->created_at->timezone($tz)->format('j M') }}<br><small class="text-muted">{{ $e->created_at->timezone($tz)->format('g:ia') }}</small></td>
                                    <td>{{ $e->description }}<br><small class="text-muted">{{ $e->reference }}</small></td>
                                    <td class="text-end text-success text-nowrap">{{ $e->type === 'credit' ? $m($e->amount) : '' }}</td>
                                    <td class="text-end text-danger text-nowrap">{{ $e->type === 'debit' ? $m($e->amount) : '' }}</td>
                                    <td class="text-end text-nowrap">{{ $m($e->balance_after) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><div class="a-empty"><i class="ri-file-list-3-line"></i>No wallet activity in this period.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($s['entries']->count() > 300)<p class="small text-muted mt-2 mb-0">Showing the latest 300 here — the PDF and CSV have all {{ $s['entries']->count() }}.</p>@endif
            </div>
        </div>
        <div class="col-xl-4">
            <div class="a-card mb-4">
                <div class="a-card-head"><h2>Spending by service</h2></div>
                @forelse($s['spend'] as $row)
                    @php $pct = $s['debits'] > 0 ? min(100, round($row['total'] / max($s['spend']->sum('total'), 1) * 100)) : 0; $c = config('afritopup.categories.' . $row['category'] . '.color', '#0B8457'); @endphp
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small fw-semibold"><span>{{ $row['label'] }} <span class="text-muted fw-normal">· {{ $row['count'] }}</span></span><span>{{ $m($row['total']) }}</span></div>
                        <div class="progress mt-1" style="height:6px;border-radius:6px"><div class="progress-bar" style="width: {{ $pct }}%; background: {{ $c }}"></div></div>
                    </div>
                @empty
                    <div class="small text-muted">No purchases in this period.</div>
                @endforelse
            </div>
            <div class="a-card">
                <div class="d-flex align-items-center gap-3">
                    <span class="s-ico"><i class="ri-mail-check-line"></i></span>
                    <div class="flex-grow-1"><b class="d-block" style="color: var(--s-ink)">E-mail receipts</b><small class="text-muted">A receipt for every payment and top-up</small></div>
                    <form method="POST" action="{{ route('account.receipts') }}">@csrf
                        <input type="hidden" name="email_receipts" value="{{ $s['user']->wantsEmailReceipts() ? 0 : 1 }}">
                        <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" aria-label="E-mail receipts" @checked($s['user']->wantsEmailReceipts()) onchange="this.form.submit()"></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
