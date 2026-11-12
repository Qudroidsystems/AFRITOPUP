<?php

namespace App\Services\Support;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Messaging\PortalNotifier;
use App\Services\Push\PushService;
use App\Services\Realtime\Realtime;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Customer ↔ support chat, shared by the website, the admin inbox and the app.
 *
 * Delivery: clients poll lightly (every few seconds while a chat is open) and,
 * when Reverb is switched on, get an instant "support" poke on user.{id}.
 * Someone not looking at the chat gets a push notification (customers) or a
 * portal notification (staff with "Manage support"), throttled per chat.
 */
class SupportChat
{
    public const PERMISSION = 'Manage support';
    public const MAX_FILE_KB = 5120;
    public const MIMES = 'jpg,jpeg,png,webp,gif,pdf';
    public const TYPING_SECONDS = 6;

    /** The customer's conversation, created (with a welcome) the first time. */
    public function conversationFor(User $customer): SupportConversation
    {
        $conv = SupportConversation::firstOrCreate(['user_id' => $customer->id], ['status' => 'open', 'last_message_at' => now()]);
        if ($conv->wasRecentlyCreated) {
            $this->event($conv, 'Hi ' . (Str::before(trim($customer->name), ' ') ?: 'there') . ' 👋 Send us a message and our support team will reply right here. You can attach a screenshot, or a payment from your history.', false);
        }

        return $conv;
    }

    /** Post a message. $by is the customer or a staff member. */
    public function send(SupportConversation $conv, User $by, ?string $body, ?UploadedFile $file = null, ?Transaction $txn = null): SupportMessage
    {
        $fromStaff = $by->id !== $conv->user_id;
        $body = $body !== null ? trim(preg_replace("/\r\n?/", "\n", $body)) : null;

        $msg = DB::transaction(function () use ($conv, $by, $body, $file, $txn, $fromStaff) {
            $data = ['conversation_id' => $conv->id, 'user_id' => $by->id, 'from_staff' => $fromStaff, 'type' => 'text', 'body' => $body ?: null, 'transaction_id' => $txn?->id];
            if ($file) {
                $data += [
                    'attachment_path' => $file->store('support/' . $conv->id, 'local'),
                    'attachment_name' => Str::limit($file->getClientOriginalName(), 140, ''),
                    'attachment_mime' => $file->getMimeType(),
                    'attachment_size' => $file->getSize(),
                ];
            }
            $msg = SupportMessage::create($data);

            $preview = $body ?: ($file ? (Str::startsWith((string) $file->getMimeType(), 'image/') ? '📷 Photo' : '📎 ' . $data['attachment_name']) : ($txn ? '🧾 Payment ' . $txn->reference : ''));
            $updates = ['last_message_at' => now(), 'last_message_preview' => Str::limit($preview, 150), 'last_from_staff' => $fromStaff];

            if ($fromStaff) {
                $updates['customer_unread'] = DB::raw('customer_unread + 1');
                $updates['staff_unread'] = 0;     // replying means they've read it
                if (!$conv->assigned_to) $updates['assigned_to'] = $by->id;
                SupportMessage::where('conversation_id', $conv->id)->where('from_staff', false)->whereNull('read_at')->update(['read_at' => now()]);
            } else {
                $updates['staff_unread'] = DB::raw('staff_unread + 1');
                if (!$conv->isOpen()) { $updates['status'] = 'open'; $updates['closed_at'] = null; }
            }
            $conv->forceFill($updates)->save();

            return $msg;
        });

        Cache::forget($this->typingKey($conv, $fromStaff));
        $conv->refresh();
        $this->poke($conv);
        $fromStaff ? $this->notifyCustomer($conv, $by, $msg) : $this->notifyStaff($conv, $msg);

        return $msg;
    }

    /** A grey line in the chat ("Chat closed by Ada", the welcome…). */
    public function event(SupportConversation $conv, string $text, bool $poke = true): SupportMessage
    {
        $msg = SupportMessage::create(['conversation_id' => $conv->id, 'type' => 'event', 'body' => $text, 'from_staff' => true, 'read_at' => now()]);
        if ($poke) $this->poke($conv);

        return $msg;
    }

    /** Messages after $afterId (newest last), or the latest page before $beforeId. */
    public function messages(SupportConversation $conv, ?int $afterId = null, ?int $beforeId = null, int $limit = 40)
    {
        $q = SupportMessage::with(['sender:id,name', 'transaction.service'])->where('conversation_id', $conv->id);
        if ($afterId) return $q->where('id', '>', $afterId)->orderBy('id')->limit(200)->get();
        if ($beforeId) $q->where('id', '<', $beforeId);

        return $q->orderByDesc('id')->limit($limit)->get()->reverse()->values();
    }

    /** The viewer has seen the chat: clear their unread count and mark the other side's messages read. */
    public function markRead(SupportConversation $conv, User $viewer): void
    {
        $isStaff = $viewer->id !== $conv->user_id;
        $unread = $isStaff ? $conv->staff_unread : $conv->customer_unread;
        $changed = SupportMessage::where('conversation_id', $conv->id)->where('from_staff', !$isStaff)->whereNull('read_at')->update(['read_at' => now()]);
        if ($unread || $changed) {
            $conv->forceFill([$isStaff ? 'staff_unread' : 'customer_unread' => 0])->save();
            $this->poke($conv);   // the other side sees "Read"
        }
    }

    public function typing(SupportConversation $conv, User $who): void
    {
        $isStaff = $who->id !== $conv->user_id;
        Cache::put($this->typingKey($conv, $isStaff), Str::before(trim($who->name), ' '), self::TYPING_SECONDS);
        Realtime::toUsers($isStaff ? [$conv->user_id] : $this->watchers($conv), 'support', ['conversation' => $conv->id, 'typing' => true]);
    }

    /** Is the other side typing? Returns their first name or null. */
    public function otherTyping(SupportConversation $conv, User $viewer): ?string
    {
        $viewerIsStaff = $viewer->id !== $conv->user_id;

        return Cache::get($this->typingKey($conv, !$viewerIsStaff));
    }

    public function close(SupportConversation $conv, User $staff): void
    {
        if (!$conv->isOpen()) return;
        $conv->forceFill(['status' => 'closed', 'closed_at' => now(), 'staff_unread' => 0])->save();
        $this->event($conv, 'Chat marked as solved by ' . Str::before(trim($staff->name), ' ') . '. Send a message any time to continue.');
    }

    public function reopen(SupportConversation $conv, User $staff): void
    {
        if ($conv->isOpen()) return;
        $conv->forceFill(['status' => 'open', 'closed_at' => null])->save();
        $this->event($conv, 'Chat re-opened by ' . Str::before(trim($staff->name), ' ') . '.');
    }

    public function assign(SupportConversation $conv, ?User $to, User $by): void
    {
        $conv->forceFill(['assigned_to' => $to?->id])->save();
        $this->event($conv, $to ? Str::before(trim($to->name), ' ') . ' from support has joined the chat.' : 'Chat returned to the support queue.');
    }

    /** Payload for "what's new" checks (badge counts). */
    public function customerSummary(User $customer): array
    {
        $conv = SupportConversation::where('user_id', $customer->id)->first();

        return ['unread' => (int) ($conv?->customer_unread ?? 0), 'status' => $conv?->status, 'last_message_at' => $conv?->last_message_at?->toIso8601String()];
    }

    public function staffUnreadTotal(): int
    {
        return (int) SupportConversation::where('status', 'open')->sum('staff_unread');
    }

    /** People who should hear about a customer's message: the assignee, else every support agent. */
    public function watchers(SupportConversation $conv): array
    {
        if ($conv->assigned_to) return [$conv->assigned_to];

        return PortalNotifier::userIdsWithPermission(self::PERMISSION);
    }

    protected function poke(SupportConversation $conv): void
    {
        Realtime::toUsers(array_merge([$conv->user_id], PortalNotifier::userIdsWithPermission(self::PERMISSION)), 'support', ['conversation' => $conv->id]);
    }

    protected function notifyCustomer(SupportConversation $conv, User $staff, SupportMessage $msg): void
    {
        rescue(fn () => PushService::toUsers([$conv->user_id], Str::before(trim($staff->name), ' ') . ' · ' . config('afritopup.name') . ' Support',
            Str::limit($conv->last_message_preview ?: 'New message', 120), ['type' => 'support', 'route' => '/support', 'conversation' => (string) $conv->id]), report: false);
    }

    /** Staff hear about a customer's message at most once every 5 minutes per chat (unless it was quiet). */
    protected function notifyStaff(SupportConversation $conv, SupportMessage $msg): void
    {
        if ($conv->staff_notified_at && $conv->staff_notified_at->gt(now()->subMinutes(5)) && $conv->staff_unread > 1) return;
        $conv->forceFill(['staff_notified_at' => now()])->save();
        $customer = $conv->user;

        rescue(fn () => PortalNotifier::toUsers($this->watchers($conv), 'Support chat · ' . $customer?->name,
            Str::limit($conv->last_message_preview ?: 'New message', 140), route('admin.support.show', $conv), 'support', 'support-' . $conv->id), report: false);
    }

    protected function typingKey(SupportConversation $conv, bool $staff): string
    {
        return "support.typing.{$conv->id}." . ($staff ? 'staff' : 'customer');
    }

    /** Stream a file to someone allowed to see it. */
    public static function canSee(User $user, SupportMessage $msg): bool
    {
        return $msg->conversation?->user_id === $user->id || $user->can(self::PERMISSION);
    }

    public static function file(SupportMessage $msg)
    {
        return Storage::disk('local')->response($msg->attachment_path, $msg->attachment_name, [
            'Content-Type' => $msg->attachment_mime, 'Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff',
        ], $msg->isImage() ? 'inline' : 'attachment');
    }
}
