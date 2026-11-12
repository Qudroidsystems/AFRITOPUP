<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoSeeder::class);
    $this->admin = User::where('email', 'admin@afritopup.test')->first();
});

it('shows "coming soon" store buttons until the admin adds the links', function () {
    $this->get('/')->assertOk()->assertSee('Coming soon to')->assertDontSee('id="appBar"', false);
    $this->get(route('site.app'), ['User-Agent' => 'Mozilla/5.0 (Linux; Android 14)'])->assertRedirect(route('home') . '#app');
});

it('lets the admin set store, social and contact links, and the website uses them', function () {
    $play = 'https://play.google.com/store/apps/details?id=com.afritopup.app';
    $ios = 'https://apps.apple.com/app/afritopup/id6400000000';

    $this->actingAs($this->admin)->get(route('portal-settings.website'))->assertOk()->assertSee('Google Play link');
    $this->actingAs($this->admin)->post(route('portal-settings.website.save'), [
        'android' => $play, 'ios' => $ios, 'instagram' => 'https://instagram.com/afritopup', 'whatsapp' => '0803 123 4567',
        'support_email' => 'help@afritopup.com', 'support_phone' => '+234 803 123 4567',
    ])->assertSessionHas('success');

    // A fresh request reads the saved settings.
    app()->forgetInstance(\App\Services\Settings\Settings::class);
    app(\App\Services\Settings\Settings::class)->applySiteToConfig();
    \Illuminate\Support\Facades\View::share('brand', config('afritopup'));

    $this->get('/')->assertSee($play, false)->assertSee($ios, false)->assertSee('https://instagram.com/afritopup', false)->assertSee('id="appBar"', false);
    $this->get('/contact')->assertSee('help@afritopup.com')->assertSee('https://wa.me/2348031234567', false);

    $this->get(route('site.app'), ['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)'])->assertRedirect($ios);
    $this->get(route('site.app'), ['User-Agent' => 'Mozilla/5.0 (Linux; Android 14; Pixel 8)'])->assertRedirect($play);
    $this->get(route('site.app'), ['User-Agent' => 'Mozilla/5.0 (Macintosh)'])->assertRedirect(route('home') . '#app');
});

it('rejects links that are not the right store, and keeps customers out', function () {
    $this->actingAs($this->admin)->post(route('portal-settings.website.save'), ['android' => 'https://example.com/app.apk', 'ios' => 'http://apps.apple.com/x'])
        ->assertSessionHasErrors(['android', 'ios']);

    $customer = User::where('email', 'customer@afritopup.test')->first();
    $this->actingAs($customer)->get(route('portal-settings.website'))->assertForbidden();
});
