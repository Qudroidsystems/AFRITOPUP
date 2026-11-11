@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    @include('topup.partials.styles')
    <x-cb.hero title="Buy & Pay" icon="ri-shopping-bag-3-line" subtitle="Airtime, data, cable TV, electricity, bulk SMS and education payments — paid from your wallet.">
        <x-slot:pills>
            <span class="cb-meta-pill">{{ $country->flag }} {{ $country->name }}</span>
            <span class="cb-meta-pill"><i class="ri-wallet-3-line me-1"></i>{{ $wallet->formatted_balance }}</span>
        </x-slot:pills>
        <x-slot:actions><a href="{{ route('topup.wallet') }}#fund" class="cb-hero-btn"><i class="ri-add-line"></i>Fund wallet</a></x-slot:actions>
    </x-cb.hero>
    @include('partials.flash')

    <div class="row g-3 mb-3">
        @foreach($categories as $key => $cat)
            <div class="col-6 col-md-4 col-xl-2">
                <a href="{{ route('topup.buy.form', $key) }}" class="at-cat" style="--c: {{ $cat['color'] }}">
                    <span class="ico"><i class="{{ $cat['icon'] }}"></i></span>
                    <span class="nm">{{ $cat['name'] }}</span>
                    <span class="sub">{{ $services[$key]->take(4)->pluck('short_name')->filter()->implode(' · ') }}</span>
                </a>
            </div>
        @endforeach
    </div>

    @if($recent->isNotEmpty())
        <x-cb.card title="Buy again" icon="ri-repeat-line" :flush="true">
            <div class="table-responsive"><table class="cb-table stack"><tbody>
                @foreach($recent as $t)
                    <tr>
                        <td data-label="Purchase"><div class="d-flex align-items-center gap-2">@include('topup.partials.service-icon', ['s' => $t->service, 'size' => 34])
                            <div><div class="fw-semibold">{{ $t->product?->name ?? ($t->service?->display_name . ' ' . $t->category_label) }}</div><div class="small text-muted">{{ $t->recipient }}</div></div></div></td>
                        <td data-label="Amount" class="num">{{ \App\Support\Money::format($t->total, $t->currency) }}</td>
                        <td class="text-end"><a class="btn btn-sm btn-soft-success" href="{{ route('topup.buy.form', ['category' => $t->category, 'service' => $t->service?->code, 'recipient' => $t->category === 'sms' ? null : $t->recipient]) }}">Buy again</a></td>
                    </tr>
                @endforeach
            </tbody></table></div>
        </x-cb.card>
    @endif
</div></div></div>
@endsection
