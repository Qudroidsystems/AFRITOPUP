@php $compact = $compact ?? false; @endphp
@if(!$rows)
    <div class="empty-state"><i class="ri-bar-chart-line"></i><h6>No purchases in this period</h6></div>
@else
    @php $total = max(0.01, collect($rows)->sum('sales')); @endphp
    <div class="table-responsive"><table class="cb-table stack">
        <thead><tr><th>{{ $label }}</th><th class="text-end">Purchases</th>@unless($compact)<th class="text-end">Success</th>@endunless<th class="text-end">Sales</th><th class="text-end">Profit</th>@unless($compact)<th class="text-end">Margin</th>@endunless</tr></thead>
        <tbody>
        @foreach($rows as $x)
            <tr>
                <td data-label="{{ $label }}"><span class="fw-semibold">{{ $name($x['key']) }}</span>
                    <div class="rpt-share" title="{{ number_format($x['sales'] / $total * 100, 1) }}% of sales"><span style="width: {{ round($x['sales'] / $total * 100, 1) }}%"></span></div></td>
                <td data-label="Purchases" class="text-end">{{ number_format($x['n']) }}@if($x['failed'])<div class="small text-danger">{{ number_format($x['failed']) }} failed</div>@endif</td>
                @unless($compact)<td data-label="Success" class="text-end">{{ $pct($x['rate']) }}</td>@endunless
                <td data-label="Sales" class="text-end">{{ $m($x['sales']) }}</td>
                <td data-label="Profit" class="text-end fw-semibold">{{ $m($x['profit']) }}</td>
                @unless($compact)<td data-label="Margin" class="text-end">{{ $pct($x['margin']) }}</td>@endunless
            </tr>
        @endforeach
        </tbody>
    </table></div>
@endif
