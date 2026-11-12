<?php

use App\Models\Country;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Settings\Settings;
use App\Services\Topup\TopupService;
use App\Services\Topup\WalletService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Country::where('code', 'GH')->update(['is_active' => true]);
    $this->user = User::factory()->create(['user_type' => 'customer', 'country_code' => 'NG', 'must_change_password' => false]);
    $this->user->assignRole('Customer');
});

test('switching country uses that country\'s catalogue and a wallet in its currency', function () {
    $token = $this->user->createToken('t')->plainTextToken;
    $this->withToken($token)->putJson('/api/v1/me/country', ['country_code' => 'KE'])->assertStatus(422);   // still hidden
    $this->withToken($token)->putJson('/api/v1/me/country', ['country_code' => 'GH'])->assertOk()->assertJsonPath('data.country.currency', 'GHS');
    $this->withToken($token)->getJson('/api/v1/catalog')->assertOk()->assertJsonPath('data.country.code', 'GH')->assertJsonFragment(['code' => 'mtn-gh']);

    $user = $this->user->fresh();
    app(WalletService::class)->credit($user->wallet('GHS'), 50, 'T', 'test');
    $t = app(TopupService::class)->purchase($user, ['service_id' => Service::where('code', 'mtn-gh')->first()->id, 'recipient' => '0241234567', 'amount' => 10]);
    expect($t->status)->toBe('successful')->and($t->currency)->toBe('GHS')->and((float) $user->wallet('GHS')->fresh()->balance)->toBe(40.0)
        ->and((float) $user->wallet('NGN')->fresh()->balance)->toBe(0.0);

    $this->actingAs($user)->post(route('topup.buy.country'), ['country_code' => 'NG'])->assertRedirect(route('topup.buy'));
    expect($user->fresh()->country_code)->toBe('NG');
});

test('reloadly: import networks with plans, then buy and requery', function () {
    app(Settings::class)->put(['provider.reloadly.client_id' => 'id', 'provider.reloadly.client_secret' => 'secret']);
    $op = ['operatorId' => 643, 'name' => 'MTN Ghana Data', 'data' => true, 'bundle' => true, 'denominationType' => 'FIXED',
        'localFixedAmounts' => [5.0, 10.0], 'localFixedAmountsDescriptions' => ['5.00' => '1GB 30 days', '10.00' => '2.5GB 30 days'], 'logoUrls' => ['https://x/logo.png']];
    Http::fake([
        'auth.reloadly.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
        'topups-sandbox.reloadly.com/operators/countries/GH*' => Http::response([$op, ['operatorId' => 150, 'name' => 'Telecel Ghana', 'data' => false, 'bundle' => false, 'denominationType' => 'RANGE', 'localMinAmount' => 1, 'localMaxAmount' => 200]]),
        'topups-sandbox.reloadly.com/operators/643' => Http::response($op),
        'topups-sandbox.reloadly.com/topups' => Http::sequence()
            ->push(['transactionId' => 9001, 'status' => 'SUCCESSFUL', 'operatorTransactionId' => 'OP1'])
            ->push(['transactionId' => 9002, 'status' => 'PENDING']),
        'topups-sandbox.reloadly.com/topups/9002/status' => Http::response(['status' => 'SUCCESSFUL', 'transaction' => ['transactionId' => 9002, 'status' => 'SUCCESSFUL']]),
    ]);

    $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Administrator'))->first();
    $admin->forceFill(['must_change_password' => false])->save();
    $this->actingAs($admin)->post(route('admin.countries.import', Country::where('code', 'GH')->first()))->assertSessionHas('success');

    $data = Service::where('country_code', 'GH')->where('provider_code', '643')->first();
    $airtime = Service::where('country_code', 'GH')->where('provider_code', '150')->first();
    expect($data->category)->toBe('data')->and($data->products()->pluck('code')->all())->toEqual(['5', '10'])
        ->and($airtime->category)->toBe('airtime')->and((float) $airtime->max_amount)->toBe(200.0);

    $user = $this->user;
    $user->forceFill(['country_code' => 'GH'])->save();
    app(WalletService::class)->credit($user->wallet('GHS'), 100, 'T', 'test');
    $t = app(TopupService::class)->purchase($user, ['service_id' => $data->id, 'product_code' => '10', 'recipient' => '0241234567']);
    expect($t->status)->toBe('successful')->and($t->provider_reference)->toBe('9001');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/topups') && $r['operatorId'] === 643 && (float) $r['amount'] === 10.0 && $r['recipientPhone']['countryCode'] === 'GH');

    $t2 = app(TopupService::class)->purchase($user, ['service_id' => $airtime->id, 'recipient' => '0201234567', 'amount' => 20]);
    expect($t2->status)->toBe('processing');
    expect(app(TopupService::class)->requery($t2)->status)->toBe(Transaction::SUCCESSFUL);
});
