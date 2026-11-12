<?php

use App\Models\ContactMessage;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('serves every public page', function () {
    foreach (['/', '/pricing', '/about', '/business', '/developers', '/faq', '/contact', '/terms', '/privacy'] as $url) {
        $this->get($url)->assertOk();
    }
    foreach (array_keys(config('afritopup.categories')) as $cat) {
        $this->get(route('site.service', $cat))->assertOk();
    }
    $this->get('/pay/nonsense')->assertNotFound();
});

it('shows live plans at the customer price', function () {
    $mtn = Service::where('code', 'mtn-data')->first();
    $plan = $mtn->products()->where('is_active', true)->first();

    $this->getJson(route('site.plans', $mtn))->assertOk()
        ->assertJsonFragment(['id' => $plan->id, 'name' => $plan->name]);
    $this->get('/pay/data')->assertSee($plan->name);
});

it('sends a visitor from quick-pay through sign-up straight back to their order', function () {
    $mtn = Service::where('code', 'mtn')->first();

    $this->post(route('site.quickpay'), ['category' => 'airtime', 'service' => 'mtn', 'recipient' => '0803 123 4567', 'amount' => 500])
        ->assertRedirect(route('register'));

    $this->post(route('register'), ['name' => 'New Buyer', 'email' => 'new@example.com', 'phone_number' => '08031234567', 'password' => 'secret123', 'password_confirmation' => 'secret123'])
        ->assertRedirect(route('topup.buy.form', ['category' => 'airtime', 'service' => 'mtn', 'recipient' => '08031234567', 'amount' => 500]));
});

it('takes a signed-in customer from quick-pay to the filled-in buy form', function () {
    $this->seed(DemoSeeder::class);
    $user = User::where('email', 'customer@afritopup.test')->first();
    $plan = Product::whereHas('service', fn ($q) => $q->where('code', 'mtn-data'))->first();

    $to = route('topup.buy.form', ['category' => 'data', 'service' => 'mtn-data', 'recipient' => '08031234567', 'product' => $plan->id]);
    $this->actingAs($user)->post(route('site.quickpay'), ['category' => 'data', 'service' => 'mtn-data', 'recipient' => '08031234567', 'product' => $plan->id])->assertRedirect($to);
    $this->actingAs($user)->get($to)->assertOk()->assertSee('value="' . $plan->id . '"', false)->assertSee('08031234567');
});

it('saves contact messages and ignores bots', function () {
    $this->post(route('site.contact.send'), ['name' => 'Ada', 'email' => 'ada@example.com', 'topic' => 'payment', 'message' => 'My airtime did not arrive, ref AT-123.'])
        ->assertSessionHas('contact_sent');
    $this->post(route('site.contact.send'), ['name' => 'Bot', 'email' => 'b@example.com', 'topic' => 'other', 'message' => 'buy cheap things now', 'website' => 'http://spam'])
        ->assertSessionHas('contact_sent');

    expect(ContactMessage::count())->toBe(1)->and(ContactMessage::first()->name)->toBe('Ada');
});

it('gives customers the customer area and staff the admin panel', function () {
    $this->seed(DemoSeeder::class);
    $customer = User::where('email', 'customer@afritopup.test')->first();
    $admin = User::where('email', 'admin@afritopup.test')->first();

    $this->actingAs($customer)->get(route('dashboard'))->assertOk()->assertSee('a-tabbar', false)->assertSee('Pay again');
    foreach (['topup.buy', 'topup.wallet', 'topup.transactions', 'account.profile', 'notifications.index'] as $r) {
        $this->actingAs($customer)->get(route($r))->assertOk()->assertSee('a-tabbar', false);
    }
    $this->actingAs($customer)->get(route('topup.buy.form', 'data'))->assertOk()->assertSee('a-tabbar', false);

    $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertDontSee('a-tabbar', false);
    $this->actingAs($admin)->get(route('account.profile'))->assertRedirect(route('profile.settings', $admin->id));
});
