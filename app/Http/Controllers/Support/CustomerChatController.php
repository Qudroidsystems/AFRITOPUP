<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Services\Support\SupportChat;
use Illuminate\Http\Request;

/** The customer's side of the chat on the website (the app uses Api\V1\SupportApiController). */
class CustomerChatController extends Controller
{
    use ChatPayload;

    public function __construct(protected SupportChat $chat) {}

    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->isStaff()) return redirect()->route('admin.support.index');
        $conv = $this->chat->conversationFor($user);

        return view('account.support', [
            'pagetitle' => 'Help & support',
            'conv'      => $conv,
            'about'     => $request->query('transaction')
                ? $user->transactions()->with('service')->where('reference', $request->query('transaction'))->first() : null,
            'recentTxns' => $user->transactions()->with('service')->latest('id')->limit(8)->get(),
        ]);
    }

    /** Poll: new messages after ?after=, or a page before ?before=. */
    public function messages(Request $request)
    {
        $user = $request->user();
        $conv = $this->chat->conversationFor($user);
        $msgs = $this->chat->messages($conv, $request->integer('after') ?: null, $request->integer('before') ?: null);
        if ($request->boolean('seen')) $this->chat->markRead($conv->fresh(), $user);

        return response()->json(['messages' => $this->present($msgs, $user, false), 'conversation' => $this->conversationMeta($conv->fresh(), $user, false)]);
    }

    public function send(Request $request)
    {
        $user = $request->user();
        $conv = $this->chat->conversationFor($user);
        [$body, $file, $txn] = $this->validateMessage($request, $user);
        $msg = $this->chat->send($conv, $user, $body, $file, $txn);

        return response()->json(['message' => $this->present(collect([$msg->load('sender', 'transaction.service')]), $user, false)[0]], 201);
    }

    public function typing(Request $request)
    {
        $this->chat->typing($this->chat->conversationFor($request->user()), $request->user());

        return response()->noContent();
    }

    /** Unread count for the badge on every page. */
    public function summary(Request $request)
    {
        return response()->json($this->chat->customerSummary($request->user()));
    }

    /** Files in the chat: only the customer of that chat and support staff. */
    public function attachment(Request $request, SupportMessage $message)
    {
        abort_unless($message->attachment_path && SupportChat::canSee($request->user(), $message), 404);

        return SupportChat::file($message);
    }
}
