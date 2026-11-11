@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="SMS Sender IDs" icon="ri-message-2-line" subtitle="Send bulk SMS under your own business name. Networks must approve each name first (usually 1–3 working days)." />
    @include('partials.flash')
    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card title="Your sender names" icon="ri-list-check" :flush="true">
                @if($senders->isEmpty())
                    <div class="empty-state"><i class="ri-message-2-line"></i><h6>None yet</h6><p>Until one is approved, your messages go out as {{ config('afritopup.providers.termii.sender_id', 'AfriTopup') }}.</p></div>
                @else
                    <div class="table-responsive"><table class="cb-table stack"><tbody>
                        @foreach($senders as $s)
                            <tr>
                                <td data-label="Sender" class="fw-semibold">{{ $s->sender_id }}<div class="small text-muted">{{ $s->purpose }}</div></td>
                                <td data-label="Status"><span class="badge bg-{{ ['approved' => 'success', 'rejected' => 'danger'][$s->status] ?? 'warning' }}-subtle text-{{ ['approved' => 'success', 'rejected' => 'danger'][$s->status] ?? 'warning' }}">{{ ucfirst($s->status) }}</span>@if($s->note)<div class="small text-muted">{{ $s->note }}</div>@endif</td>
                                <td data-label="Requested" class="small text-muted">{{ $s->created_at->format('j M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody></table></div>
                @endif
            </x-cb.card>
        </div>
        <div class="col-xl-4">
            <x-cb.card title="Request a sender name" icon="ri-add-line">
                <form method="POST" action="{{ route('topup.sender-ids.request') }}">@csrf
                    <div class="mb-2"><label class="form-label fw-semibold">Sender name</label><input class="form-control" name="sender_id" maxlength="11" required placeholder="MyShop"><div class="form-text">3–11 letters, numbers or spaces.</div></div>
                    <div class="mb-3"><label class="form-label fw-semibold">What will you send?</label><textarea class="form-control" name="purpose" rows="3" maxlength="255" required placeholder="Order updates and promotions for our customers"></textarea></div>
                    <button class="btn btn-success w-100">Send request</button>
                </form>
            </x-cb.card>
        </div>
    </div>
</div></div></div>
@endsection
