<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Services\Api\ApiUserPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;

/**
 * Token sign-in for the mobile app (Laravel Sanctum).
 *
 * Same rules as the web login: e-mail, username or phone number; disabled
 * accounts are refused; temporary passwords must be changed first. Each
 * phone gets its own token, so signing out on one phone keeps the others.
 */
class AuthController extends ApiController
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'login'       => 'required|string|max:191',
            'password'    => 'required|string|max:191',
            'device_name' => 'nullable|string|max:120',
            'platform'    => 'nullable|in:android,ios,web',
            'app_version' => 'nullable|string|max:30',
            'push_token'  => 'nullable|string|max:512',
        ]);

        $user = $this->findUser(trim($data['login']));

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return $this->fail('The login details are not correct.', 422, 'invalid_credentials', [
                'login' => ['The login details are not correct.'],
            ]);
        }

        if ($user->is_disabled) {
            return $this->fail('This account has been disabled. Please contact your administrator.', 403, 'account_disabled');
        }

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();

        $token = $user->createToken(($data['device_name'] ?? null) ?: 'Mobile app', ['*']);
        $this->rememberDevice($user->id, $token->accessToken->id, $data);

        return $this->ok([
            'token'      => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user'       => ApiUserPresenter::present($user),
        ], 'Signed in.');
    }

    /** Customer sign-up from the app (when registration is open). Returns a token like login. */
    public function register(Request $request)
    {
        if (!config('afritopup.allow_registration')) return $this->fail('Sign-up is closed. Please contact support.', 403, 'registration_closed');

        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|max:255|unique:users,email',
            'phone_number' => 'required|string|max:30|unique:users,phone_number',
            'password'     => ['required', 'string', Password::min(8)],
            'referral'     => 'nullable|string|max:20',
            'device_name'  => 'nullable|string|max:120',
            'platform'     => 'nullable|in:android,ios,web',
            'app_version'  => 'nullable|string|max:30',
            'push_token'   => 'nullable|string|max:512',
        ]);

        $referrer = !empty($data['referral']) ? User::where('referral_code', strtoupper(trim($data['referral'])))->first() : null;
        $user = User::create([
            'name'          => $data['name'],
            'email'         => strtolower($data['email']),
            'phone_number'  => $data['phone_number'],
            'user_type'     => User::TYPE_CUSTOMER,
            'country_code'  => config('afritopup.default_country', 'NG'),
            'referred_by'   => $referrer?->id,
            'referral_code' => strtoupper(\Illuminate\Support\Str::random(8)),
            'password'      => Hash::make($data['password']),
        ]);
        try { $user->assignRole('Customer'); } catch (\Throwable $e) {}
        $user->wallet();
        event(new \Illuminate\Auth\Events\Registered($user));   // e-mails the 6-digit confirmation code

        $token = $user->createToken(($data['device_name'] ?? null) ?: 'Mobile app', ['*']);
        $this->rememberDevice($user->id, $token->accessToken->id, $data);

        return $this->ok([
            'token'      => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user'       => ApiUserPresenter::present($user->fresh()),
        ], 'Welcome to ' . config('afritopup.name') . '! Enter the 6-digit code we sent to ' . $user->email . '.', 201);
    }

    /**
     * Sign in (or sign up) with Google or Apple. The app sends the provider's ID token;
     * the server checks it with the provider's keys. Apple sends the name only the
     * first time, so the app passes it along.
     */
    public function social(Request $request, \App\Services\Auth\SocialLogin $social)
    {
        $data = $request->validate([
            'provider'    => 'required|in:google,apple',
            'id_token'    => 'required|string|max:8000',
            'name'        => 'nullable|string|max:255',
            'device_name' => 'nullable|string|max:120',
            'platform'    => 'nullable|in:android,ios,web',
            'app_version' => 'nullable|string|max:30',
            'push_token'  => 'nullable|string|max:512',
        ]);

        try {
            [$user, $created] = $social->resolve($data['provider'], $data['id_token'], $data['name'] ?? null);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, 'social_failed');
        }

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        $token = $user->createToken(($data['device_name'] ?? null) ?: 'Mobile app', ['*']);
        $this->rememberDevice($user->id, $token->accessToken->id, $data);

        return $this->ok([
            'token'      => $token->plainTextToken,
            'token_type' => 'Bearer',
            'created'    => $created,
            'user'       => ApiUserPresenter::present($user->fresh()),
        ], $created ? 'Welcome to ' . config('afritopup.name') . '!' : 'Signed in.', $created ? 201 : 200);
    }

    public function me(Request $request)
    {
        return $this->ok(ApiUserPresenter::present($request->user()));
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            if (Schema::hasTable('mobile_devices')) {
                DB::table('mobile_devices')->where('token_id', $token->id)->delete();
            }
            $token->delete();
        }

        return $this->ok(null, 'Signed out.');
    }

    /** Sign out of every phone. */
    public function logoutAll(Request $request)
    {
        $user = $request->user();
        $user->tokens()->delete();
        if (Schema::hasTable('mobile_devices')) {
            DB::table('mobile_devices')->where('user_id', $user->id)->delete();
        }

        return $this->ok(null, 'Signed out on all devices.');
    }

    /** Change password; also how a temporary password is replaced. */
    public function changePassword(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'current_password' => $user->must_change_password ? 'nullable|string' : 'required|string',
            'password'         => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if (!$user->must_change_password && !Hash::check((string) $request->current_password, $user->password)) {
            return $this->fail('Your current password is not correct.', 422, 'invalid_current_password', [
                'current_password' => ['Your current password is not correct.'],
            ]);
        }

        $user->forceFill(['password' => Hash::make($request->password), 'must_change_password' => false])->save();

        // Sign out the other phones; keep this one.
        $current = $user->currentAccessToken();
        $user->tokens()->when($current, fn ($q) => $q->where('id', '!=', $current->id))->delete();

        return $this->ok(ApiUserPresenter::present($user->fresh()), 'Password changed.');
    }

    /**
     * E-mails a password-reset link (the same link as the web "Forgot password").
     * Always answers the same way so it cannot reveal which addresses exist.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email|max:191']);

        try {
            PasswordBroker::sendResetLink(['email' => strtolower(trim($request->email))]);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->ok(['code_length' => 6], 'If that address belongs to an account, we\'ve e-mailed a 6-digit code to it.');
    }

    /** Send another confirmation code (one a minute). */
    public function resendVerification(Request $request, \App\Services\Auth\EmailCodes $codes)
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) return $this->ok(['email_verified' => true], 'Your e-mail is already confirmed.');
        if ($wait = $codes->wait($user, \App\Services\Auth\EmailCodes::VERIFY)) {
            return $this->fail("Please wait {$wait} seconds before asking for another code.", 429, 'too_soon', ['retry_after' => $wait]);
        }
        $user->sendEmailVerificationNotification();

        return $this->ok(['retry_after' => \App\Services\Auth\EmailCodes::COOLDOWN], 'A new code is on its way to ' . $user->email . '.');
    }

    /** Confirm the e-mail with the 6-digit code. */
    public function verifyEmail(Request $request, \App\Services\Auth\EmailCodes $codes)
    {
        $request->validate(['code' => 'required|string|max:12']);
        $user = $request->user();

        if (!$user->hasVerifiedEmail()) {
            if (!$codes->check($user, \App\Services\Auth\EmailCodes::VERIFY, $request->input('code'), $error)) {
                return $this->fail(\App\Services\Auth\EmailCodes::message($error, 'verify'), 422, 'code_' . $error);
            }
            if ($user->markEmailAsVerified()) event(new \Illuminate\Auth\Events\Verified($user));
        }

        return $this->ok(['user' => ApiUserPresenter::present($user->fresh())], 'Your e-mail is confirmed.');
    }

    /**
     * Reset a forgotten password with the 6-digit code from the e-mail.
     * Signs out every device, as the old password may be known to someone else.
     */
    public function resetPassword(Request $request, \App\Services\Auth\EmailCodes $codes)
    {
        $data = $request->validate([
            'email'    => 'required|email|max:191',
            'code'     => 'required|string|max:12',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = User::where('email', strtolower(trim($data['email'])))->first();
        $error = 'invalid';
        if (!$user || !$codes->check($user, \App\Services\Auth\EmailCodes::RESET, $data['code'], $error)) {
            return $this->fail(\App\Services\Auth\EmailCodes::message($error, 'reset'), 422, 'code_' . $error);
        }

        $user->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false, 'remember_token' => \Illuminate\Support\Str::random(60)]);
        // They proved they own the inbox.
        if (!$user->hasVerifiedEmail()) $user->email_verified_at = now();
        $user->save();
        $user->tokens()->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        event(new \Illuminate\Auth\Events\PasswordReset($user));

        return $this->ok(null, 'Your password has been reset. Please sign in.');
    }

    // ── helpers ─────────────────────────────────────────────────────────

    /** E-mail, username or phone number (as on the web login). */
    protected function findUser(string $login): ?User
    {
        if ($login === '') return null;
        if (str_contains($login, '@')) {
            return User::where('email', strtolower($login))->first();
        }
        return User::where('username', $login)->orWhere('phone_number', $login)->first();
    }

    protected function rememberDevice(int $userId, int $tokenId, array $d): void
    {
        if (!Schema::hasTable('mobile_devices')) return;

        $row = [
            'user_id' => $userId, 'token_id' => $tokenId,
            'platform' => $d['platform'] ?? null, 'device_name' => $d['device_name'] ?? null,
            'app_version' => $d['app_version'] ?? null, 'last_seen_at' => now(), 'updated_at' => now(),
        ];

        if (!empty($d['push_token'])) {
            DB::table('mobile_devices')->updateOrInsert(['push_token' => $d['push_token']], $row + ['created_at' => now()]);
        } else {
            DB::table('mobile_devices')->insert($row + ['created_at' => now()]);
        }
    }
}
