<?php

use App\Models\Beneficiary;
use App\Models\Service;
use App\Models\User;
use App\Models\WalletFunding;
use App\Services\Settings\Settings;
use App\Services\Topup\TopupService;
use App\Services\Topup\WalletService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    app(Settings::class)->put(['paystack.active' => '1', 'paystack.mode' => 'sandbox', 'paystack.test_secret_key' => 'sk_test_abc']);
    $this->user = User::factory()->create(['user_type' => 'customer', 'country_code' => 'NG', 'phone_number' => '08031234567', 'name' => 'Ada Obi', 'must_change_password' => false]);
    $this->user->assignRole('Customer');
    Http::fake([
        'api.paystack.co/customer' => Http::response(['status' => true, 'data' => ['customer_code' => 'CUS_abc']]),
        'api.paystack.co/dedicated_account' => Http::response(['status' => true, 'data' => ['id' => 77, 'account_number' => '9930000001', 'account_name' => 'AFRITOPUP/ADA OBI', 'currency' => 'NGN', 'bank' => ['name' => 'Test Bank', 'slug' => 'test-bank']]]),
        'api.paystack.co/transaction/verify/*' => fn ($r) => Http::response(['status' => true, 'data' => [
            'status' => 'success', 'amount' => 1000000, 'currency' => 'NGN', 'id' => 5, 'reference' => basename($r->url()), 'channel' => 'dedicated_nuban',
            'customer' => ['customer_code' => 'CUS_abc'], 'authorization' => ['receiver_bank_account_number' => '9930000001', 'sender_name' => 'ADA OBI', 'sender_bank' => 'GTBank']]]),
    ]);
});

test('a customer gets an account number once, in the app and on the web', function () {
    $token = $this->user->createToken('t')->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/wallet/account')->assertOk()->assertJsonPath('data', null);
    $this->withToken($token)->postJson('/api/v1/wallet/account')->assertCreated()->assertJsonPath('data.account_number', '9930000001');
    $this->withToken($token)->postJson('/api/v1/wallet/account')->assertCreated();
    expect($this->user->virtualAccounts()->count())->toBe(1)->and($this->user->fresh()->paystack_customer_code)->toBe('CUS_abc');
    Http::assertSentCount(2);   // customer + account, only the first time

    $this->actingAs($this->user)->get(route('topup.wallet'))->assertOk()->assertSee('9930000001');
});

test('a transfer is verified, credited minus the fee, and never twice', function () {
    $this->actingAs($this->user)->post(route('topup.wallet.account'));
    $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'T123', 'channel' => 'dedicated_nuban', 'amount' => 1000000,
        'authorization' => ['receiver_bank_account_number' => '9930000001']]]);
    $send = fn () => $this->call('POST', '/webhook/paystack', [], [], [], ['HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_abc'), 'CONTENT_TYPE' => 'application/json'], $body);

    $send()->assertOk();
    $send()->assertOk();

    $f = WalletFunding::where('gateway', 'transfer')->sole();
    expect((float) $f->charged)->toBe(10000.0)->and((float) $f->fee)->toBe(100.0)->and((float) $f->amount)->toBe(9900.0)
        ->and((float) $this->user->wallet()->fresh()->balance)->toBe(9900.0);
});

test('successful purchases are remembered as beneficiaries', function () {
    app(WalletService::class)->credit($this->user->wallet(), 5000, 'T', 'test');
    $mtn = Service::where('code', 'mtn')->first();
    $topup = app(TopupService::class);
    $topup->purchase($this->user, ['service_id' => $mtn->id, 'recipient' => '08051112222', 'amount' => 100]);
    $topup->purchase($this->user, ['service_id' => Service::where('code', 'ikeja-electric')->first()->id, 'recipient' => '45012345678', 'amount' => 1000, 'phone' => '08031234567']);
    $topup->purchase($this->user, ['service_id' => $mtn->id, 'recipient' => '08050000000', 'amount' => 100]);   // fails: not remembered

    expect(Beneficiary::where('user_id', $this->user->id)->count())->toBe(2)
        ->and(Beneficiary::where('recipient', '45012345678')->value('label'))->toStartWith('Test Customer');

    $token = $this->user->createToken('t')->plainTextToken;
    $id = $this->withToken($token)->getJson('/api/v1/beneficiaries?category=airtime')->assertOk()->assertJsonCount(1, 'data')->json('data.0.id');
    $this->withToken($token)->putJson("/api/v1/beneficiaries/$id", ['label' => 'Mum'])->assertOk()->assertJsonPath('data.label', 'Mum');
    $this->withToken($token)->deleteJson("/api/v1/beneficiaries/$id")->assertOk();
    $this->actingAs($this->user)->getJson(route('topup.beneficiaries'))->assertOk()->assertJsonCount(1, 'beneficiaries');
});
