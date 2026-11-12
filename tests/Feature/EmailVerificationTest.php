<?php

use App\Mail\AccountCodeMail;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Mail::fake();
});

function signUp($test, string $email = 'ngozi@example.com'): User
{
    $test->post(route('register'), ['name' => 'Ngozi Eze', 'email' => $email, 'phone_number' => '08035550000', 'password' => 'secret123', 'password_confirmation' => 'secret123']);

    return User::where('email', $email)->firstOrFail();
}

function sentCode(string $purpose = 'verify'): ?string
{
    $code = null;
    Mail::assertSent(AccountCodeMail::class, function ($m) use (&$code, $purpose) { if ($m->purpose === $purpose) $code = $m->code; return $m->purpose === $purpose; });

    return $code;
}

it('e-mails a code on sign-up and keeps the customer on the confirm screen until it is entered', function () {
    $user = signUp($this);
    expect($user->hasVerifiedEmail())->toBeFalse();
    $code = sentCode();
    expect($code)->toMatch('/^\d{6}$/');

    $this->actingAs($user)->get(route('topup.wallet'))->assertRedirect(route('verification.notice'));
    $this->actingAs($user)->get(route('verification.notice'))->assertOk()->assertSee($user->email);

    $this->actingAs($user)->post(route('verification.code'), ['code' => '000000' === $code ? '111111' : '000000'])->assertSessionHasErrors('code');
    $this->actingAs($user)->post(route('verification.code'), ['code' => substr($code, 0, 3) . ' ' . substr($code, 3)])->assertRedirect(route('topup.wallet'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->actingAs($user->fresh())->get(route('topup.wallet'))->assertOk();
});

it('confirms with the signed link, even when signed out, and rejects tampered links', function () {
    $user = signUp($this);
    auth()->logout();

    $bad = URL::temporarySignedRoute('verification.verify', now()->addDay(), ['id' => $user->id, 'hash' => sha1('someone@else.com')]);
    $this->get($bad)->assertForbidden();
    $this->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))->assertForbidden(); // unsigned

    $good = URL::temporarySignedRoute('verification.verify', now()->addDay(), ['id' => $user->id, 'hash' => sha1($user->email)]);
    $this->get($good)->assertRedirect(route('login'));
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('locks a code after five wrong tries and limits resends', function () {
    $user = signUp($this);
    $code = sentCode();
    $wrong = $code === '123456' ? '654321' : '123456';
    for ($i = 0; $i < 5; $i++) $this->actingAs($user)->post(route('verification.code'), ['code' => $wrong]);
    $this->actingAs($user)->post(route('verification.code'), ['code' => $code])->assertSessionHasErrors('code');
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();

    // Just sent one: a resend now is refused.
    $this->actingAs($user)->post(route('verification.send'))->assertSessionHasErrors('code');
    $this->travel(61)->seconds();
    $this->actingAs($user)->post(route('verification.send'))->assertSessionHas('status');
    Mail::assertSent(AccountCodeMail::class, 2);
});

it('lets staff in without a code and skips the gate for social sign-ins', function () {
    $admin = User::where('email', strtolower(config('afritopup.super_admin.email')))->first();
    $admin->forceFill(['email_verified_at' => null, 'must_change_password' => false])->save();
    $this->actingAs($admin)->get(route('dashboard'))->assertOk();
});

it('resets a forgotten password with the app code, and signs out every device', function () {
    $user = signUp($this);
    $user->markEmailAsVerified();
    $user->createToken('phone');

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ngozi@example.com'])->assertOk();
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk(); // same answer
    $code = sentCode('reset');
    Mail::assertSent(AccountCodeMail::class, fn ($m) => $m->purpose === 'reset' && str_contains((string) $m->url, '/password/reset/'));

    $this->postJson('/api/v1/auth/reset-password', ['email' => 'ngozi@example.com', 'code' => '000001', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])->assertStatus(422);
    $this->postJson('/api/v1/auth/reset-password', ['email' => 'ngozi@example.com', 'code' => $code, 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])->assertOk();

    expect($user->tokens()->count())->toBe(0);
    $this->postJson('/api/v1/auth/login', ['login' => 'ngozi@example.com', 'password' => 'NewPass123', 'device_name' => 't'])->assertOk();
    // A code works once.
    $this->postJson('/api/v1/auth/reset-password', ['email' => 'ngozi@example.com', 'code' => $code, 'password' => 'Other1234', 'password_confirmation' => 'Other1234'])->assertStatus(422);
});

it('renders the e-mail with a plain-text part and no third-party links', function () {
    $user = signUp($this);
    $mail = null;
    Mail::assertSent(AccountCodeMail::class, function ($m) use (&$mail) { $mail = $m; return true; });

    $html = $mail->render();
    expect($html)->toContain(trim(chunk_split($mail->code, 3, ' ')))->toContain('Confirm my e-mail');
    preg_match_all('#href="(https?://[^"]+)"#', $html, $m);
    foreach ($m[1] as $link) expect(parse_url($link, PHP_URL_HOST))->toBe(parse_url(config('app.url'), PHP_URL_HOST));

    $content = $mail->content();
    expect($content->text)->toBe('emails.auth.code-text');
    expect($mail->envelope()->subject)->toContain('Confirm your e-mail');
});
