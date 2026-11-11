<?php

namespace App\Services\Messaging;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\ValidationException;

/**
 * Buttons on a notification, for the web notification centre and the mobile
 * app. A notification names its target (`target: {kind, id}`) and its buttons;
 * run() performs one and marks the notification read with what was done.
 *
 * AfriTopup has no actionable notifications yet. Add a kind to BUTTONS and a
 * case to perform() when one is needed (e.g. approving a manual wallet top-up).
 */
class NotificationActions
{
    /** The buttons each kind of target can have. */
    public const BUTTONS = [];

    public static function for(string $kind): array
    {
        return self::BUTTONS[$kind] ?? [];
    }

    /** @return string what happened, e.g. "Approved" */
    public function run(DatabaseNotification $n, User $user, string $action, ?string $input = null): string
    {
        $data = (array) $n->data;
        $target = (array) ($data['target'] ?? []);
        $buttons = collect($data['actions'] ?? [])->keyBy('id');
        if (!$buttons->has($action) || empty($target['kind']) || empty($target['id'])) $this->fail('This notification has no such button.');
        if (!empty($data['done'])) $this->fail('Already done: ' . $data['done'] . '.');

        $done = $this->perform($target['kind'], (int) $target['id'], $user, $action, $input);
        $n->forceFill(['data' => $data + ['done' => $done], 'read_at' => $n->read_at ?? now()])->save();

        return $done;
    }

    public function perform(string $kind, int $id, User $user, string $action, ?string $input = null): string
    {
        $this->fail('This notification has no such button.');
    }

    protected function fail(string $message): never
    {
        throw ValidationException::withMessages(['action' => $message]);
    }
}
