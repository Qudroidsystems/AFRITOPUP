@extends('layouts.master')

@php
    use App\Support\Money;
    $initials = fn ($n) => collect(explode(' ', trim((string) $n)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->join('');
@endphp

@push('styles')<link href="{{ asset('site/chat.css') }}?v={{ @filemtime(public_path('site/chat.css')) }}" rel="stylesheet">@endpush

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Support chat" icon="ri-customer-service-2-line" subtitle="Answer customers in real time. Replies reach them on the website and as a push notification in the app." />
    @include('partials.flash')

    <div class="inbox {{ $conv ? 'has-chat' : '' }}" id="inbox">
        {{-- chats --}}
        <div class="inbox-list">
            <div class="p-2 border-bottom">
                <div class="position-relative"><i class="ri-search-line position-absolute text-muted" style="left:12px;top:50%;transform:translateY(-50%)"></i>
                    <input class="form-control form-control-sm rounded-pill" style="padding-left:34px" placeholder="Search name, e-mail or phone" data-inbox-search></div>
            </div>
            <div class="inbox-tabs" data-inbox-tabs>
                @foreach(['open' => 'Open', 'mine' => 'Mine', 'unassigned' => 'Unassigned', 'closed' => 'Solved', 'all' => 'All'] as $k => $l)
                    <a href="?filter={{ $k }}" data-filter="{{ $k }}" class="{{ $filter === $k ? 'is-on' : '' }}">{{ $l }}<span data-count="{{ $k }}"></span></a>
                @endforeach
            </div>
            <div class="inbox-items" data-inbox-items><div class="text-center text-muted small p-4"><span class="spinner-border spinner-border-sm"></span></div></div>
        </div>

        {{-- thread --}}
        @if($conv)
            @include('support.partials.chat', [
                'id' => 'chat',
                'placeholder' => 'Reply to ' . \Illuminate\Support\Str::before($customer?->name ?? 'customer', ' ') . '…',
                'head' => '<a href="' . route('admin.support.index', ['filter' => $filter]) . '" class="btn btn-sm btn-light d-md-none"><i class="ri-arrow-left-line"></i></a>'
                    . '<span class="ch-avatar">' . ($customer?->avatar ? '<img src="' . e($customer->avatar_url) . '" alt="">' : e($initials($customer?->name))) . '</span>'
                    . '<span class="flex-grow-1 min-w-0"><b>' . e($customer?->name ?? 'Deleted user') . '</b><small>' . e($customer?->email) . ' · ' . e($customer?->user_type_label) . '</small></span>'
                    . ($conv->isOpen()
                        ? '<form method="POST" action="' . route('admin.support.close', $conv) . '">' . csrf_field() . '<button class="btn btn-sm btn-soft-success rounded-pill"><i class="ri-check-double-line me-1"></i>Mark solved</button></form>'
                        : '<form method="POST" action="' . route('admin.support.reopen', $conv) . '">' . csrf_field() . '<button class="btn btn-sm btn-light rounded-pill"><i class="ri-refresh-line me-1"></i>Re-open</button></form>'),
            ])
        @else
            <div class="inbox-blank"><i class="ri-chat-smile-3-line"></i><b>Pick a chat</b><span class="small">New customer messages appear on the left as they arrive.</span></div>
        @endif

        {{-- customer --}}
        <aside class="inbox-side">
            @if($customer)
                <div class="text-center mb-3">
                    <div class="ch-avatar mx-auto mb-2" style="width:56px;height:56px;border-radius:18px;font-size:1.1rem">@if($customer->avatar)<img src="{{ $customer->avatar_url }}" alt="">@else{{ $initials($customer->name) }}@endif</div>
                    <b class="d-block">{{ $customer->name }}</b>
                    <small class="text-muted d-block">{{ $customer->email }}</small>
                    <small class="text-muted d-block">{{ $customer->phone_number }}</small>
                    <span class="badge bg-light text-dark mt-1">{{ $customer->user_type_label }}</span>
                    @if(!$customer->hasVerifiedEmail())<span class="badge bg-warning-subtle text-warning mt-1">E-mail not confirmed</span>@endif
                </div>
                <div class="d-flex justify-content-between small border rounded-3 p-2 mb-3 bg-white">
                    <span class="text-muted">Wallet</span><b>{{ $wallet?->formatted_balance }}</b>
                </div>

                <form method="POST" action="{{ route('admin.support.assign', $conv) }}" class="mb-3">
                    @csrf
                    <label class="form-label small fw-semibold mb-1">Assigned to</label>
                    <div class="input-group input-group-sm">
                        <select name="agent" class="form-select">
                            <option value="">— Queue —</option>
                            @foreach($agents as $a)<option value="{{ $a->id }}" @selected($conv->assigned_to === $a->id)>{{ $a->name }}{{ $a->id === auth()->id() ? ' (me)' : '' }}</option>@endforeach
                        </select>
                        <button class="btn btn-light">Save</button>
                    </div>
                </form>

                <div class="d-flex justify-content-between align-items-center mb-1"><b class="small">Recent payments</b>
                    @can('View transactions')<a href="{{ route('admin.transactions.index', ['q' => $customer->email]) }}" class="small">All</a>@endcan</div>
                @forelse($recent as $t)
                    <a href="{{ auth()->user()->can('View transactions') ? route('admin.transactions.show', $t) : '#' }}" class="d-flex align-items-center gap-2 py-2 border-bottom text-reset text-decoration-none">
                        @include('topup.partials.service-icon', ['s' => $t->service, 'size' => 30])
                        <span class="flex-grow-1 min-w-0"><span class="d-block small fw-semibold text-truncate">{{ $t->service?->display_name }} · {{ $t->recipient }}</span>
                            <span class="d-block text-muted" style="font-size:.72rem">{{ $t->reference }} · {{ $t->created_at->format('j M, g:ia') }}</span></span>
                        <span class="text-end"><span class="d-block small fw-bold">{{ Money::format($t->total, $t->currency) }}</span>
                            <span class="badge bg-{{ $t->status_color }}-subtle text-{{ $t->status_color }}" style="font-size:.62rem">{{ $t->status_label }}</span></span>
                    </a>
                @empty
                    <div class="small text-muted">No payments yet.</div>
                @endforelse

                @can('View user')<a href="{{ route('profile.settings', $customer->id) }}" class="btn btn-sm btn-light w-100 mt-3"><i class="ri-user-line me-1"></i>Open profile</a>@endcan
            @else
                <div class="text-muted small">Customer details show here.</div>
            @endif
        </aside>
    </div>
</div></div></div>
@endsection

@push('scripts')
<script src="{{ asset('site/chat.js') }}?v={{ @filemtime(public_path('site/chat.js')) }}"></script>
<script>
(function () {
    var listUrl = @json(route('admin.support.list'));
    var current = @json($conv?->id);
    var filter = @json($filter);
    var q = '';
    var box = document.querySelector('[data-inbox-items]');
    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]; }); };
    var ini = function (n) { return String(n || '?').trim().split(/\s+/).slice(0, 2).map(function (p) { return p[0].toUpperCase(); }).join(''); };
    var ago = function (iso) {
        if (!iso) return ''; var s = (Date.now() - new Date(iso)) / 1000;
        if (s < 60) return 'now'; if (s < 3600) return Math.floor(s / 60) + 'm'; if (s < 86400) return Math.floor(s / 3600) + 'h';
        return new Date(iso).toLocaleDateString([], { day: 'numeric', month: 'short' });
    };
    var lastTotal = null;

    function render(rows) {
        if (!rows.length) { box.innerHTML = '<div class="text-center text-muted small p-4">No chats here.</div>'; return; }
        box.innerHTML = rows.map(function (c) {
            return '<a class="inbox-item ' + (c.id === current ? 'is-on ' : '') + (c.unread ? 'is-unread' : '') + '" href="' + c.url + '?filter=' + filter + '">'
                + '<span class="ch-avatar">' + (c.avatar ? '<img src="' + esc(c.avatar) + '" alt="">' : esc(ini(c.name))) + '</span>'
                + '<span class="inbox-item-main"><b><span class="text-truncate">' + esc(c.name) + '</span><time>' + ago(c.time) + '</time></b>'
                + '<small>' + (c.typing ? '<em class="text-success">typing…</em>' : (c.from_staff ? 'You: ' : '') + esc(c.preview || '')) + '</small>'
                + '<small style="font-size:.7rem">' + (c.status === 'closed' ? '✓ Solved' : (c.agent ? esc(c.agent) : 'Unassigned')) + '</small></span>'
                + (c.unread ? '<span class="inbox-badge">' + c.unread + '</span>' : '') + '</a>';
        }).join('');
    }

    function refresh() {
        var u = new URL(listUrl, location.href); u.searchParams.set('filter', filter); if (q) u.searchParams.set('q', q);
        fetch(u, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
            render(j.conversations);
            Object.keys(j.counts).forEach(function (k) { var el = document.querySelector('[data-count="' + k + '"]'); if (el) el.textContent = j.counts[k] ? ' ' + j.counts[k] : ''; });
            document.querySelectorAll('[data-support-waiting]').forEach(function (b) { var n = j.conversations.filter(function (c) { return c.unread; }).length; b.textContent = n; b.style.display = n ? '' : 'none'; });
            if (lastTotal !== null && j.counts.unread > lastTotal && document.visibilityState !== 'visible') { document.title = '(' + j.counts.unread + ') Support chat'; }
            if (document.visibilityState === 'visible') document.title = 'Support chat';
            lastTotal = j.counts.unread;
        }).catch(function () {});
    }
    refresh();
    setInterval(function () { refresh(); }, 5000);
    window.addEventListener('afri:support', refresh);

    var t;
    document.querySelector('[data-inbox-search]').addEventListener('input', function (e) { clearTimeout(t); t = setTimeout(function () { q = e.target.value.trim(); refresh(); }, 250); });
    document.querySelectorAll('[data-filter]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault(); filter = a.dataset.filter;
            document.querySelectorAll('[data-filter]').forEach(function (x) { x.classList.toggle('is-on', x === a); });
            refresh();
        });
    });

    @if($conv)
    new SupportChat(document.getElementById('chat'), {
        staff: true,
        messagesUrl: @json(route('admin.support.messages', $conv)),
        sendUrl: @json(route('admin.support.send', $conv)),
        typingUrl: @json(route('admin.support.typing', $conv)),
        txnUrl: @json(auth()->user()->can('View transactions') ? route('admin.transactions.index', ['q' => '__REF__']) : null),
        onSent: refresh,
    });
    @endif
})();
</script>
@endpush
