@extends('layouts.account')

@php
    $name = $brand['name'] ?? 'AfriTopup';
    $agent = $conv->assignee;
    $txLabel = fn ($t) => trim(($t->service?->display_name ?? 'Payment') . ' · ' . \App\Support\Money::format($t->total, $t->currency));
@endphp

@push('styles')<link href="{{ asset('site/chat.css') }}?v={{ @filemtime(public_path('site/chat.css')) }}" rel="stylesheet">@endpush

@section('content')
<div class="a-page">
    <div class="ch-page">
        @include('support.partials.chat', [
            'id' => 'chat',
            'txnPicker' => true,
            'placeholder' => 'Type a message…',
            'head' => '<span class="ch-avatar">' . e(mb_substr($name, 0, 1)) . '</span><span class="flex-grow-1"><b>' . e($name) . ' Support</b><small data-agent><span class="ch-online"></span>' . ($agent ? e(\Illuminate\Support\Str::before($agent->name, ' ')) . ' is helping you' : 'We usually reply in a few minutes') . '</small></span>'
                . '<a href="' . route('site.page', 'faq') . '" class="btn btn-sm btn-light rounded-pill d-none d-sm-inline-flex" target="_blank"><i class="ri-question-line me-1"></i>Help centre</a>',
        ])

        <aside class="ch-side">
            <div class="a-card">
                <h2 class="h6 mb-1">About a payment?</h2>
                <p class="small mb-2" style="color: var(--s-muted)">Tap one to attach it — we'll see all the details.</p>
                @forelse($recentTxns as $t)
                    <button type="button" class="ch-pick" data-attach-txn="{{ $t->reference }}" data-label="{{ $txLabel($t) }}">
                        @include('topup.partials.service-icon', ['s' => $t->service, 'size' => 34])
                        <span class="flex-grow-1 min-w-0"><b>{{ $t->service?->display_name ?? 'Payment' }} · {{ $t->recipient }}</b><small>{{ \App\Support\Money::format($t->total, $t->currency) }} · {{ $t->created_at->format('j M, g:ia') }} · {{ $t->status_label }}</small></span>
                        <i class="ri-add-line" style="color: var(--s-muted)"></i>
                    </button>
                @empty
                    <div class="small" style="color: var(--s-muted)">No payments yet.</div>
                @endforelse
            </div>
            <div class="a-card">
                <h2 class="h6 mb-2">Quick answers</h2>
                @foreach(array_slice(\App\Http\Controllers\Site\SiteController::faqs(), 0, 4) as [$q, $a])
                    <details class="mb-2"><summary class="small fw-semibold" style="cursor:pointer;color:var(--s-ink)">{{ $q }}</summary><p class="small mt-1 mb-0" style="color: var(--s-muted)">{{ $a }}</p></details>
                @endforeach
            </div>
        </aside>
    </div>
</div>

{{-- phone: pick a payment --}}
<div class="modal fade" id="pickTxn" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content" style="border-radius: 20px">
        <div class="modal-header"><h5 class="modal-title">Attach a payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            @forelse($recentTxns as $t)
                <button type="button" class="ch-pick" data-bs-dismiss="modal" data-attach-txn="{{ $t->reference }}" data-label="{{ $txLabel($t) }}">
                    @include('topup.partials.service-icon', ['s' => $t->service, 'size' => 34])
                    <span class="flex-grow-1 min-w-0"><b>{{ $t->service?->display_name ?? 'Payment' }} · {{ $t->recipient }}</b><small>{{ \App\Support\Money::format($t->total, $t->currency) }} · {{ $t->created_at->format('j M') }} · {{ $t->status_label }}</small></span>
                </button>
            @empty
                <div class="small text-muted">No payments yet.</div>
            @endforelse
        </div>
    </div></div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('site/chat.js') }}?v={{ @filemtime(public_path('site/chat.js')) }}"></script>
<script>
(function () {
    var root = document.getElementById('chat');
    var chat = new SupportChat(root, {
        messagesUrl: @json(route('support.messages')),
        sendUrl: @json(route('support.send')),
        typingUrl: @json(route('support.typing')),
        txnUrl: @json(route('topup.transactions.show', '__REF__')),
        onMeta: function (c) {
            var el = root.querySelector('[data-agent]');
            if (el && c.agent) el.innerHTML = '<span class="ch-online"></span>' + c.agent.name.replace(/[<>&]/g, '') + ' is helping you';
            document.querySelectorAll('[data-support-badge]').forEach(function (b) { b.hidden = true; });
        }
    });
    // The side list and the phone modal attach payments.
    document.addEventListener('click', function (e) {
        var p = e.target.closest('[data-attach-txn]');
        if (p && !root.contains(p)) chat.setTransaction(p.dataset.attachTxn, p.dataset.label);
    });
    @if($about)
        chat.setTransaction(@json($about->reference), @json($txLabel($about)));
        root.querySelector('[data-chat-input]').placeholder = 'What went wrong with this payment?';
    @endif
})();
</script>
@endpush
