@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">

    @php
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $me = auth()->user();
        $m = fn ($v, $c = null) => \App\Support\Money::format($v, $c);
    @endphp
    @include('topup.partials.styles')

    <x-cb.hero :title="$greeting . ', ' . $me->first_name" icon="ri-sun-line" :subtitle="$brand['tagline']">
        <x-slot:pills>
            <span class="cb-meta-pill"><i class="ri-user-3-line me-1"></i>{{ $me->user_type_label }}</span>
            @if($me->priceTier)<span class="cb-meta-pill"><i class="ri-price-tag-3-line me-1"></i>{{ $me->priceTier->name }} prices</span>@endif
            <span class="cb-meta-pill"><i class="ri-calendar-line me-1"></i>{{ now()->format('l, j F Y') }}</span>
        </x-slot:pills>
        <x-slot:actions>
            <a href="{{ route('notifications.index') }}" class="cb-hero-btn"><i class="ri-notification-3-line"></i>Notifications @if($unread)<span class="badge bg-danger ms-1">{{ $unread }}</span>@endif</a>
            @can('Buy services')<a href="{{ route('topup.wallet') }}#fund" class="cb-hero-btn"><i class="ri-add-circle-line"></i>Fund wallet</a>@endcan
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    {{-- ── Platform overview (staff) ─────────────────────────────────── --}}
    @if($overview)
        @if($overview['sandbox'])
            <div class="cb-banner warning"><i class="ri-flask-line"></i><div>{{ $overview['sandbox'] }} {{ \Illuminate\Support\Str::plural('service', $overview['sandbox']) }} still use the <strong>sandbox</strong> provider — purchases there are not real. @can('Manage services')<a href="{{ route('admin.services.index') }}">Switch them to a live provider</a>.@endcan</div></div>
        @endif
        @if($overview['stuck'])
            <div class="cb-banner danger"><i class="ri-alarm-warning-line"></i><div><a href="{{ route('admin.transactions.index', ['status' => 'processing']) }}">{{ $overview['stuck'] }} {{ \Illuminate\Support\Str::plural('purchase', $overview['stuck']) }}</a> {{ $overview['stuck'] === 1 ? 'has' : 'have' }} been processing for over 15 minutes.</div></div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-xl-3 col-md-6"><x-cb.stat label="Sales today" :value="$m($overview['salesToday'])" icon="ri-shopping-bag-3-line" accent="green" :hint="number_format($overview['countToday']) . ' successful purchases'" /></div>
            <div class="col-xl-3 col-md-6"><x-cb.stat label="Sales this month" :value="$m($overview['salesMonth'])" icon="ri-line-chart-line" accent="teal" :hint="'Margin ' . $m($overview['marginMonth'])" /></div>
            <div class="col-xl-3 col-md-6"><x-cb.stat label="Funded today" :value="$m($overview['fundedToday'])" icon="ri-bank-card-line" accent="amber" :hint="'Wallet float ' . $m($overview['float'])" /></div>
            <div class="col-xl-3 col-md-6"><x-cb.stat label="Failed today" :value="number_format($overview['failedToday'])" icon="ri-error-warning-line" accent="rose" :hint="$overview['processing'] . ' processing now'" /></div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-xl-8">
                <x-cb.card title="Sales, last 14 days" icon="ri-bar-chart-2-line">
                    @php
                        $days = collect(range(13, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));
                        $max = max(1, (float) $overview['daily']->max());
                    @endphp
                    <div class="at-bars">
                        @foreach($days as $d)
                            @php $v = (float) ($overview['daily'][$d] ?? 0); @endphp
                            <div style="height: {{ max(2, round($v / $max * 100)) }}%" data-tip="{{ \Carbon\Carbon::parse($d)->format('D j M') }}: {{ $m($v) }}"></div>
                        @endforeach
                    </div>
                    <div class="d-flex justify-content-between small text-muted mt-2"><span>{{ \Carbon\Carbon::parse($days->first())->format('j M') }}</span><span>Today</span></div>
                </x-cb.card>
            </div>
            <div class="col-xl-4">
                <x-cb.card title="Provider balances" icon="ri-plug-line">
                    @foreach($overview['balances'] as $name => $bal)
                        <div class="at-kv"><span>{{ $name }}</span><span>{!! $bal === null ? '<span class="text-muted fw-normal">not connected</span>' : e($m($bal)) !!}</span></div>
                    @endforeach
                    <div class="at-kv"><span>Customers</span><span>{{ number_format($overview['buyers']) }} <span class="text-muted fw-normal">(+{{ $overview['newCustomers'] }} today)</span></span></div>
                    @can('Manage settings')<a href="{{ route('portal-settings.providers') }}" class="btn btn-sm btn-soft-primary mt-2">Provider settings</a>@endcan
                </x-cb.card>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-xl-4">
                <x-cb.card title="Last 30 days by service" icon="ri-pie-chart-line" :flush="true">
                    @forelse($overview['byCategory'] as $row)
                        @php $cat = config('afritopup.categories.' . $row->category); @endphp
                        <div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom">
                            <span class="at-cat-mini" style="color: {{ $cat['color'] ?? '#0B8457' }}"><i class="{{ $cat['icon'] ?? 'ri-shopping-bag-line' }} fs-20"></i></span>
                            <div class="flex-grow-1"><div class="fw-semibold">{{ $cat['name'] ?? $row->category }}</div><div class="small text-muted">{{ number_format($row->n) }} purchases</div></div>
                            <div class="fw-bold">{{ $m($row->total) }}</div>
                        </div>
                    @empty
                        <div class="empty-state"><i class="ri-pie-chart-line"></i><h6>No sales yet</h6></div>
                    @endforelse
                </x-cb.card>
            </div>
            <div class="col-xl-8">
                <x-cb.card title="Latest purchases" icon="ri-exchange-funds-line" :flush="true">
                    <x-slot:tools><a href="{{ route('admin.transactions.index') }}" class="btn btn-sm btn-soft-primary">All transactions</a></x-slot:tools>
                    @if($overview['recent']->isEmpty())
                        <div class="empty-state"><i class="ri-exchange-funds-line"></i><h6>No purchases yet</h6></div>
                    @else
                        <div class="table-responsive"><table class="cb-table stack"><tbody>
                            @foreach($overview['recent'] as $t)
                                <tr>
                                    <td data-label="Purchase">
                                        <div class="d-flex align-items-center gap-2">
                                            @include('topup.partials.service-icon', ['s' => $t->service, 'size' => 34])
                                            <div><a href="{{ route('admin.transactions.show', $t) }}" class="fw-semibold">{{ $t->service?->display_name }} {{ $t->category_label }}</a>
                                                <div class="small text-muted">{{ $t->user?->name }} · {{ $t->recipient }}</div></div>
                                        </div>
                                    </td>
                                    <td data-label="Amount" class="num">{{ $m($t->total, $t->currency) }}<div class="small text-muted">{{ $t->created_at->diffForHumans() }}</div></td>
                                    <td data-label="Status">@include('topup.partials.status', ['t' => $t])</td>
                                </tr>
                            @endforeach
                        </tbody></table></div>
                    @endif
                </x-cb.card>
            </div>
        </div>
    @endif

    {{-- ── Buyer home ────────────────────────────────────────────────── --}}
    @if($buyer)
        @unless($buyer['hasPin'])
            <div class="cb-banner info"><i class="ri-lock-password-line"></i><div>Set your 4-digit transaction PIN before your first purchase. <a href="{{ route('topup.wallet') }}#pin">Set PIN</a></div></div>
        @endunless
        <div class="row g-3 mb-3">
            <div class="col-xl-4">
                <div class="at-wallet h-100">
                    <div class="lbl">Wallet balance</div>
                    <div class="amt">{{ $buyer['wallet']->formatted_balance }}</div>
                    <div class="small mb-3" style="opacity:.85">Spent this month: {{ $m($buyer['spentMonth'], $buyer['wallet']->currency) }}</div>
                    <div class="d-flex gap-2 position-relative" style="z-index:1">
                        <a href="{{ route('topup.wallet') }}#fund" class="btn btn-light btn-sm"><i class="ri-add-line me-1"></i>Fund wallet</a>
                        <a href="{{ route('topup.transactions') }}" class="btn btn-outline-light btn-sm">History</a>
                    </div>
                </div>
            </div>
            <div class="col-xl-8">
                <div class="row g-3 h-100">
                    @foreach($buyer['categories'] as $key => $cat)
                        <div class="col-4 col-md-4 col-xl-4">
                            <a href="{{ route('topup.buy.form', $key) }}" class="at-cat" style="--c: {{ $cat['color'] }}">
                                <span class="ico"><i class="{{ $cat['icon'] }}"></i></span>
                                <span class="nm">{{ $cat['name'] }}</span>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <x-cb.card title="Recent purchases" icon="ri-history-line" :flush="true">
            <x-slot:tools><a href="{{ route('topup.transactions') }}" class="btn btn-sm btn-soft-primary">See all</a></x-slot:tools>
            @if($buyer['recent']->isEmpty())
                <div class="empty-state"><i class="ri-shopping-bag-3-line"></i><h6>No purchases yet</h6><p>Fund your wallet, then buy airtime, data, TV or power in seconds.</p></div>
            @else
                <div class="table-responsive"><table class="cb-table stack"><tbody>
                    @foreach($buyer['recent'] as $t)
                        <tr>
                            <td data-label="Purchase">
                                <div class="d-flex align-items-center gap-2">
                                    @include('topup.partials.service-icon', ['s' => $t->service, 'size' => 34])
                                    <div><a href="{{ route('topup.transactions.show', $t->reference) }}" class="fw-semibold">{{ $t->product?->name ?? ($t->service?->display_name . ' ' . $t->category_label) }}</a>
                                        <div class="small text-muted">{{ $t->recipient }}</div></div>
                                </div>
                            </td>
                            <td data-label="Amount" class="num">{{ $m($t->total, $t->currency) }}<div class="small text-muted">{{ $t->created_at->diffForHumans() }}</div></td>
                            <td data-label="Status">@include('topup.partials.status', ['t' => $t])</td>
                        </tr>
                    @endforeach
                </tbody></table></div>
            @endif
        </x-cb.card>
    @endif

    @if(!$overview && !$buyer)
        <x-cb.card title="Welcome" icon="ri-apps-2-line">
            <div class="empty-state"><i class="ri-apps-line"></i><h6>Nothing to show yet</h6><p>Your administrator has not given your account access to anything yet.</p></div>
        </x-cb.card>
    @endif

</div>
</div>
</div>
@endsection
