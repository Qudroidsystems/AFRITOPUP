<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/** A real RS256 key pair, published as a fake JWKS, so tokens are verified end to end. */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $d = openssl_pkey_get_details($this->key)['rsa'];
    $b64 = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    $jwks = ['keys' => [['kty' => 'RSA', 'kid' => 'k1', 'alg' => 'RS256', 'n' => $b64($d['n']), 'e' => $b64($d['e'])]]];
    Http::fake(['www.googleapis.com/*' => Http::response($jwks), 'appleid.apple.com/*' => Http::response($jwks)]);
    config(['afritopup.social.google.client_ids' => ['web-client.apps.googleusercontent.com'], 'afritopup.social.google.web_client_id' => 'web-client.apps.googleusercontent.com',
        'afritopup.social.apple.client_ids' => ['com.afritopup.app']]);

    $this->jwt = function (array $claims, $key = null) use ($b64) {
        $h = $b64(json_encode(['alg' => 'RS256', 'kid' => 'k1', 'typ' => 'JWT']));
        $c = $b64(json_encode($claims + ['iat' => time(), 'exp' => time() + 600]));
        openssl_sign("$h.$c", $sig, $key ?? $this->key, OPENSSL_ALGO_SHA256);

        return "$h.$c." . $b64($sig);
    };
    $this->google = fn (array $extra = []) => ($this->jwt)($extra + ['iss' => 'https://accounts.google.com', 'aud' => 'web-client.apps.googleusercontent.com',
        'sub' => 'g-123', 'email' => 'ngozi@example.com', 'email_verified' => true, 'name' => 'Ngozi Eze']);
});

test('google sign-in creates a customer with a wallet, then signs the same person in', function () {
    $res = $this->postJson('/api/v1/auth/social', ['provider' => 'google', 'id_token' => ($this->google)()])
        ->assertCreated()->assertJsonPath('data.created', true)->assertJsonPath('data.user.user_type', 'customer');
    $u = User::where('email', 'ngozi@example.com')->first();
    expect($u->google_id)->toBe('g-123')->and($u->hasRole('Customer'))->toBeTrue()->and($u->wallets()->count())->toBe(1);
    $this->withToken($res->json('data.token'))->getJson('/api/v1/wallet')->assertOk();

    $this->postJson('/api/v1/auth/social', ['provider' => 'google', 'id_token' => ($this->google)()])->assertOk()->assertJsonPath('data.created', false);
    expect(User::where('email', 'ngozi@example.com')->count())->toBe(1);
});

test('a verified e-mail links to the existing account', function () {
    $u = User::factory()->create(['email' => 'ngozi@example.com']);
    $this->postJson('/api/v1/auth/social', ['provider' => 'google', 'id_token' => ($this->google)()])->assertOk()->assertJsonPath('data.user.id', $u->id);
    expect($u->fresh()->google_id)->toBe('g-123');
});

test('apple sign-in uses the name the app sends', function () {
    $t = ($this->jwt)(['iss' => 'https://appleid.apple.com', 'aud' => 'com.afritopup.app', 'sub' => 'a-9', 'email' => 'x1@privaterelay.appleid.com', 'email_verified' => 'true']);
    $this->postJson('/api/v1/auth/social', ['provider' => 'apple', 'id_token' => $t, 'name' => 'Tunde Bakare'])->assertCreated();
    expect(User::where('apple_id', 'a-9')->value('name'))->toBe('Tunde Bakare');
});

test('forged, foreign and expired tokens are refused', function () {
    $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $base = ['iss' => 'https://accounts.google.com', 'aud' => 'web-client.apps.googleusercontent.com', 'sub' => 'g-1', 'email' => 'a@b.co', 'email_verified' => true];

    $this->postJson('/api/v1/auth/social', ['provider' => 'google', 'id_token' => ($this->jwt)($base, $other)])->assertStatus(422)->assertJsonPath('code', 'social_failed');
    $this->postJson('/api/v1/auth/social', ['provider' => 'google', 'id_token' => ($this->jwt)(['aud' => 'someone-else'] + $base)])->assertStatus(422);
    $this->postJson('/api/v1/auth/social', ['provider' => 'google', 'id_token' => ($this->jwt)(['exp' => time() - 3600] + $base)])->assertStatus(422);
    $this->postJson('/api/v1/auth/social', ['provider' => 'google', 'id_token' => 'not.a.jwt'])->assertStatus(422);
    expect(User::where('email', 'a@b.co')->exists())->toBeFalse();
});

test('the website signs in with a google credential and the app config advertises the buttons', function () {
    $this->postJson('/auth/social/google', ['id_token' => ($this->google)()])->assertOk()->assertJsonPath('success', true);
    $this->assertAuthenticated();

    $this->getJson('/api/v1/app/config')->assertOk()
        ->assertJsonPath('data.social.google.server_client_id', 'web-client.apps.googleusercontent.com')
        ->assertJsonPath('data.social.apple', true)
        ->assertJsonPath('data.developer', 'Qudroid Systems');
});
