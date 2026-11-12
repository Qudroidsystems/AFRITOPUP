<?php

namespace App\Services\Api;

use App\Models\User;
use App\Support\Modules;

/**
 * The signed-in user as the mobile app sees them (login, /auth/me).
 * `modules` lists the module keys the user can open; `wallet` is the
 * main wallet balance shown on the home screen.
 */
class ApiUserPresenter
{
    public static function present(User $user): array
    {
        return [
            'id'                   => $user->id,
            'name'                 => $user->name,
            'first_name'           => $user->first_name,
            'email'                => $user->email,
            'email_verified'       => $user->hasVerifiedEmail(),
            'email_receipts'       => method_exists($user, 'wantsEmailReceipts') ? $user->wantsEmailReceipts() : false,
            'needs_email_verification' => method_exists($user, 'needsEmailVerification') ? $user->needsEmailVerification() : false,
            'username'             => $user->username,
            'phone_number'         => $user->phone_number,
            'user_type'            => $user->user_type,
            'user_type_label'      => $user->user_type_label,
            'job_title'            => $user->job_title,
            'avatar_url'           => $user->avatar_path_url,
            'initials'             => $user->initials,
            'roles'                => $user->getRoleNames()->values(),
            'permissions'          => $user->isSuperAdmin() ? ['*'] : $user->getAllPermissions()->pluck('name')->values(),
            'modules'              => array_keys(Modules::available($user)),
            'must_change_password' => (bool) $user->must_change_password,
            'wallet'               => self::wallet($user),
            'presence'             => app(\App\Services\Presence\Presence::class)->of($user) + ['line' => app(\App\Services\Presence\Presence::class)->line($user)],
        ];
    }

    /** Main wallet balance, so the app can show it straight after sign-in. */
    protected static function wallet(User $user): ?array
    {
        try {
            $w = $user->wallet();
        } catch (\Throwable $e) {
            return null;   // before migrate
        }

        return ['balance' => (float) $w->balance, 'currency' => $w->currency, 'formatted' => \App\Support\Money::format($w->balance, $w->currency)];
    }
}
