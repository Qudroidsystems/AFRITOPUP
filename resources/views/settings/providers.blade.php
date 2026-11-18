@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Settings" icon="ri-settings-4-line" subtitle="Platform settings. Changes here take effect straight away." />
    @include('settings._tabs')
    @include('partials.flash')

    {{-- ── Vendor routing: which vendor serves each category ─────────────────── --}}
    <x-cb.card title="Vendor routing" icon="ri-route-line">
        <x-slot:tools>
            <form method="GET" class="d-flex align-items-center gap-2"><select class="form-select form-select-sm" name="country" onchange="this.form.submit()">@foreach($countries as $c)<option value="{{ $c->code }}" @selected($country === $c->code)>{{ $c->name }}</option>@endforeach</select></form>
        </x-slot:tools>
        <p class="small text-muted">Pick the vendor that fulfils each kind of purchase. Switching moves every service of that category in {{ $country }} to the new vendor straight away (staff-fulfilled services such as school fees stay as they are). Purchases already in progress stay with the vendor they were sent to. For plans (data, cable, exam PINs) the new vendor's plans are imported and matched to yours; review prices under <a href="{{ route('admin.services.index', ['country' => $country]) }}">Services &amp; Pricing</a> afterwards.</p>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr><th>Category</th><th>Now served by</th><th style="min-width: 520px">Switch to</th></tr></thead>
                <tbody>
                @foreach($categories as $cat => $row)
                    <tr>
                        <td class="fw-semibold">{{ $row['name'] }}</td>
                        <td>
                            @forelse($row['current'] as $k => $n)
                                <span class="badge {{ $k === 'sandbox' ? 'bg-warning text-dark' : 'bg-primary-subtle text-primary' }} me-1">{{ $names[$k] ?? $k }} · {{ $n }}</span>
                            @empty
                                <span class="text-muted small">No services</span>
                            @endforelse
                        </td>
                        <td>
                            <form method="POST" action="{{ route('portal-settings.providers.routing') }}" class="row g-2 align-items-center"
                                  onsubmit="return confirm('Move all {{ $row['name'] }} services in {{ $country }} to ' + this.vendor.options[this.vendor.selectedIndex].text.replace(/ — .*/, '') + '?');">@csrf
                                <input type="hidden" name="category" value="{{ $cat }}"><input type="hidden" name="country" value="{{ $country }}">
                                <div class="col-md-5"><select class="form-select form-select-sm" name="vendor" required>
                                    @foreach($row['options'] as $k => $o)<option value="{{ $k }}" @disabled(!$o['ready']) @selected(count($row['current']) === 1 && isset($row['current'][$k]))>{{ $o['name'] }}{{ $o['ready'] ? '' : ' — keys not set' }}</option>@endforeach
                                </select></div>
                                @if(config('pericharge.categories.' . $cat . '.product'))
                                    <div class="col-md-2"><div class="input-group input-group-sm" title="New plans are priced at the vendor's cost plus this percentage"><input class="form-control" type="number" name="markup" min="0" max="100" step="0.5" value="5"><span class="input-group-text">%</span></div></div>
                                    <div class="col-md-3 small">
                                        <div class="form-check"><input class="form-check-input" type="checkbox" name="sync" value="1" id="sy{{ $cat }}" checked><label class="form-check-label" for="sy{{ $cat }}">Import plans</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" name="activate" value="1" id="ac{{ $cat }}"><label class="form-check-label" for="ac{{ $cat }}">Switch new plans on</label></div>
                                    </div>
                                @else
                                    <div class="col-md-5"></div>
                                @endif
                                <div class="col-md-2"><button class="btn btn-sm btn-primary w-100"><i class="ri-arrow-left-right-line me-1"></i>Switch</button></div>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-cb.card>


    <div class="row g-3">
        @foreach($cards as $key => $p)
            <div class="col-xl-6">
                <x-cb.card :title="$p['name']" icon="ri-plug-line">
                    <x-slot:tools>
                        @if($p['configured'])<span class="badge bg-success">Keys set</span>@else<span class="badge bg-secondary">Not set</span>@endif
                        @if($key === 'vtpass')<span class="badge {{ ($p['c']['mode'] ?? 'sandbox') === 'live' ? 'bg-danger' : 'bg-warning text-dark' }}">{{ ($p['c']['mode'] ?? 'sandbox') === 'live' ? 'Live' : 'Sandbox' }}</span>@endif
                    </x-slot:tools>
                    <form method="POST" action="{{ route('portal-settings.providers.save', $key) }}" autocomplete="off">@csrf
                        @if($key === 'vtpass')
                            <p class="small text-muted">Airtime, data, cable TV, electricity and exam PINs. Keys are on your VTpass dashboard under <strong>Settings → API Keys</strong>. Use the sandbox keys from <a href="https://sandbox.vtpass.com" target="_blank" rel="noopener">sandbox.vtpass.com</a> to test.</p>
                            <div class="mb-2"><label class="form-label fw-semibold">Mode</label>
                                <div class="d-flex gap-3">@foreach(['sandbox' => 'Sandbox', 'live' => 'Live (real money)'] as $v => $l)<div class="form-check"><input class="form-check-input" type="radio" name="mode" value="{{ $v }}" id="vm{{ $v }}" @checked(($p['c']['mode'] ?? 'sandbox') === $v)><label class="form-check-label" for="vm{{ $v }}">{{ $l }}</label></div>@endforeach</div></div>
                            <div class="mb-2"><label class="form-label fw-semibold">API key</label><input class="form-control" type="password" name="api_key" placeholder="{{ $p['masked']['api_key'] ?? 'Paste the API key' }}" autocomplete="new-password">
                                @if($p['saved']['api_key'])<div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="clear_api_key" value="1" id="vca"><label class="form-check-label small" for="vca">Remove the saved key</label></div>@endif</div>
                            <div class="mb-2"><label class="form-label fw-semibold">Public key</label><input class="form-control" name="public_key" value="{{ $p['c']['public_key'] ?? '' }}"></div>
                            <div class="mb-3"><label class="form-label fw-semibold">Secret key</label><input class="form-control" type="password" name="secret_key" placeholder="{{ $p['masked']['secret_key'] ?? 'Paste the secret key' }}" autocomplete="new-password">
                                @if($p['saved']['secret_key'])<div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="clear_secret_key" value="1" id="vcs"><label class="form-check-label small" for="vcs">Remove the saved key</label></div>@endif</div>
                        @elseif($key === 'reloadly')
                            <p class="small text-muted">Airtime and data in Ghana, Kenya and 150+ countries. Create API credentials at <a href="https://www.reloadly.com" target="_blank" rel="noopener">reloadly.com</a> → Developers. Then use <strong>Import networks</strong> on a country under Services &amp; Pricing.</p>
                            <div class="mb-2"><label class="form-label fw-semibold">Mode</label>
                                <div class="d-flex gap-3">@foreach(['sandbox' => 'Sandbox', 'live' => 'Live (real money)'] as $v => $l)<div class="form-check"><input class="form-check-input" type="radio" name="mode" value="{{ $v }}" id="rm{{ $v }}" @checked(($p['c']['mode'] ?? 'sandbox') === $v)><label class="form-check-label" for="rm{{ $v }}">{{ $l }}</label></div>@endforeach</div></div>
                            <div class="mb-2"><label class="form-label fw-semibold">Client ID</label><input class="form-control" name="client_id" value="{{ $p['c']['client_id'] ?? '' }}"></div>
                            <div class="mb-3"><label class="form-label fw-semibold">Client secret</label><input class="form-control" type="password" name="client_secret" placeholder="{{ $p['masked']['client_secret'] ?? 'Paste the client secret' }}" autocomplete="new-password">
                                @if($p['saved']['client_secret'] ?? false)<div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="clear_client_secret" value="1" id="rcs"><label class="form-check-label small" for="rcs">Remove the saved secret</label></div>@endif</div>
                        @elseif($key === 'clubkonnect')
                            <p class="small text-muted">Airtime, data, cable TV, electricity and WAEC PINs. Your UserID and API key are on <a href="https://www.nellobytesystems.com" target="_blank" rel="noopener">nellobytesystems.com</a> → API. Add this server's IP address to the allowed IPs there.</p>
                            <div class="mb-2"><label class="form-label fw-semibold">UserID</label><input class="form-control" name="user_id" value="{{ $p['c']['user_id'] ?? '' }}" placeholder="CK100…"></div>
                            <div class="mb-3"><label class="form-label fw-semibold">API key</label><input class="form-control" type="password" name="api_key" placeholder="{{ $p['masked']['api_key'] ?? 'Paste the API key' }}" autocomplete="new-password">
                                @if($p['saved']['api_key'])<div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="clear_api_key" value="1" id="cca"><label class="form-check-label small" for="cca">Remove the saved key</label></div>@endif</div>
                        @else
                            <p class="small text-muted">Bulk and business SMS. Your API key is on the Termii dashboard. Sender IDs must be registered with Termii before customers can use them.</p>
                            <div class="mb-2"><label class="form-label fw-semibold">API key</label><input class="form-control" type="password" name="api_key" placeholder="{{ $p['masked']['api_key'] ?? 'Paste the API key' }}" autocomplete="new-password">
                                @if($p['saved']['api_key'])<div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="clear_api_key" value="1" id="tca"><label class="form-check-label small" for="tca">Remove the saved key</label></div>@endif</div>
                            <div class="row g-2 mb-3">
                                <div class="col-md-4"><label class="form-label fw-semibold">Default sender</label><input class="form-control" name="sender_id" value="{{ $p['c']['sender_id'] ?? '' }}" maxlength="11"></div>
                                <div class="col-md-3"><label class="form-label fw-semibold">Route</label><select class="form-select" name="channel">@foreach(['generic' => 'Generic', 'dnd' => 'DND'] as $v => $l)<option value="{{ $v }}" @selected(($p['c']['channel'] ?? 'generic') === $v)>{{ $l }}</option>@endforeach</select></div>
                                <div class="col-md-5"><label class="form-label fw-semibold">Base URL</label><input class="form-control" name="base_url" value="{{ $p['c']['base_url'] ?? '' }}"></div>
                            </div>
                        @endif
                        <div class="d-flex gap-2"><button class="btn btn-primary">Save</button></div>
                    </form>
                    <form method="POST" action="{{ route('portal-settings.providers.test', $key) }}" class="mt-2">@csrf<button class="btn btn-soft-secondary btn-sm"><i class="ri-plug-line me-1"></i>Test keys (read balance)</button></form>
                    <div class="small text-muted mt-2">{{ $p['source'] === 'portal' ? 'Using values saved here (secrets encrypted).' : 'Using values from .env.' }}</div>
                </x-cb.card>
            </div>
        @endforeach
    </div>
    {{-- ── Custom vendors (common VTU API) ───────────────────────────────────── --}}
    <div class="d-flex align-items-center justify-content-between mt-2 mb-2">
        <h6 class="mb-0"><i class="ri-plug-2-line me-1"></i>Other vendors</h6>
        <button class="btn btn-sm btn-soft-primary" type="button" data-bs-toggle="collapse" data-bs-target="#vendor-new"><i class="ri-add-line me-1"></i>Add a vendor</button>
    </div>
    <p class="small text-muted">Most Nigerian data-reseller sites (Husmodata, Alrahuz, Datastation and the many sites built on the same script) share one API: a token, <code>/api/topup/</code>, <code>/api/data/</code>, <code>/api/cablesub/</code> and <code>/api/billpayment/</code>. Add any of them here — as many as you like — then switch categories to them above.</p>
    @php($blank = ['slug' => '', 'name' => '', 'base_url' => '', 'ids' => [], 'configured' => false, 'masked' => null, 'key' => null])
    <div class="row g-3">
        @foreach(array_merge($custom, ['__new' => $blank]) as $slug => $v)
            @php($isNew = $slug === '__new')
            <div class="col-xl-6 {{ $isNew ? 'collapse' : '' }}" @if($isNew) id="vendor-new" @endif>
                <x-cb.card :title="$isNew ? 'New vendor' : $v['name']" icon="ri-plug-2-line">
                    @unless($isNew)
                        <x-slot:tools>@if($v['configured'])<span class="badge bg-success">Token set</span>@else<span class="badge bg-secondary">No token</span>@endif <span class="badge bg-light text-dark at-mono">{{ $v['key'] }}</span></x-slot:tools>
                    @endunless
                    <form method="POST" action="{{ route('portal-settings.providers.custom.save') }}" autocomplete="off">@csrf
                        <input type="hidden" name="slug" value="{{ $isNew ? '' : $slug }}">
                        <div class="row g-2 mb-2">
                            <div class="col-md-5"><label class="form-label fw-semibold">Name</label><input class="form-control" name="name" value="{{ $v['name'] }}" maxlength="60" required placeholder="e.g. Husmodata"></div>
                            <div class="col-md-7"><label class="form-label fw-semibold">Website (API base URL)</label><input class="form-control" name="base_url" value="{{ $v['base_url'] }}" required placeholder="https://www.husmodata.com"></div>
                        </div>
                        <div class="mb-2"><label class="form-label fw-semibold">API token</label><input class="form-control" type="password" name="token" placeholder="{{ $v['masked'] ?? 'Paste the token from your profile on the vendor site' }}" autocomplete="new-password">
                            @if(!$isNew && $v['masked'])<div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="clear_token" value="1" id="ct{{ $slug }}"><label class="form-check-label small" for="ct{{ $slug }}">Remove the saved token</label></div>@endif</div>
                        <details class="mb-3"><summary class="small fw-semibold">IDs this vendor uses <span class="text-muted fw-normal">(only change if the vendor's documentation differs)</span></summary>
                            @foreach(['network' => 'Networks', 'cable' => 'Cable TV', 'disco' => 'Electricity'] as $type => $label)
                                <div class="small fw-semibold mt-2">{{ $label }}</div>
                                <div class="row g-1">@foreach($defaultIds[$type] as $what => $def)
                                    <div class="col-4 col-md-3"><div class="input-group input-group-sm"><span class="input-group-text">{{ $what }}</span><input class="form-control" name="ids[{{ $type }}][{{ $what }}]" value="{{ $v['ids'][$type][$what] ?? '' }}" placeholder="{{ $def }}" maxlength="20"></div></div>
                                @endforeach</div>
                            @endforeach
                        </details>
                        <button class="btn btn-primary">{{ $isNew ? 'Add vendor' : 'Save' }}</button>
                    </form>
                    @unless($isNew)
                        <div class="d-flex gap-2 mt-2">
                            <form method="POST" action="{{ route('portal-settings.providers.test', $v['key']) }}">@csrf<button class="btn btn-soft-secondary btn-sm"><i class="ri-plug-line me-1"></i>Test token (read balance)</button></form>
                            <form method="POST" action="{{ route('portal-settings.providers.custom.delete', $slug) }}" onsubmit="return confirm('Remove {{ $v['name'] }}?');">@csrf<button class="btn btn-soft-danger btn-sm"><i class="ri-delete-bin-line me-1"></i>Remove</button></form>
                        </div>
                    @endunless
                </x-cb.card>
            </div>
        @endforeach
    </div>

    <x-cb.card title="Going live checklist" icon="ri-checkbox-multiple-line">
        <ol class="small mb-0 ps-3">
            <li>Enter the live keys of the vendor(s) you use above (switch VTpass / Reloadly to <strong>Live</strong>) and fund your wallet with each vendor.</li>
            <li>Under <strong>Vendor routing</strong> above, switch each category to the vendor you want (bulk SMS to Termii). Plans are imported as you switch; review prices under <a href="{{ route('admin.services.index') }}">Services &amp; Pricing</a>. You can switch again at any time.</li>
            <li>Set the Paystack live keys and webhook under Payment gateways.</li>
            <li>Make sure the scheduler runs every minute (<code>php artisan schedule:run</code>) so stuck purchases are re-checked.</li>
        </ol>
        @if($last)<div class="small text-muted mt-2">Last changed {{ $last->updated_at->diffForHumans() }}{{ $last->editor ? ' by ' . $last->editor->name : '' }}.</div>@endif
    </x-cb.card>
</div></div></div>
@endsection
