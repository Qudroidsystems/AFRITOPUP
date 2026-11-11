@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Settings" icon="ri-settings-4-line" subtitle="Platform settings. Changes here take effect straight away." />
    @include('settings._tabs')
    @include('partials.flash')

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
    <x-cb.card title="Going live checklist" icon="ri-checkbox-multiple-line">
        <ol class="small mb-0 ps-3">
            <li>Enter the live keys above and switch VTpass to <strong>Live</strong>. Fund your VTpass and Termii wallets.</li>
            <li>Under <a href="{{ route('admin.services.index') }}">Services &amp; Pricing</a>, set each service's provider to VTpass (bulk SMS to Termii) and press <strong>Load plans from VTpass</strong> on the data, cable and exam services. Review prices.</li>
            <li>Set the Paystack live keys and webhook under Payment gateways.</li>
            <li>Make sure the scheduler runs every minute (<code>php artisan schedule:run</code>) so stuck purchases are re-checked.</li>
        </ol>
        @if($last)<div class="small text-muted mt-2">Last changed {{ $last->updated_at->diffForHumans() }}{{ $last->editor ? ' by ' . $last->editor->name : '' }}.</div>@endif
    </x-cb.card>
</div></div></div>
@endsection
