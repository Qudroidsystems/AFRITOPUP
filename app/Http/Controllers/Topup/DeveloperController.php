<?php

namespace App\Http\Controllers\Topup;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\SmsSenderId;
use Illuminate\Http\Request;

/** Resellers and businesses: API keys, webhook, docs. Anyone who buys: SMS sender ID requests. */
class DeveloperController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:Use reseller API')->except(['senderIds', 'requestSenderId']);
        $this->middleware('permission:Buy services')->only(['senderIds', 'requestSenderId']);
    }

    public function index(Request $request)
    {
        return view('topup.developer.index', [
            'pagetitle' => 'Resellers & API',
            'keys'      => $request->user()->apiKeys()->latest()->get(),
            'newKey'    => session('new_key'),
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate(['name' => 'required|string|max:80', 'webhook_url' => 'nullable|url|max:255', 'allowed_ips' => 'nullable|string|max:500']);
        if ($request->user()->apiKeys()->whereNull('revoked_at')->count() >= 5) return back()->with('error', 'You can have at most 5 active keys. Revoke one first.');

        [$key, $plain] = ApiKey::issue($request->user(), $d['name'], $d['webhook_url'] ?? null);
        $key->forceFill(['allowed_ips' => $this->ips($d['allowed_ips'] ?? '')])->save();

        return back()->with('new_key', $plain)->with('success', 'Key created. Copy it now — it will not be shown again.');
    }

    public function update(Request $request, ApiKey $key)
    {
        abort_unless($key->user_id === $request->user()->id, 404);
        $d = $request->validate(['webhook_url' => 'nullable|url|max:255', 'allowed_ips' => 'nullable|string|max:500']);
        $key->forceFill(['webhook_url' => $d['webhook_url'] ?? null, 'allowed_ips' => $this->ips($d['allowed_ips'] ?? '')])->save();

        return back()->with('success', 'Key updated.');
    }

    public function revoke(Request $request, ApiKey $key)
    {
        abort_unless($key->user_id === $request->user()->id, 404);
        $key->forceFill(['revoked_at' => now()])->save();

        return back()->with('success', 'Key revoked. Requests using it will now be refused.');
    }

    public function docs()
    {
        return view('topup.developer.docs', ['pagetitle' => 'API documentation']);
    }

    public function senderIds(Request $request)
    {
        return view('topup.developer.sender-ids', ['pagetitle' => 'SMS Sender IDs', 'senders' => $request->user()->senderIds()->latest()->get()]);
    }

    public function requestSenderId(Request $request)
    {
        $d = $request->validate([
            'sender_id' => ['required', 'string', 'min:3', 'max:11', 'regex:/^[A-Za-z0-9 ]+$/'],
            'purpose'   => 'required|string|max:255',
        ], ['sender_id.regex' => 'Use letters, numbers and spaces only.']);

        SmsSenderId::firstOrCreate(['user_id' => $request->user()->id, 'sender_id' => trim($d['sender_id'])], ['purpose' => $d['purpose'], 'status' => 'pending']);
        \App\Services\Messaging\PortalNotifier::toUsers(\App\Services\Messaging\PortalNotifier::userIdsWithPermission('Manage services'),
            'Sender ID request: ' . $d['sender_id'], $request->user()->name . ' — ' . $d['purpose'], route('admin.sender-ids.index'), 'approval');

        return back()->with('success', 'Request sent. We will let you know once the networks approve it.');
    }

    protected function ips(string $raw): ?array
    {
        $ips = collect(preg_split('/[\s,]+/', $raw))->filter(fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP))->values()->all();

        return $ips ?: null;
    }
}
