<?php

namespace App\Http\Controllers\Support;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Support\SupportChat;
use Illuminate\Http\Request;

/** Shared bits for the customer chat (web + app) and the admin inbox. */
trait ChatPayload
{
    protected function present($messages, User $viewer, bool $forStaff, string $fileRoute = 'support.attachment'): array
    {
        return $messages->map(function (SupportMessage $m) use ($viewer, $forStaff, $fileRoute) {
            $p = $m->present($forStaff, $fileRoute);
            $p['mine'] = $m->type !== 'event' && ($forStaff ? $m->from_staff : !$m->from_staff);
            return $p;
        })->values()->all();
    }

    protected function conversationMeta(SupportConversation $conv, User $viewer, bool $forStaff): array
    {
        $assignee = $conv->assignee;

        return [
            'id'          => $conv->id,
            'status'      => $conv->status,
            'unread'      => (int) ($forStaff ? $conv->staff_unread : $conv->customer_unread),
            'typing'      => app(SupportChat::class)->otherTyping($conv, $viewer),
            'agent'       => $assignee ? ['name' => $forStaff ? $assignee->name : \Illuminate\Support\Str::before(trim($assignee->name), ' '), 'avatar' => $assignee->avatar_url ?? null] : null,
            // The other side has read up to here (for "Read" ticks on my last message).
            'read_up_to'  => SupportMessage::where('conversation_id', $conv->id)->where('from_staff', $forStaff)->whereNotNull('read_at')->max('id'),
        ];
    }

    /** Validates a send request; returns [body, file, transaction]. */
    protected function validateMessage(Request $request, ?User $customer): array
    {
        $data = $request->validate([
            'body'       => 'nullable|string|max:4000|required_without_all:attachment,transaction',
            'attachment' => 'nullable|file|max:' . SupportChat::MAX_FILE_KB . '|mimes:' . SupportChat::MIMES,
            'transaction' => 'nullable|string|max:40',
        ], [
            'body.required_without_all' => 'Type a message, or attach a file or payment.',
            'attachment.max'   => 'Files can be up to 5 MB.',
            'attachment.mimes' => 'Attach a photo (JPG, PNG, WEBP) or a PDF.',
        ]);

        $txn = null;
        if (!empty($data['transaction']) && $customer) {
            // Customers can only attach their own payments.
            $txn = Transaction::where('reference', $data['transaction'])->where('user_id', $customer->id)->first();
        }

        return [$data['body'] ?? null, $request->file('attachment'), $txn];
    }
}
