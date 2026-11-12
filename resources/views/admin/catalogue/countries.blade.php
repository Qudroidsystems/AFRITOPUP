@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Countries" icon="ri-earth-line" subtitle="Markets AfriTopup sells in. A country appears to customers once it is switched on and has services." />
    @include('partials.flash')
    <x-cb.card :flush="true">
        <div class="table-responsive"><table class="cb-table stack">
            <thead><tr><th>Country</th><th>Currency</th><th>Dial code</th><th>Services</th><th>Customers see it</th><th></th></tr></thead>
            <tbody>
            @foreach($countries as $c)
                <tr>
                    <td data-label="Country" class="fw-semibold">{{ $c->flag }} {{ $c->name }} <span class="text-muted small">{{ $c->code }}</span></td>
                    <td data-label="Currency">{{ $c->currency }}</td>
                    <td data-label="Dial code">+{{ $c->dial_code }}</td>
                    <td data-label="Services"><a href="{{ route('admin.services.index', ['country' => $c->code]) }}">{{ $c->services_count }}</a></td>
                    <td data-label="Status"><form method="POST" action="{{ route('admin.countries.toggle', $c) }}">@csrf<button class="btn btn-sm {{ $c->is_active ? 'btn-soft-success' : 'btn-soft-secondary' }}">{{ $c->is_active ? 'Open' : 'Hidden' }}</button></form></td>
                    <td class="text-end">@if($c->code !== 'NG')<form method="POST" action="{{ route('admin.countries.import', $c) }}" onsubmit="this.querySelector('button').disabled=true">@csrf<button class="btn btn-sm btn-light"><i class="ri-download-cloud-2-line me-1"></i>Import networks from Reloadly</button></form>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </x-cb.card>
    <p class="small text-muted">Opening a new market: press <strong>Import networks from Reloadly</strong> (after entering the Reloadly keys), check the services and prices it created, then open the country here. Customers pick their country on the Buy page and in the app; each country has its own wallet in its own currency. New customers are placed in {{ config('afritopup.default_country') }} unless their account says otherwise.</p>
</div></div></div>
@endsection
