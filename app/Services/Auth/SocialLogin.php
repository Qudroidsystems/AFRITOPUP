<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns a verified Google / Apple identity into an account:
 *   1. an account already linked to that Google / Apple ID → sign in;
 *   2. an account with the same (provider-verified) e-mail → link it, sign in;
 *   3. otherwise → create a customer account (when sign-up is open).
 *
 * @return array{0: User, 1: bool} the user and whether the account is new
 */
class SocialLogin
{
    public function __construct(protected SocialTokenVerifier $verifier)
    {
    }

    public function resolve(string $provider, string $idToken, ?string $name = null): array
    {
        if (!SocialTokenVerifier::enabled($provider)) throw new RuntimeException('Sign in with ' . ucfirst($provider) . ' is not available.');

        $claims = $this->verifier->verify($provider, $idToken);
        $column = $provider . '_id';
        $sub = (string) $claims['sub'];
        $email = strtolower(trim((string) ($claims['email'] ?? '')));
        $verified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($user = User::where($column, $sub)->first()) return [$this->check($user), false];

        if ($email !== '' && $verified && ($user = User::where('email', $email)->first())) {
            $this->check($user)->forceFill([$column => $sub, 'email_verified_at' => $user->email_verified_at ?? now()])->save();

            return [$user, false];
        }

        if (!config('afritopup.allow_registration')) throw new RuntimeException('Sign-up is closed. Please contact support.');
        if ($email === '') throw new RuntimeException(ucfirst($provider) . ' did not share an e-mail address. Please allow e-mail access and try again.');

        $name = trim((string) ($name ?: ($claims['name'] ?? trim(($claims['given_name'] ?? '') . ' ' . ($claims['family_name'] ?? '')))));
        if ($name === '') $name = Str::headline(Str::before($email, '@'));

        $user = DB::transaction(function () use ($column, $sub, $email, $name) {
            $user = User::create([
                'name'          => mb_substr($name, 0, 255),
                'email'         => $email,
                'user_type'     => User::TYPE_CUSTOMER,
                'country_code'  => config('afritopup.default_country', 'NG'),
                'referral_code' => strtoupper(Str::random(8)),
                'password'      => Hash::make(Str::random(40)),   // they sign in with the provider; "forgot password" sets one
            ]);
            $user->forceFill([$column => $sub, 'email_verified_at' => now()])->save();
            try {
                $user->assignRole('Customer');
            } catch (\Throwable $e) {
                // Roles not seeded.
            }
            $user->wallet();

            return $user;
        });

        return [$user, true];
    }

    protected function check(User $user): User
    {
        if ($user->is_disabled) throw new RuntimeException('This account has been disabled. Please contact support.');

        return $user;
    }
}
