<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Models\User;
use App\Services\Messaging\PortalNotifier;
use App\Services\Support\SupportChat;
use Illuminate\Http\Request;

/** Admin: the support inbox (list of chats + the open chat + customer details). */
class SupportInboxController extends Controller
{
    use ChatPayload;

    public function __construct(protected SupportChat $chat) {}

    public function index(Request $request)
    {
        return $this->page($request, null);
    }

    public function show(Request $request, SupportConversation $conversation)
    {
        return $this->page($request, $conversation);
    }

    protected function page(Request $request, ?SupportConversation $conv)
    {
        $conv?->load('user', 'assignee');
        $customer = $conv?->user;

        return view('support.inbox', [
            'pagetitle' => 'Support chat',
            'conv'      => $conv,
            'customer'  => $customer,
            'wallet'    => $customer?->wallet(),
            'recent'    => $customer ? $customer->transactions()->with('service')->latest('id')->limit(6)->get() : collect(),
            'agents'    => User::whereIn('id', PortalNotifier::userIdsWithPermission(SupportChat::PERMISSION))->orderBy('name')->get(['id', 'name']),
            'filter'    => $request->query('filter', 'open'),
        ]);
    }

    /** The chat list (polled). */
    public function list(Request $request)
    {
        $me = $request->user();
        $filter = $request->query('filter', 'open');
        $q = SupportConversation::with('user:id,name,email,avatar,phone_number', 'assignee:id,name')->orderByDesc('last_message_at');

        match ($filter) {
            'mine'       => $q->where('status', 'open')->where('assigned_to', $me->id),
            'unassigned' => $q->where('status', 'open')->whereNull('assigned_to'),
            'closed'     => $q->where('status', 'closed'),
            'all'        => null,
            default      => $q->where('status', 'open'),
        };
        if ($s = trim((string) $request->query('q'))) {
            $q->whereHas('user', fn ($u) => $u->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")->orWhere('phone_number', 'like', "%$s%")));
        }

        $rows = $q->limit(80)->get()->map(fn (SupportConversation $c) => [
            'id'       => $c->id,
            'url'      => route('admin.support.show', $c),
            'name'     => $c->user?->name ?? 'Deleted user',
            'email'    => $c->user?->email,
            'avatar'   => $c->user?->avatar ? $c->user->avatar_url : null,
            'preview'  => $c->last_message_preview,
            'from_staff' => $c->last_from_staff,
            'unread'   => $c->staff_unread,
            'status'   => $c->status,
            'agent'    => $c->assignee?->name,
            'time'     => $c->last_message_at?->toIso8601String(),
            'typing'   => (bool) $this->chat->otherTyping($c, $me),
        ]);

        return response()->json([
            'conversations' => $rows,
            'counts' => [
                'open'       => SupportConversation::where('status', 'open')->count(),
                'mine'       => SupportConversation::where('status', 'open')->where('assigned_to', $me->id)->count(),
                'unassigned' => SupportConversation::where('status', 'open')->whereNull('assigned_to')->count(),
                'unread'     => $this->chat->staffUnreadTotal(),
            ],
        ]);
    }

    public function messages(Request $request, SupportConversation $conversation)
    {
        $me = $request->user();
        $msgs = $this->chat->messages($conversation, $request->integer('after') ?: null, $request->integer('before') ?: null);
        if ($request->boolean('seen')) $this->chat->markRead($conversation, $me);

        return response()->json(['messages' => $this->present($msgs, $me, true), 'conversation' => $this->conversationMeta($conversation->fresh('assignee'), $me, true)]);
    }

    public function send(Request $request, SupportConversation $conversation)
    {
        $me = $request->user();
        [$body, $file] = $this->validateMessage($request, null);
        $msg = $this->chat->send($conversation, $me, $body, $file);

        return response()->json(['message' => $this->present(collect([$msg->load('sender')]), $me, true)[0]], 201);
    }

    public function typing(Request $request, SupportConversation $conversation)
    {
        $this->chat->typing($conversation, $request->user());

        return response()->noContent();
    }

    public function close(Request $request, SupportConversation $conversation)
    {
        $this->chat->close($conversation, $request->user());

        return $this->back($request, 'Chat marked as solved.');
    }

    public function reopen(Request $request, SupportConversation $conversation)
    {
        $this->chat->reopen($conversation, $request->user());

        return $this->back($request, 'Chat re-opened.');
    }

    public function assign(Request $request, SupportConversation $conversation)
    {
        $data = $request->validate(['agent' => 'nullable|integer']);
        $to = !empty($data['agent']) ? User::find($data['agent']) : null;
        abort_if($to && !$to->can(SupportChat::PERMISSION), 422, 'That person cannot answer support chats.');
        $this->chat->assign($conversation, $to, $request->user());

        return $this->back($request, $to ? 'Assigned to ' . $to->name . '.' : 'Returned to the queue.');
    }

    protected function back(Request $request, string $msg)
    {
        return $request->expectsJson() ? response()->json(['message' => $msg]) : back()->with('success', $msg);
    }
}
