@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="SMS Sender IDs" icon="ri-message-2-line" subtitle="Register each name with the SMS provider (Termii dashboard → Sender ID) and record the networks' decision here." />
    @include('partials.flash')
    <x-cb.card :flush="true">
        <form class="d-flex gap-2 p-3 border-bottom" method="GET"><select class="form-select" style="max-width:200px" name="status" onchange="this.form.submit()"><option value="">All</option>@foreach(['pending', 'approved', 'rejected'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></form>
        <div class="table-responsive"><table class="cb-table stack">
            <thead><tr><th>Sender</th><th>Account</th><th>Purpose</th><th>Status</th><th>Decide</th></tr></thead>
            <tbody>
            @forelse($senders as $s)
                <tr>
                    <td data-label="Sender" class="fw-semibold at-mono">{{ $s->sender_id }}</td>
                    <td data-label="Account">{{ $s->user?->name }}<div class="small text-muted">{{ $s->user?->email }}</div></td>
                    <td data-label="Purpose" class="small">{{ $s->purpose }}</td>
                    <td data-label="Status"><span class="badge bg-{{ ['approved' => 'success', 'rejected' => 'danger'][$s->status] ?? 'warning' }}-subtle text-{{ ['approved' => 'success', 'rejected' => 'danger'][$s->status] ?? 'warning' }}">{{ ucfirst($s->status) }}</span>@if($s->reviewer)<div class="small text-muted">{{ $s->reviewer->name }}, {{ $s->reviewed_at?->format('j M') }}</div>@endif</td>
                    <td data-label="Decide">
                        <form method="POST" action="{{ route('admin.sender-ids.decide', $s) }}" class="d-flex gap-1 flex-wrap">@csrf
                            <input class="form-control form-control-sm" style="max-width:180px" name="note" placeholder="Note (optional)" value="{{ $s->note }}">
                            <button class="btn btn-sm btn-soft-success" name="status" value="approved">Approve</button>
                            <button class="btn btn-sm btn-soft-danger" name="status" value="rejected">Reject</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty-state"><i class="ri-message-2-line"></i><h6>No requests</h6></div></td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="p-3">{{ $senders->links() }}</div>
    </x-cb.card>
</div></div></div>
@endsection
