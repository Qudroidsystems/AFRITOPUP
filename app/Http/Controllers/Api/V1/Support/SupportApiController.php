<?php

namespace App\Http\Controllers\Api\V1\Support;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithApi;
use App\Http\Controllers\Support\ChatPayload;
use App\Services\Support\SupportChat;
use Illuminate\Http\Request;

/**
 * App: chat with support.
 *   GET  support                 conversation + latest messages (marks them read)
 *   GET  support/messages        ?after=ID (poll) or ?before=ID (older)
 *   POST support/messages        body / attachment (multipart) / transaction (reference)
 *   POST support/typing
 *   GET  support/summary         unread count for badges
 *   GET  support/files/{message} a photo / PDF (send the Bearer token)
 */
class SupportApiController extends Controller
{
    use RespondsWithApi, ChatPayload;

    public function __construct(protected SupportChat $chat) {}

    public function show(Request $request)
    {
        $user = $request->user();
        $conv = $this->chat->conversationFor($user);
        $msgs = $this->chat->messages($conv);
        $this->chat->markRead($conv, $user);

        return $this->ok([
            'conversation' => $this->conversationMeta($conv->fresh(), $user, false),
            'messages'     => $this->present($msgs, $user, false, 'api.v1.support.file'),
            'has_more'     => $msgs->isNotEmpty() && $conv->messages()->where('id', '<', $msgs->first()->id)->exists(),
            'hours'        => 'We usually reply within a few minutes, 8am – 9pm.',
        ]);
    }

    public function messages(Request $request)
    {
        $user = $request->user();
        $conv = $this->chat->conversationFor($user);
        $msgs = $this->chat->messages($conv, $request->integer('after') ?: null, $request->integer('before') ?: null);
        if ($request->boolean('seen', true) && !$request->integer('before')) $this->chat->markRead($conv, $user);

        return $this->ok(['conversation' => $this->conversationMeta($conv->fresh(), $user, false), 'messages' => $this->present($msgs, $user, false, 'api.v1.support.file')]);
    }

    public function send(Request $request)
    {
        $user = $request->user();
        $conv = $this->chat->conversationFor($user);
        [$body, $file, $txn] = $this->validateMessage($request, $user);
        $msg = $this->chat->send($conv, $user, $body, $file, $txn);

        return $this->ok(['message' => $this->present(collect([$msg->load('sender', 'transaction.service')]), $user, false, 'api.v1.support.file')[0]], null, 201);
    }

    public function typing(Request $request)
    {
        $this->chat->typing($this->chat->conversationFor($request->user()), $request->user());

        return $this->ok(null);
    }

    /** A photo or PDF from the chat (token-authenticated, for the app). */
    public function file(Request $request, \App\Models\SupportMessage $message)
    {
        abort_unless($message->attachment_path && SupportChat::canSee($request->user(), $message), 404);

        return SupportChat::file($message);
    }

    public function summary(Request $request)
    {
        return $this->ok($this->chat->customerSummary($request->user()));
    }
}
