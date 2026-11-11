@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Schools" icon="ri-school-line" subtitle="Schools parents can pay fees to. Fees stay 'processing' until you pay the school and mark them delivered." />
    @include('partials.flash')
    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card :flush="true" title="Partner schools" icon="ri-list-check" :count="$schools->count()">
                <div class="table-responsive"><table class="cb-table stack">
                    <thead><tr><th>School</th><th>Payout account</th><th>Waiting to be paid out</th><th></th></tr></thead>
                    <tbody>
                    @forelse($schools as $sc)
                        @php $o = $owed[$sc->id] ?? null; @endphp
                        <tr class="{{ $sc->is_active ? '' : 'opacity-50' }}">
                            <td data-label="School" class="fw-semibold">{{ $sc->name }}<div class="small text-muted">{{ $sc->code }} · {{ $sc->city }} · {{ $sc->country_code }}</div></td>
                            <td data-label="Payout" class="small">{{ $sc->bank_name }} {{ $sc->account_number }}<div class="text-muted">{{ $sc->account_name }}</div></td>
                            <td data-label="Owed">@if($o)<a href="{{ route('admin.transactions.index', ['category' => 'education', 'status' => 'processing', 'q' => $sc->name]) }}">{{ $o['n'] }} payments · {{ \App\Support\Money::format($o['total']) }}</a>@else <span class="text-muted">Nothing</span>@endif</td>
                            <td class="text-end"><button class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#s{{ $sc->id }}">Edit</button></td>
                        </tr>
                        <tr class="collapse" id="s{{ $sc->id }}"><td colspan="4">@include('admin.schools._form', ['school' => $sc])</td></tr>
                    @empty
                        <tr><td colspan="4"><div class="empty-state"><i class="ri-school-line"></i><h6>No schools yet</h6></div></td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </x-cb.card>
        </div>
        <div class="col-xl-4">
            <x-cb.card title="Add a school" icon="ri-add-line">@include('admin.schools._form', ['school' => null])</x-cb.card>
        </div>
    </div>
</div></div></div>
@endsection
