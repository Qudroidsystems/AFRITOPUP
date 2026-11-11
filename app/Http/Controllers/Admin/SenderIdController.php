<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsSenderId;
use App\Services\Messaging\PortalNotifier;
use Illuminate\Http\Request;

/** Approve or reject SMS sender names once the networks have registered them. */
class SenderIdController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:Manage services');
    }

    public function index(Request $request)
    {
        $q = SmsSenderId::with('user:id,name,email', 'reviewer:id,name')->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")->latest()
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s));

        return view('admin.sender-ids.index', ['pagetitle' => 'SMS Sender IDs', 'senders' => $q->paginate(30)->withQueryString()]);
    }

    public function decide(Request $request, SmsSenderId $sender)
    {
        $d = $request->validate(['status' => 'required|in:approved,rejected', 'note' => 'nullable|string|max:255']);
        $sender->update(['status' => $d['status'], 'note' => $d['note'] ?? null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

        PortalNotifier::toUsers([$sender->user_id], 'Sender ID ' . $sender->sender_id . ' ' . $d['status'],
            $d['status'] === 'approved' ? 'You can now send SMS from "' . $sender->sender_id . '".' : ($d['note'] ?? 'The networks did not accept this sender name.'),
            route('topup.sender-ids'), 'sms');

        return back()->with('success', 'Sender ID ' . $d['status'] . '.');
    }
}
