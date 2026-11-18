@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    @include('topup.partials.styles')
    @php
        $cur = $f['currency'];
        $m = fn ($v) => \App\Support\Money::format($v, $cur);
        $pct = fn ($v) => $v === null ? '—' : number_format($v, 1) . '%';
        $chg = function ($v) { if ($v === null) return 'new'; return ($v >= 0 ? '▲ ' : '▼ ') . number_format(abs($v), 1) . '% vs previous period'; };
        $q = array_filter($f, fn ($v) => $v !== null && $v !== '');
        $catName = fn ($k) => $categories[$k] ?? ucfirst($k);
        $venName = fn ($k) => $vendors[$k] ?? $k;
        $rangeLabel = $r->from->format('j M Y') . ($r->from->isSameDay($r->to) ? '' : ' – ' . $r->to->format('j M Y'));
    @endphp
    <x-cb.hero title="Reports & analytics" icon="ri-bar-chart-box-line" :subtitle="'Sales, profit and transactions · ' . $rangeLabel . ' · ' . $cur">
        <x-slot:actions>
            <div class="dropdown">
                <button class="cb-hero-btn border-0 dropdown-toggle" data-bs-toggle="dropdown" type="button"><i class="ri-download-2-line"></i>Export CSV</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @foreach(['daily' => 'Day by day', 'categories' => 'By category', 'vendors' => 'By vendor', 'channels' => 'By channel', 'services' => 'By service', 'transactions' => 'Every purchase'] as $k => $l)
                        <li><a class="dropdown-item" data-no-loader href="{{ route('admin.reports.export', array_merge(['what' => $k], $q)) }}">{{ $l }}</a></li>
                    @endforeach
                </ul>
            </div>
        </x-slot:actions>
    </x-cb.hero>
    @include('partials.flash')

    {{-- Filters: one row above everything --}}
    <x-cb.card :flush="true" class="mb-3">
        <form class="row g-2 p-3 align-items-end" method="GET" id="report-filters">
            <div class="col-lg-2 col-md-4"><label class="form-label small fw-semibold mb-1">Period</label>
                <select class="form-select" name="range" onchange="document.getElementById('rf-dates').classList.toggle('d-none', this.value !== 'custom'); if (this.value !== 'custom') this.form.submit();">
                    @foreach(\App\Services\Reports\ReportService::PRESETS as $k => $l)<option value="{{ $k }}" @selected($f['range'] === $k)>{{ $l }}</option>@endforeach
                </select></div>
            <div class="col-lg-3 col-md-8 {{ $f['range'] === 'custom' ? '' : 'd-none' }}" id="rf-dates"><label class="form-label small fw-semibold mb-1">From – to</label>
                <div class="d-flex gap-2"><input type="date" class="form-control" name="from" value="{{ $f['from'] }}"><input type="date" class="form-control" name="to" value="{{ $f['to'] }}"></div></div>
            <div class="col-lg-2 col-md-3"><label class="form-label small fw-semibold mb-1">Category</label>
                <select class="form-select" name="category"><option value="">All</option>@foreach($categories as $k => $l)<option value="{{ $k }}" @selected($f['category'] === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="col-lg-2 col-md-3"><label class="form-label small fw-semibold mb-1">Vendor</label>
                <select class="form-select" name="vendor"><option value="">All</option>@foreach($vendors as $k => $l)<option value="{{ $k }}" @selected($f['vendor'] === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="col-lg-1 col-md-2"><label class="form-label small fw-semibold mb-1">Channel</label>
                <select class="form-select" name="channel"><option value="">All</option>@foreach(['web', 'app', 'api'] as $c)<option value="{{ $c }}" @selected($f['channel'] === $c)>{{ strtoupper($c) }}</option>@endforeach</select></div>
            @if(count($currencies) > 1)
                <div class="col-lg-1 col-md-2"><label class="form-label small fw-semibold mb-1">Currency</label>
                    <select class="form-select" name="currency">@foreach($currencies as $c)<option value="{{ $c }}" @selected($cur === $c)>{{ $c }}</option>@endforeach</select></div>
            @endif
            <div class="col-lg-1 col-md-2"><button class="btn btn-primary w-100"><i class="ri-filter-3-line"></i> Apply</button></div>
        </form>
    </x-cb.card>

    @if($s['stuck'])
        <div class="cb-banner warning"><i class="ri-time-line"></i><div><a href="{{ route('admin.transactions.index', ['status' => 'processing']) }}">{{ $s['stuck'] }} purchase(s) processing for over 15 minutes.</a> They count once they settle.</div></div>
    @endif

    {{-- Headline figures --}}
    <div class="row g-3 mb-3">
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Sales (successful)" :value="$m($s['sales'])" icon="ri-shopping-bag-3-line" accent="green" :hint="$chg($s['change']['sales'])" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Gross profit" :value="$m($s['profit'])" icon="ri-line-chart-line" accent="teal" :hint="'Margin ' . $pct($s['margin']) . ' · ' . $chg($s['change']['profit'])" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Net profit" :value="$m($s['net'])" icon="ri-money-dollar-circle-line" accent="violet" :hint="'After ' . $m($s['referrals']) . ' referral rewards'" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Successful purchases" :value="number_format($s['count'])" icon="ri-checkbox-circle-line" accent="sky" :hint="$chg($s['change']['count'])" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Success rate" :value="$pct($s['successRate'])" icon="ri-percent-line" :accent="($s['successRate'] ?? 100) < 90 ? 'rose' : 'green'" :hint="number_format($s['all']) . ' purchases in all'" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Vendor cost" :value="$m($s['cost'])" icon="ri-plug-line" accent="amber" :hint="'Discounts given ' . $m($s['discount']) . ' · fees ' . $m($s['fees'])" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Average purchase" :value="$m($s['avg'])" icon="ri-scales-3-line" accent="sky" :hint="'Profit ' . $m($s['avgProfit']) . ' each'" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Paying customers" :value="number_format($s['buyers'])" icon="ri-user-star-line" accent="teal" :hint="'+' . number_format($s['newCustomers']) . ' new sign-ups'" /></div>
    </div>

    {{-- Sales & profit over time --}}
    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <x-cb.card title="Sales and profit by day" icon="ri-bar-chart-2-line">
                <x-slot:tools><span class="small text-muted">{{ $cur }}</span></x-slot:tools>
                <div id="chart-daily" class="rpt-chart" style="min-height: 320px"></div>
            </x-cb.card>
        </div>
        <div class="col-xl-4">
            <x-cb.card title="Purchases by status" icon="ri-donut-chart-line">
                @php $maxS = max(1, collect($s['statuses'])->max('n')); @endphp
                @foreach($s['statuses'] as $k => $st)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small"><span><span class="badge bg-{{ $st['color'] }}-subtle text-{{ $st['color'] }} me-1">{{ $st['label'] }}</span></span><span class="fw-semibold">{{ number_format($st['n']) }} <span class="text-muted fw-normal">· {{ $m($st['value']) }}</span></span></div>
                        <div class="rpt-bar mt-1"><span class="bg-{{ $st['color'] }}" style="width: {{ round($st['n'] / $maxS * 100) }}%"></span></div>
                    </div>
                @endforeach
                <div class="at-kv mt-2"><span>Wallet fundings</span><span>{{ $m($s['fundings']['amount']) }} <span class="text-muted fw-normal">({{ number_format($s['fundings']['n']) }})</span></span></div>
                <div class="at-kv"><span>Gateway fees paid by customers</span><span>{{ $m($s['fundings']['fee']) }}</span></div>
                <div class="at-kv"><span>Money in wallets now</span><span>{{ $m($s['float']) }}</span></div>
            </x-cb.card>
        </div>
    </div>

    {{-- Breakdowns --}}
    @php
        $table = function (array $rows, string $label, callable $name, string $exportKey, bool $compact = false) use ($m, $pct, $q) {
            return view('admin.reports.partials.breakdown', compact('rows', 'label', 'name', 'exportKey', 'm', 'pct', 'q', 'compact'))->render();
        };
    @endphp
    <div class="row g-3 mb-3">
        <div class="col-xl-6">
            <x-cb.card title="By category" icon="ri-apps-2-line" :flush="true">{!! $table($byCategory, 'Category', $catName, 'categories') !!}</x-cb.card>
        </div>
        <div class="col-xl-6">
            <x-cb.card title="By vendor" icon="ri-plug-line" :flush="true">{!! $table($byVendor, 'Vendor', $venName, 'vendors') !!}</x-cb.card>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <x-cb.card title="Top services" icon="ri-trophy-line" :flush="true">{!! $table($services, 'Service', fn ($k) => $k, 'services') !!}</x-cb.card>
        </div>
        <div class="col-xl-4">
            <x-cb.card title="By channel" icon="ri-smartphone-line" :flush="true">{!! $table($byChannel, 'Channel', fn ($k) => strtoupper($k), 'channels', true) !!}</x-cb.card>
            <x-cb.card title="Busiest hours" icon="ri-time-line" class="mt-3">
                <div id="chart-hourly" class="rpt-chart" style="min-height: 200px"></div>
            </x-cb.card>
        </div>
    </div>

    <x-cb.card title="Top customers" icon="ri-user-star-line" :flush="true" class="mb-3">
        @if(!$customers)
            <div class="empty-state"><i class="ri-user-line"></i><h6>No purchases in this period</h6></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>#</th><th>Customer</th><th class="text-end">Purchases</th><th class="text-end">Spent</th><th class="text-end">Profit</th></tr></thead>
                <tbody>
                @foreach($customers as $i => $c)
                    <tr>
                        <td data-label="#">{{ $i + 1 }}</td>
                        <td data-label="Customer">@can('View wallets')<a href="{{ route('admin.wallets.show', $c['id']) }}" class="fw-semibold">{{ $c['name'] }}</a>@else<span class="fw-semibold">{{ $c['name'] }}</span>@endcan<div class="small text-muted">{{ $c['email'] }}</div></td>
                        <td data-label="Purchases" class="text-end">{{ number_format($c['n']) }}</td>
                        <td data-label="Spent" class="text-end">{{ $m($c['sales']) }}</td>
                        <td data-label="Profit" class="text-end">{{ $m($c['profit']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </x-cb.card>

    {{-- Day-by-day table (the chart's data, readable without the chart) --}}
    <x-cb.card title="Day by day" icon="ri-table-line" :flush="true">
        <x-slot:tools><a class="btn btn-sm btn-soft-secondary" data-no-loader href="{{ route('admin.reports.export', array_merge(['what' => 'daily'], $q)) }}"><i class="ri-download-2-line"></i> CSV</a></x-slot:tools>
        <div class="table-responsive" style="max-height: 420px"><table class="cb-table stack">
            <thead><tr><th>Date</th><th class="text-end">Purchases</th><th class="text-end">Successful</th><th class="text-end">Failed</th><th class="text-end">Sales</th><th class="text-end">Cost</th><th class="text-end">Profit</th></tr></thead>
            <tbody>
            @foreach(array_reverse($daily) as $d)
                <tr @if(!$d['n']) class="text-muted" @endif>
                    <td data-label="Date"><a href="{{ route('admin.transactions.index', ['from' => $d['date'], 'to' => $d['date']]) }}">{{ \Carbon\Carbon::parse($d['date'])->format('D j M Y') }}</a></td>
                    <td data-label="Purchases" class="text-end">{{ number_format($d['n']) }}</td>
                    <td data-label="Successful" class="text-end">{{ number_format($d['ok']) }}</td>
                    <td data-label="Failed" class="text-end">{{ number_format($d['failed']) }}</td>
                    <td data-label="Sales" class="text-end">{{ $m($d['sales']) }}</td>
                    <td data-label="Cost" class="text-end">{{ $m($d['cost']) }}</td>
                    <td data-label="Profit" class="text-end fw-semibold">{{ $m($d['profit']) }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr class="fw-semibold"><td>Total</td><td class="text-end">{{ number_format($s['all']) }}</td><td class="text-end">{{ number_format($s['count']) }}</td><td class="text-end">{{ number_format(collect($daily)->sum('failed')) }}</td><td class="text-end">{{ $m($s['sales']) }}</td><td class="text-end">{{ $m($s['cost']) }}</td><td class="text-end">{{ $m($s['profit']) }}</td></tr></tfoot>
        </table></div>
    </x-cb.card>
    <p class="small text-muted mt-2">Sales are what customers paid for successful purchases. Cost is what the vendor charged (where it is unknown, no profit is counted for that purchase). Gross profit = sales − cost; net profit also takes off referral rewards paid in the period. Refunded and failed purchases are not counted as sales.</p>
</div></div></div>
@endsection

@push('styles')
<style>
    .rpt-chart { --series-1: #2a78d6; --series-2: #1baf7a; --rpt-ink: #52514e; --rpt-grid: rgba(0,0,0,.07); }
    [data-bs-theme="dark"] .rpt-chart { --series-1: #3987e5; --series-2: #199e70; --rpt-ink: #c3c2b7; --rpt-grid: rgba(255,255,255,.08); }
    .rpt-bar { height: 6px; border-radius: 3px; background: var(--vz-light, #f1f3f5); overflow: hidden; }
    .rpt-bar > span { display: block; height: 100%; border-radius: 3px; min-width: 2px; }
    .rpt-share { height: 4px; border-radius: 2px; background: var(--vz-light, #eef1f4); margin-top: 4px; }
    .rpt-share > span { display: block; height: 100%; border-radius: 2px; background: #2a78d6; }
</style>
@endpush

@push('scripts')
<script src="{{ asset('theme/layouts/assets/libs/apexcharts/apexcharts.min.js') }}"></script>
<script>
(function () {
    if (typeof ApexCharts === 'undefined') return;
    var daily = @json($daily), hourly = @json($hourly), cur = @json($cur);
    var symbol = @json(\App\Support\Money::symbol($cur));
    function css(el, name) { return getComputedStyle(el).getPropertyValue(name).trim(); }
    function money(v) { return symbol + Number(v || 0).toLocaleString(undefined, { maximumFractionDigits: 0 }); }
    function short(v) { var a = Math.abs(v); return symbol + (a >= 1e6 ? (v / 1e6).toFixed(1) + 'M' : a >= 1e3 ? (v / 1e3).toFixed(0) + 'k' : Math.round(v)); }
    var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';

    var el = document.getElementById('chart-daily');
    if (el) {
        var many = daily.length > 45;
        new ApexCharts(el, {
            chart: { type: 'bar', height: 320, toolbar: { show: false }, fontFamily: 'inherit', foreColor: css(el, '--rpt-ink'), background: 'transparent' },
            theme: { mode: dark ? 'dark' : 'light' },
            series: [{ name: 'Sales', data: daily.map(function (d) { return d.sales; }) }, { name: 'Profit', data: daily.map(function (d) { return d.profit; }) }],
            colors: [css(el, '--series-1'), css(el, '--series-2')],
            plotOptions: { bar: { columnWidth: many ? '80%' : '60%', borderRadius: 3, borderRadiusApplication: 'end' } },
            stroke: { show: true, width: 2, colors: ['transparent'] },
            dataLabels: { enabled: false },
            grid: { borderColor: css(el, '--rpt-grid'), strokeDashArray: 3 },
            xaxis: { categories: daily.map(function (d) { return d.date; }), type: 'datetime', labels: { datetimeUTC: false } },
            yaxis: { labels: { formatter: short } },
            legend: { position: 'top', horizontalAlign: 'left' },
            tooltip: { shared: true, intersect: false, x: { format: 'ddd d MMM yyyy' }, y: { formatter: money } }
        }).render();
    }

    var hel = document.getElementById('chart-hourly');
    if (hel) {
        new ApexCharts(hel, {
            chart: { type: 'bar', height: 200, toolbar: { show: false }, fontFamily: 'inherit', foreColor: css(hel, '--rpt-ink'), background: 'transparent' },
            theme: { mode: dark ? 'dark' : 'light' },
            series: [{ name: 'Successful purchases', data: hourly.map(function (h) { return h.n; }) }],
            colors: [css(hel, '--series-1')],
            plotOptions: { bar: { columnWidth: '70%', borderRadius: 2, borderRadiusApplication: 'end' } },
            dataLabels: { enabled: false },
            grid: { borderColor: css(hel, '--rpt-grid'), strokeDashArray: 3 },
            xaxis: { categories: hourly.map(function (h) { return (h.hour < 10 ? '0' : '') + h.hour + ':00'; }), tickAmount: 6, labels: { rotate: 0 } },
            yaxis: { labels: { formatter: function (v) { return Math.round(v); } } },
            tooltip: { y: { formatter: function (v, o) { return v + ' · ' + money(hourly[o.dataPointIndex].sales); } } }
        }).render();
    }
})();
</script>
@endpush
