@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Price tiers" icon="ri-percent-line" subtitle="Discount levels for customers, agents and resellers. Set each tier's discount per service on the service page." :back="route('admin.services.index')" back-label="Services" />
    @include('partials.flash')
    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card :flush="true">
                <div class="table-responsive"><table class="cb-table">
                    <thead><tr><th>Tier</th><th>Code</th><th>People</th><th></th></tr></thead>
                    <tbody>
                    @foreach($tiers as $t)
                        <tr>
                            <td class="fw-semibold">{{ $t->name }}@if($t->is_default) <span class="badge bg-success-subtle text-success">default</span>@endif<div class="small text-muted">{{ $t->description }}</div></td>
                            <td class="at-mono">{{ $t->code }}</td>
                            <td>{{ $t->users_count }}</td>
                            <td class="text-end"><button class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#t{{ $t->id }}">Edit</button></td>
                        </tr>
                        <tr class="collapse" id="t{{ $t->id }}"><td colspan="4">
                            <form method="POST" action="{{ route('admin.tiers.update', $t) }}" class="row g-2 align-items-end">@csrf @method('PUT')
                                <div class="col-md-3"><input class="form-control form-control-sm" name="name" value="{{ $t->name }}" required></div>
                                <div class="col-md-2"><input class="form-control form-control-sm" name="code" value="{{ $t->code }}" required></div>
                                <div class="col-md-4"><input class="form-control form-control-sm" name="description" value="{{ $t->description }}"></div>
                                <div class="col-md-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_default" value="1" @checked($t->is_default) id="d{{ $t->id }}"><label class="form-check-label small" for="d{{ $t->id }}">Default</label></div></div>
                                <div class="col-md-1"><button class="btn btn-sm btn-primary">Save</button></div>
                            </form>
                        </td></tr>
                    @endforeach
                    </tbody>
                </table></div>
            </x-cb.card>
            <p class="small text-muted">Give a person a tier (and make them an agent or reseller) from their wallet page under Wallets → Balances. Customers without a tier buy at the default one.</p>
        </div>
        <div class="col-xl-4">
            <x-cb.card title="New tier" icon="ri-add-line">
                <form method="POST" action="{{ route('admin.tiers.store') }}">@csrf
                    <input class="form-control mb-2" name="name" placeholder="Name, e.g. Gold agent" required>
                    <input class="form-control mb-2" name="code" placeholder="code, e.g. gold-agent" required>
                    <input class="form-control mb-3" name="description" placeholder="Who it is for">
                    <button class="btn btn-success w-100">Add tier</button>
                </form>
            </x-cb.card>
        </div>
    </div>
</div></div></div>
@endsection
