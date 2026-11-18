<?php

use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Settings\Settings;
use App\Services\Topup\Providers\ProviderManager;
use App\Services\Topup\TopupService;
use App\Services\Topup\WalletService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Administrator'))->first();
    $this->admin->forceFill(['must_change_password' => false])->save();
    $this->user = User::factory()->create(['user_type' => 'customer', 'country_code' => 'NG', 'phone_number' => '08031234567', 'must_change_password' => false]);
    $this->user->assignRole('Customer');
    app(WalletService::class)->credit($this->user->wallet(), 20000, 'TEST-SEED', 'Opening balance');
    $this->svc = fn (string $code) => Service::where('country_code', 'NG')->where('code', $code)->firstOrFail();
    $this->topup = app(TopupService::class);
    app(Settings::class)->put(['provider.clubkonnect.user_id' => 'CK1', 'provider.clubkonnect.api_key' => 'KEY']);
});

test('admins switch a whole category to ClubKonnect, which then fulfils purchases', function () {
    $this->actingAs($this->admin)->get(route('portal-settings.providers'))->assertOk()->assertSee('Vendor routing')->assertSee('ClubKonnect');

    $this->actingAs($this->admin)->post(route('portal-settings.providers.routing'), ['category' => 'airtime', 'country' => 'NG', 'vendor' => 'clubkonnect'])
        ->assertSessionHas('success');
    expect(Service::where('country_code', 'NG')->where('category', 'airtime')->pluck('provider')->unique()->all())->toBe(['clubkonnect']);

    Http::fake(['www.nellobytesystems.com/APIAirtimeV1.asp*' => Http::response(['orderid' => '9001', 'statuscode' => '100', 'status' => 'ORDER_RECEIVED']),
        'www.nellobytesystems.com/APIQueryV1.asp*' => Http::response(['orderid' => '9001', 'statuscode' => '200', 'status' => 'ORDER_COMPLETED', 'amountcharged' => '485'])]);
    $t = $this->topup->purchase($this->user, ['service_id' => ($this->svc)('9mobile')->id, 'recipient' => '08091234567', 'amount' => 500]);
    expect($t->status)->toBe('processing')->and($t->provider)->toBe('clubkonnect')->and($t->provider_reference)->toBe('9001');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'APIAirtimeV1.asp') && $r['MobileNetwork'] === '03' && $r['UserID'] === 'CK1' && $r['Amount'] === '500');

    // Switching away again does not move the purchase in progress: it is re-checked with ClubKonnect.
    app(ProviderManager::class)->switchVendor('airtime', 'NG', 'sandbox');
    $this->topup->requery($t->fresh());
    expect($t->fresh()->status)->toBe('successful')->and((float) $t->fresh()->cost)->toBe(485.0);
});

test('a vendor without keys cannot be switched to, and refusals are refunded', function () {
    $this->actingAs($this->admin)->post(route('portal-settings.providers.routing'), ['category' => 'airtime', 'country' => 'NG', 'vendor' => 'vtpass'])->assertSessionHas('error');

    app(ProviderManager::class)->switchVendor('airtime', 'NG', 'clubkonnect');
    Http::fake(['www.nellobytesystems.com/*' => Http::response(['status' => 'INSUFFICIENT_BALANCE'])]);
    $t = $this->topup->purchase($this->user, ['service_id' => ($this->svc)('mtn')->id, 'recipient' => '08031234567', 'amount' => 1000]);
    expect($t->status)->toBe('failed')->and($t->message)->not->toContain('BALANCE')->and((float) $this->user->wallet()->fresh()->balance)->toBe(20000.0);
});

test('importing plans from a new vendor maps them onto the existing plans', function () {
    $data = ($this->svc)('mtn-data');
    $data->products()->delete();
    $old = Product::create(['service_id' => $data->id, 'code' => 'mtn-1gb-vt', 'name' => 'MTN 1GB - 30 days', 'cost' => 290, 'price' => 320, 'validity' => '30 days', 'is_active' => true]);
    $gone = Product::create(['service_id' => $data->id, 'code' => 'mtn-75gb', 'name' => 'MTN 75GB - 30 days', 'cost' => 15000, 'price' => 16000, 'is_active' => true]);

    Http::fake(['www.nellobytesystems.com/APIDatabundlePlansV2.asp*' => Http::response(['MOBILE_NETWORK' => ['MTN' => [['ID' => '01', 'PRODUCT' => [
        ['PRODUCT_ID' => '1000.0', 'PRODUCT_NAME' => '1 GB - 30 days (SME)', 'PRODUCT_AMOUNT' => '270.00'],
        ['PRODUCT_ID' => '2000.0', 'PRODUCT_NAME' => '2 GB - 30 days (SME)', 'PRODUCT_AMOUNT' => '540.00'],
    ]]], 'Glo' => [['ID' => '02', 'PRODUCT' => [['PRODUCT_ID' => '1000.0', 'PRODUCT_NAME' => 'Glo 1GB', 'PRODUCT_AMOUNT' => '250']]]]]])]);

    $data->update(['provider' => 'clubkonnect']);
    $r = app(ProviderManager::class)->sync($data->fresh(), false, 10);
    expect($r['added'])->toBe(1)->and($r['retired'])->toBe(1)->and($r['total'])->toBe(2);

    $old->refresh();
    expect($old->codeFor('clubkonnect'))->toBe('1000.0')->and($old->code)->toBe('mtn-1gb-vt')->and((float) $old->cost)->toBe(270.0)
        ->and((float) $old->price)->toBe(320.0)->and($old->is_active)->toBeTrue()
        ->and($gone->fresh()->is_active)->toBeFalse();
    $new = Product::where('service_id', $data->id)->where('code', '2000.0')->first();
    expect((float) $new->price)->toBe(594.0)->and($new->is_active)->toBeFalse();

    Http::fake(['www.nellobytesystems.com/APIDatabundleV1.asp*' => Http::response(['orderid' => '77', 'statuscode' => '200', 'status' => 'ORDER_COMPLETED'])]);
    $this->user->forceFill(['transaction_pin' => '2580'])->save();
    $t = $this->topup->purchase($this->user, ['service_id' => $data->id, 'product_id' => $old->id, 'recipient' => '08031234567']);
    expect($t->status)->toBe('successful');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'APIDatabundleV1.asp') && $r['DataPlan'] === '1000.0' && $r['MobileNetwork'] === '01');
});

test('admins add, use and remove a custom vendor on the common VTU API', function () {
    $this->actingAs($this->admin)->post(route('portal-settings.providers.custom.save'), [
        'name' => 'Husmo Data', 'base_url' => 'https://husmo.example.com/', 'token' => 'tok123', 'ids' => ['network' => ['airtel' => '9']],
    ])->assertRedirect(route('portal-settings.providers'));
    $m = app(ProviderManager::class);
    expect($m->exists('custom-husmo-data'))->toBeTrue()->and($m->get('custom-husmo-data')->configured())->toBeTrue()
        ->and(\App\Models\Setting::where('key', 'provider.custom-husmo-data.token')->value('value'))->not->toBe('tok123');

    $this->actingAs($this->admin)->get(route('portal-settings.providers'))->assertOk()->assertSee('Husmo Data')->assertSee('custom-husmo-data');
    $m->switchVendor('airtime', 'NG', 'custom-husmo-data');
    Http::fake(['husmo.example.com/api/topup/' => Http::response(['id' => 55, 'Status' => 'successful', 'paid_amount' => '490'])]);
    $t = $this->topup->purchase($this->user, ['service_id' => ($this->svc)('airtel')->id, 'recipient' => '08021234567', 'amount' => 500]);
    expect($t->status)->toBe('successful')->and($t->provider_reference)->toBe('55')->and($t->provider)->toBe('custom-husmo-data');
    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Token tok123') && (string) $r['network'] === '9' && $r['mobile_number'] === '08021234567');

    $this->actingAs($this->admin)->post(route('portal-settings.providers.custom.delete', 'husmo-data'))->assertSessionHas('error');
    $m->switchVendor('airtime', 'NG', 'sandbox');
    $this->actingAs($this->admin)->post(route('portal-settings.providers.custom.delete', 'husmo-data'))->assertSessionHas('success');
    expect(app(ProviderManager::class)->exists('custom-husmo-data'))->toBeFalse();
});

test('reports show sales, cost and profit, and export CSV', function () {
    $t1 = $this->topup->purchase($this->user, ['service_id' => ($this->svc)('mtn')->id, 'recipient' => '08031234567', 'amount' => 1000]);
    $t1->forceFill(['cost' => 970, 'provider' => 'vtpass'])->save();
    $this->topup->purchase($this->user, ['service_id' => ($this->svc)('glo')->id, 'recipient' => '08050000000', 'amount' => 300]); // fails on the sandbox

    $r = new \App\Services\Reports\ReportService(['range' => '7d']);
    $s = $r->summary();
    expect($s['sales'])->toBe(1000.0)->and($s['cost'])->toBe(970.0)->and($s['profit'])->toBe(30.0)->and($s['count'])->toBe(1)
        ->and($s['successRate'])->toBe(50.0)->and(collect($r->by('provider'))->firstWhere('key', 'vtpass')['profit'])->toBe(30.0)
        ->and(collect($r->daily())->last()['sales'])->toBe(1000.0)->and(count($r->hourly()))->toBe(24);

    $this->actingAs($this->admin)->get(route('admin.reports.index', ['range' => 'month']))->assertOk()
        ->assertSee('Reports &amp; analytics', false)->assertSee('Gross profit')->assertSee('By vendor');
    $this->actingAs($this->admin)->get(route('admin.reports.index', ['range' => 'custom', 'from' => now()->subYear()->format('Y-m-d'), 'to' => now()->format('Y-m-d'), 'category' => 'airtime']))->assertOk();
    $csv = $this->actingAs($this->admin)->get(route('admin.reports.export', ['what' => 'transactions', 'range' => '7d']))->assertOk()->streamedContent();
    expect($csv)->toContain($t1->reference)->toContain('30');
    foreach (['daily', 'categories', 'vendors', 'channels', 'services'] as $what) {
        $this->actingAs($this->admin)->get(route('admin.reports.export', ['what' => $what]))->assertOk();
    }
    $this->actingAs($this->user)->get(route('admin.reports.index'))->assertForbidden();
});

test('pages carry the Pericharge name and the Peridote Global Limited credit', function () {
    $line = 'Pericharge is a product of Peridote Global Limited.';
    $this->get('/')->assertOk()->assertSee($line);
    $this->get(route('login'))->assertOk()->assertSee($line);
    $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertSee($line)->assertSee('Pericharge');
    $this->actingAs($this->user)->get(route('dashboard'))->assertOk()->assertSee($line);
    $this->getJson('/api/v1/app/config')->assertOk()->assertJsonPath('data.product_of', $line);
});
