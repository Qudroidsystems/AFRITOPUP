<?php

use App\Jobs\SendResellerWebhook;
use App\Models\ApiKey;
use App\Models\PriceTier;
use App\Models\School;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletFunding;
use App\Services\Settings\Settings;
use App\Services\Topup\FundingService;
use App\Services\Topup\TopupService;
use App\Services\Topup\WalletService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->user = User::factory()->create(['user_type' => 'customer', 'country_code' => 'NG', 'phone_number' => '08031234567', 'must_change_password' => false]);
    $this->user->assignRole('Customer');
    $this->user->forceFill(['transaction_pin' => '2580'])->save();
    $this->wallet = $this->user->wallet();
    app(WalletService::class)->credit($this->wallet, 10000, 'TEST-SEED', 'Opening balance');
    $this->svc = fn (string $code) => Service::where('country_code', 'NG')->where('code', $code)->firstOrFail();
    $this->topup = app(TopupService::class);
});

test('airtime on the sandbox succeeds and debits the wallet', function () {
    $t = $this->topup->purchase($this->user, ['service_id' => ($this->svc)('mtn')->id, 'recipient' => '0803 123 4567', 'amount' => 500]);

    expect($t->status)->toBe('successful')
        ->and($t->recipient)->toBe('08031234567')
        ->and((float) $t->total)->toBe(500.0)
        ->and((float) $this->wallet->fresh()->balance)->toBe(9500.0)
        ->and($this->wallet->entries()->count())->toBe(2);
});

test('a refused purchase is refunded automatically, once', function () {
    $t = $this->topup->purchase($this->user, ['service_id' => ($this->svc)('glo')->id, 'recipient' => '08050000000', 'amount' => 1000]);

    expect($t->status)->toBe('failed')->and((float) $this->wallet->fresh()->balance)->toBe(10000.0);
    // A late failure answer must not refund twice.
    $this->topup->settle($t, \App\Services\Topup\Providers\ProviderResult::failed('again'));
    expect((float) $this->wallet->fresh()->balance)->toBe(10000.0);
});

test('a purchase the provider has not confirmed stays processing until requeried', function () {
    $t = $this->topup->purchase($this->user, ['service_id' => ($this->svc)('airtel')->id, 'recipient' => '08021111111', 'amount' => 200]);
    expect($t->status)->toBe('processing');

    $t->forceFill(['created_at' => now()->subMinutes(10), 'last_checked_at' => now()->subMinutes(10)])->save();
    $this->artisan('topup:requery')->assertSuccessful();

    expect($t->fresh()->status)->toBe('successful');
});

test('insufficient balance, bad numbers, limits and double taps are refused', function () {
    $mtn = ($this->svc)('mtn');
    expect(fn () => $this->topup->purchase($this->user, ['service_id' => $mtn->id, 'recipient' => '08031234567', 'amount' => 60000]))->toThrow(\App\Exceptions\TopupException::class, 'largest');
    expect(fn () => $this->topup->purchase($this->user, ['service_id' => $mtn->id, 'recipient' => '08031234567', 'amount' => 20000]))->toThrow(\App\Exceptions\TopupException::class, 'not enough');
    expect(Transaction::count())->toBe(0);   // a refused debit leaves no purchase behind
    expect(fn () => $this->topup->purchase($this->user, ['service_id' => $mtn->id, 'recipient' => '12345', 'amount' => 100]))->toThrow(\App\Exceptions\TopupException::class, 'valid');
    expect(fn () => $this->topup->purchase($this->user, ['service_id' => ($this->svc)('dstv')->id, 'recipient' => '7012345678', 'product_id' => ($this->svc)('dstv')->products()->where('code', 'dstv-premium')->value('id'), 'phone' => '08031234567']))
        ->toThrow(\App\Exceptions\TopupException::class, 'balance');

    $this->topup->purchase($this->user, ['service_id' => $mtn->id, 'recipient' => '08031234567', 'amount' => 100]);
    expect(fn () => $this->topup->purchase($this->user, ['service_id' => $mtn->id, 'recipient' => '08031234567', 'amount' => 100]))->toThrow(\App\Exceptions\TopupException::class, 'less than a minute');
    expect((float) $this->wallet->fresh()->balance)->toBe(9900.0);
});

test('agents and resellers get their tier discount', function () {
    $this->user->forceFill(['price_tier_id' => PriceTier::where('code', 'reseller')->value('id')])->save();
    $data = ($this->svc)('mtn-data');
    $plan = $data->products()->where('code', 'mtn-1gb-30d')->first();   // 1,000; reseller data discount 4%

    $t = $this->topup->purchase($this->user->fresh(), ['service_id' => $data->id, 'product_id' => $plan->id, 'recipient' => '08031234567']);

    expect((float) $t->amount)->toBe(1000.0)->and((float) $t->discount)->toBe(40.0)->and((float) $t->total)->toBe(960.0)
        ->and((float) $this->wallet->fresh()->balance)->toBe(9040.0);
});

test('electricity verifies the meter and returns a token', function () {
    $t = $this->topup->purchase($this->user, ['service_id' => ($this->svc)('ikeja-electric')->id, 'recipient' => '45012345678', 'meter_type' => 'prepaid', 'amount' => 2000, 'phone' => '08031234567']);

    expect($t->status)->toBe('successful')->and($t->customer_name)->toStartWith('Test Customer')->and($t->token)->not->toBeEmpty();

    expect(fn () => $this->topup->purchase($this->user, ['service_id' => ($this->svc)('ikeja-electric')->id, 'recipient' => '45012340000', 'amount' => 2000, 'phone' => '08031234567']))
        ->toThrow(\App\Exceptions\TopupException::class);
});

test('bulk sms is priced per page per recipient and needs an approved sender', function () {
    $sms = ($this->svc)('bulk-sms');
    $t = $this->topup->purchase($this->user, ['service_id' => $sms->id, 'recipients' => "08031234567, 08051234567\n2348091234567", 'message' => str_repeat('a', 200)]);

    expect($t->quantity)->toBe(3)->and($t->meta['pages'])->toBe(2)->and((float) $t->total)->toBe(24.0)
        ->and($t->meta['recipients'])->toContain('2348031234567');

    expect(fn () => $this->topup->purchase($this->user, ['service_id' => $sms->id, 'sender' => 'MyShop', 'recipients' => '08031234567', 'message' => 'Hi']))
        ->toThrow(\App\Exceptions\TopupException::class, 'not approved');
});

test('school fees wait for staff, who can deliver or refund', function () {
    $school = School::create(['country_code' => 'NG', 'name' => 'Kings College', 'code' => 'KCL', 'is_active' => true]);
    $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Administrator'))->first();

    $t = $this->topup->purchase($this->user, ['service_id' => ($this->svc)('school-fees')->id, 'school_id' => $school->id, 'student_name' => 'Ada Obi', 'amount' => 5000]);
    expect($t->status)->toBe('processing')->and($t->meta['school'])->toBe('Kings College');

    $this->topup->markDelivered($t, $admin, 'Paid to school');
    expect($t->fresh()->status)->toBe('successful');

    $t2 = $this->topup->purchase($this->user, ['service_id' => ($this->svc)('school-fees')->id, 'school_id' => $school->id, 'student_name' => 'Tunde Obi', 'amount' => 2000]);
    $this->topup->refund($t2, $admin, 'Duplicate payment');
    expect($t2->fresh()->status)->toBe('refunded')->and((float) $this->wallet->fresh()->balance)->toBe(5000.0);
});

test('buying on the web needs the transaction PIN', function () {
    $mtn = ($this->svc)('mtn');
    $this->actingAs($this->user)->post(route('topup.buy.store'), ['service_id' => $mtn->id, 'recipient' => '08031234567', 'amount' => 100, 'pin' => '0000'])
        ->assertSessionHasErrors('pin');

    $res = $this->actingAs($this->user)->post(route('topup.buy.store'), ['service_id' => $mtn->id, 'recipient' => '08031234567', 'amount' => 100, 'pin' => '2580']);
    $t = Transaction::latest('id')->first();
    $res->assertRedirect(route('topup.transactions.show', $t->reference));
    $this->actingAs($this->user)->get(route('topup.transactions.show', $t->reference))->assertOk()->assertSee($t->reference);

    // Someone else's receipt is not visible.
    $other = User::factory()->create(['must_change_password' => false]);
    $other->assignRole('Customer');
    $this->actingAs($other)->get(route('topup.transactions.show', $t->reference))->assertNotFound();
});

test('buyer pages open for a customer, admin pages do not', function () {
    foreach (['dashboard', 'topup.buy', 'topup.wallet', 'topup.transactions', 'topup.sender-ids'] as $r) {
        $this->actingAs($this->user)->get(route($r))->assertOk();
    }
    foreach (array_keys(config('afritopup.categories')) as $c) {
        $this->actingAs($this->user)->get(route('topup.buy.form', $c))->assertOk();
    }
    $this->actingAs($this->user)->getJson(route('topup.services.products', ($this->svc)('mtn-data')))->assertOk()->assertJsonCount(5, 'products');
    foreach (['admin.transactions.index', 'admin.fundings.index', 'admin.services.index', 'topup.developer'] as $r) {
        $this->actingAs($this->user)->get(route($r))->assertForbidden();
    }
});

test('paystack funding credits the wallet once, after verification', function () {
    app(Settings::class)->put(['paystack.active' => '1', 'paystack.mode' => 'sandbox', 'paystack.test_secret_key' => 'sk_test_abc']);
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/x']]),
        'api.paystack.co/transaction/verify/*'   => fn ($req) => Http::response(['status' => true, 'data' => [
            'status' => 'success', 'amount' => 507500, 'currency' => 'NGN', 'id' => 99, 'reference' => basename($req->url())]]),
    ]);

    $r = app(FundingService::class)->start($this->user, 5000, 'https://example.test/cb');
    expect($r['url'])->toBe('https://checkout.paystack.com/x')->and((float) $r['funding']->fee)->toBe(75.0);

    // The webhook (signed with the secret key) and the callback both arrive.
    $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $r['funding']->reference]]);
    $this->call('POST', '/webhook/paystack', [], [], [], ['HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_abc'), 'CONTENT_TYPE' => 'application/json'], $body)->assertOk();
    app(FundingService::class)->confirm($r['funding']->reference);

    expect(WalletFunding::find($r['funding']->id)->status)->toBe('successful')->and((float) $this->wallet->fresh()->balance)->toBe(15000.0);

    // A bad signature is refused.
    $this->call('POST', '/webhook/paystack', [], [], [], ['HTTP_X_PAYSTACK_SIGNATURE' => 'nope', 'CONTENT_TYPE' => 'application/json'], $body)->assertStatus(401);
});

test('the mobile app can sign up, set a PIN and buy', function () {
    \Illuminate\Support\Facades\Mail::fake();
    $token = $this->postJson('/api/v1/auth/register', ['name' => 'Bola Ade', 'email' => 'bola@example.com', 'phone_number' => '08099998888', 'password' => 'Password1'])
        ->assertCreated()->assertJsonPath('data.user.wallet.balance', 0)->assertJsonPath('data.user.email_verified', false)->json('data.token');

    // Buying waits for the e-mailed code.
    $this->withToken($token)->getJson('/api/v1/catalog')->assertForbidden()->assertJsonPath('code', 'email_unverified');
    $code = null;
    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\AccountCodeMail::class, function ($m) use (&$code) { $code = $m->code; return $m->purpose === 'verify'; });
    $this->withToken($token)->postJson('/api/v1/auth/email/verify', ['code' => $code])->assertOk()->assertJsonPath('data.user.email_verified', true);

    $this->withToken($token)->getJson('/api/v1/catalog')->assertOk()->assertJsonPath('data.country.code', 'NG');
    $this->withToken($token)->postJson('/api/v1/wallet/pin', ['password' => 'Password1', 'pin' => '7391'])->assertOk();

    $bola = User::where('email', 'bola@example.com')->first();
    app(WalletService::class)->credit($bola->wallet(), 1000, 'T', 'test');

    $this->withToken($token)->postJson('/api/v1/purchase', ['service_id' => ($this->svc)('mtn')->id, 'recipient' => '08031234567', 'amount' => 300, 'pin' => '7391'])
        ->assertCreated()->assertJsonPath('data.status', 'successful');
    $this->withToken($token)->postJson('/api/v1/purchase', ['service_id' => ($this->svc)('mtn')->id, 'recipient' => '08031234567', 'amount' => 5000, 'pin' => '7391'])
        ->assertStatus(402)->assertJsonPath('code', 'insufficient_funds');
    $this->withToken($token)->getJson('/api/v1/wallet')->assertOk()->assertJsonPath('data.balance', 700);
    $this->withToken($token)->getJson('/api/v1/transactions')->assertOk()->assertJsonPath('meta.total', 1);
});

test('resellers buy through the API with idempotent references and get a webhook', function () {
    Queue::fake();
    $this->user->forceFill(['user_type' => 'reseller'])->save();
    $this->user->assignRole('Reseller');
    [$key, $plain] = ApiKey::issue($this->user, 'Shop', 'https://reseller.test/hook');

    $this->getJson('/api/reseller/v1/balance')->assertStatus(401);
    $this->withToken($plain)->getJson('/api/reseller/v1/balance')->assertOk()->assertJsonPath('data.balance', 10000);
    $this->withToken($plain)->getJson('/api/reseller/v1/services?category=data')->assertOk()->assertJsonFragment(['code' => 'mtn-data']);

    $body = ['reference' => 'ORD-1', 'service' => 'mtn-data', 'product' => 'mtn-1gb-30d', 'recipient' => '08031234567'];
    $this->withToken($plain)->postJson('/api/reseller/v1/purchase', $body)->assertCreated()->assertJsonPath('data.status', 'successful');
    $this->withToken($plain)->postJson('/api/reseller/v1/purchase', $body)->assertOk()->assertJsonPath('data.client_reference', 'ORD-1');

    expect(Transaction::where('client_reference', 'ORD-1')->count())->toBe(1);
    Queue::assertPushed(SendResellerWebhook::class, 1);
    $this->withToken($plain)->getJson('/api/reseller/v1/transactions/ORD-1')->assertOk()->assertJsonPath('data.status', 'successful');

    $key->forceFill(['revoked_at' => now()])->save();
    $this->withToken($plain)->getJson('/api/reseller/v1/balance')->assertStatus(401);
});

test('the VTpass driver reads delivered, pending and failed answers', function () {
    app(Settings::class)->put(['provider.vtpass.api_key' => 'a', 'provider.vtpass.public_key' => 'p', 'provider.vtpass.secret_key' => 's']);
    $mtn = ($this->svc)('mtn');
    $mtn->update(['provider' => 'vtpass']);

    Http::fakeSequence('sandbox.vtpass.com/api/pay')
        ->push(['code' => '000', 'response_description' => 'TRANSACTION SUCCESSFUL', 'content' => ['transactions' => ['status' => 'delivered', 'transactionId' => 'V1', 'total_amount' => 485]]])
        ->push(['code' => '099', 'response_description' => 'TRANSACTION IS PROCESSING', 'content' => ['transactions' => ['status' => 'pending']]])
        ->push(['code' => '018', 'response_description' => 'LOW WALLET BALANCE']);

    $ok = $this->topup->purchase($this->user, ['service_id' => $mtn->id, 'recipient' => '08031234567', 'amount' => 500]);
    expect($ok->status)->toBe('successful')->and($ok->provider_reference)->toBe('V1')->and((float) $ok->cost)->toBe(485.0);

    $wait = $this->topup->purchase($this->user, ['service_id' => $mtn->id, 'recipient' => '08051234567', 'amount' => 500]);
    expect($wait->status)->toBe('processing')->and($wait->meta['request_id'])->toStartWith(now('Africa/Lagos')->format('Ymd'));

    $bad = $this->topup->purchase($this->user, ['service_id' => $mtn->id, 'recipient' => '08071234567', 'amount' => 500]);
    expect($bad->status)->toBe('failed')->and($bad->message)->not->toContain('WALLET')->and((float) $this->wallet->fresh()->balance)->toBe(9000.0);

    Http::assertSent(fn ($r) => $r->hasHeader('api-key', 'a') && $r->hasHeader('secret-key', 's') && $r['serviceID'] === 'mtn');
});

test('admin pages open and staff can refund and adjust wallets', function () {
    $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Administrator'))->first();
    $admin->forceFill(['must_change_password' => false])->save();
    $t = $this->topup->purchase($this->user, ['service_id' => ($this->svc)('mtn')->id, 'recipient' => '08031234567', 'amount' => 500]);

    $this->actingAs($admin)->get(route('admin.transactions.show', $t))->assertOk();
    $this->actingAs($admin)->get(route('admin.services.edit', ($this->svc)('mtn-data')))->assertOk();
    $this->actingAs($admin)->get(route('admin.wallets.show', $this->user))->assertOk();
    $this->actingAs($admin)->get(route('admin.transactions.export'))->assertOk();

    $this->actingAs($admin)->post(route('admin.transactions.refund', $t), ['reason' => 'Not received'])->assertRedirect();
    expect($t->fresh()->status)->toBe('refunded');

    $this->actingAs($admin)->post(route('admin.wallets.adjust', $this->user), ['type' => 'credit', 'amount' => 250, 'note' => 'Bank deposit'])->assertRedirect();
    $this->actingAs($admin)->post(route('admin.wallets.adjust', $this->user), ['type' => 'debit', 'amount' => 99999, 'note' => 'Too much'])->assertSessionHas('error');
    expect((float) $this->wallet->fresh()->balance)->toBe(10250.0);

    $this->actingAs($admin)->post(route('admin.wallets.account', $this->user), ['user_type' => 'agent', 'price_tier_id' => PriceTier::where('code', 'agent')->value('id')])->assertRedirect();
    expect($this->user->fresh()->hasRole('Agent'))->toBeTrue()->and($this->user->fresh()->priceTier->code)->toBe('agent');
});
