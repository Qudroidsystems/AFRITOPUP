@extends($shell ?? 'layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    @include('topup.partials.styles')
    <x-cb.hero title="Resellers & API" icon="ri-code-box-line" subtitle="Sell airtime, data, TV and power from your own app or website, at your reseller prices.">
        <x-slot:actions><a href="{{ route('topup.developer.docs') }}" class="cb-hero-btn"><i class="ri-book-2-line"></i>API documentation</a></x-slot:actions>
    </x-cb.hero>
    @include('partials.flash')

    @if($newKey)
        <div class="cb-banner warning"><i class="ri-key-2-line"></i><div>
            <div class="fw-semibold mb-1">Your new API key — copy it now, it will not be shown again:</div>
            <div class="input-group"><input class="form-control at-mono" id="newKey" value="{{ $newKey }}" readonly>
                <button class="btn btn-dark" type="button" onclick="navigator.clipboard.writeText(document.getElementById('newKey').value); this.textContent='Copied'">Copy</button></div>
        </div></div>
    @endif

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card title="API keys" icon="ri-key-2-line" :count="$keys->whereNull('revoked_at')->count()" :flush="true">
                @if($keys->isEmpty())
                    <div class="empty-state"><i class="ri-key-2-line"></i><h6>No keys yet</h6><p>Create a key to start calling the API.</p></div>
                @else
                    <div class="table-responsive"><table class="cb-table stack">
                        <thead><tr><th>Name</th><th>Key</th><th>Webhook</th><th>Last used</th><th></th></tr></thead>
                        <tbody>
                        @foreach($keys as $k)
                            <tr class="{{ $k->revoked_at ? 'opacity-50' : '' }}">
                                <td data-label="Name" class="fw-semibold">{{ $k->name }}@if($k->revoked_at)<div class="small text-danger">Revoked {{ $k->revoked_at->diffForHumans() }}</div>@endif</td>
                                <td data-label="Key" class="at-mono">{{ $k->prefix }}…</td>
                                <td data-label="Webhook" class="small">{{ $k->webhook_url ?: '—' }}@if($k->allowed_ips)<div class="text-muted">IPs: {{ implode(', ', $k->allowed_ips) }}</div>@endif</td>
                                <td data-label="Last used" class="small">{{ $k->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                                <td class="text-end text-nowrap">
                                    @unless($k->revoked_at)
                                        <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#edit{{ $k->id }}">Edit</button>
                                        <form method="POST" action="{{ route('topup.developer.keys.revoke', $k) }}" class="d-inline" onsubmit="return confirm('Revoke this key? Apps using it will stop working at once.')">@csrf<button class="btn btn-sm btn-soft-danger">Revoke</button></form>
                                    @endunless
                                </td>
                            </tr>
                            @unless($k->revoked_at)
                            <tr class="collapse" id="edit{{ $k->id }}"><td colspan="5">
                                <form method="POST" action="{{ route('topup.developer.keys.update', $k) }}" class="row g-2 align-items-end">@csrf @method('PUT')
                                    <div class="col-md-5"><label class="form-label small">Webhook URL</label><input class="form-control form-control-sm" name="webhook_url" value="{{ $k->webhook_url }}" placeholder="https://"></div>
                                    <div class="col-md-5"><label class="form-label small">Allowed IPs (optional)</label><input class="form-control form-control-sm" name="allowed_ips" value="{{ implode(', ', (array) $k->allowed_ips) }}"></div>
                                    <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Save</button></div>
                                </form>
                                <div class="small mt-2">Webhook secret (for checking <code>X-AfriTopup-Signature</code>): <span class="at-mono">{{ $k->webhook_secret }}</span></div>
                            </td></tr>
                            @endunless
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </x-cb.card>
        </div>
        <div class="col-xl-4">
            <x-cb.card title="New key" icon="ri-add-line">
                <form method="POST" action="{{ route('topup.developer.keys.store') }}">@csrf
                    <div class="mb-2"><label class="form-label fw-semibold">Name</label><input class="form-control" name="name" placeholder="e.g. My shop website" required maxlength="80"></div>
                    <div class="mb-2"><label class="form-label fw-semibold">Webhook URL <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" name="webhook_url" placeholder="https://example.com/afritopup-webhook"></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Allowed IPs <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" name="allowed_ips" placeholder="203.0.113.10, 203.0.113.11"><div class="form-text">Leave empty to allow any IP.</div></div>
                    <button class="btn btn-success w-100">Create key</button>
                </form>
            </x-cb.card>
            <x-cb.card title="Your prices" icon="ri-price-tag-3-line">
                <p class="small mb-0">You buy at <strong>{{ auth()->user()->tier()?->name ?? 'standard' }}</strong> prices, paid from your wallet ({{ auth()->user()->wallet()->formatted_balance }}). Keep it funded — API purchases fail with <code>insufficient_funds</code> when it runs low.</p>
            </x-cb.card>
        </div>
    </div>
</div></div></div>
@endsection
