<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 6-digit codes for confirming an e-mail address and resetting a password.
 *
 *  - only an HMAC of the code is stored (keyed with APP_KEY)
 *  - a new code cancels the previous one for the same purpose
 *  - codes expire (verify 30 min, reset 15 min) and allow 5 wrong tries
 *  - resending is limited: one per minute, six per hour
 */
class EmailCodes
{
    public const VERIFY = 'verify';
    public const RESET = 'reset';

    public const TTL = [self::VERIFY => 30, self::RESET => 15];   // minutes
    public const MAX_ATTEMPTS = 5;
    public const COOLDOWN = 60;                                     // seconds between sends
    public const PER_HOUR = 6;

    /** Seconds until another code may be sent (0 = now). */
    public function wait(User $user, string $purpose): int
    {
        $rows = DB::table('email_codes')->where('user_id', $user->id)->where('purpose', $purpose)
            ->where('created_at', '>=', now()->subHour())->orderByDesc('created_at')->get(['created_at']);
        if ($rows->isEmpty()) return 0;
        if ($rows->count() >= self::PER_HOUR) {
            return max(1, (int) now()->diffInSeconds(\Carbon\Carbon::parse($rows->last()->created_at)->addHour(), false));
        }

        return max(0, (int) now()->diffInSeconds(\Carbon\Carbon::parse($rows->first()->created_at)->addSeconds(self::COOLDOWN), false));
    }

    /** A new code (the plain digits are returned once, for the e-mail). */
    public function issue(User $user, string $purpose): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($user, $purpose, $code) {
            DB::table('email_codes')->where('user_id', $user->id)->where('purpose', $purpose)->whereNull('used_at')->update(['used_at' => now(), 'updated_at' => now()]);
            DB::table('email_codes')->insert([
                'user_id' => $user->id, 'email' => strtolower($user->email), 'purpose' => $purpose, 'code_hash' => $this->hash($code),
                'expires_at' => now()->addMinutes(self::TTL[$purpose]), 'ip' => request()?->ip(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return $code;
    }

    /**
     * Check a code. Returns true once (the code is then used up).
     * Errors: 'invalid' (wrong), 'expired', 'locked' (too many tries / none sent).
     */
    public function check(User $user, string $purpose, string $code, ?string &$error = null): bool
    {
        $row = DB::table('email_codes')->where('user_id', $user->id)->where('purpose', $purpose)
            ->where('email', strtolower($user->email))->whereNull('used_at')->orderByDesc('id')->first();

        if (!$row || $row->attempts >= self::MAX_ATTEMPTS) { $error = 'locked'; return false; }
        if (now()->greaterThan($row->expires_at)) { $error = 'expired'; return false; }

        $code = preg_replace('/\D/', '', $code);
        if (!hash_equals($row->code_hash, $this->hash($code))) {
            DB::table('email_codes')->where('id', $row->id)->increment('attempts');
            $error = $row->attempts + 1 >= self::MAX_ATTEMPTS ? 'locked' : 'invalid';
            return false;
        }

        DB::table('email_codes')->where('id', $row->id)->update(['used_at' => now(), 'updated_at' => now()]);

        return true;
    }

    public static function message(?string $error, string $purpose): string
    {
        return match ($error) {
            'expired' => 'That code has expired. Request a new one.',
            'locked'  => 'Too many wrong tries. Request a new code.',
            default   => 'That code is not correct. Check the e-mail and try again.',
        };
    }

    protected function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
