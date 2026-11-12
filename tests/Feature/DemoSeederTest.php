<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('seeds funded test accounts that can sign in, and is safe to run twice', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoSeeder::class);

    foreach (DemoSeeder::ACCOUNTS as [$email, , $type, , $balance]) {
        $user = User::where('email', $email)->firstOrFail();
        expect($user->user_type)->toBe($type)
            ->and(Hash::check(DemoSeeder::PASSWORD, $user->password))->toBeTrue()
            ->and($user->checkTransactionPin(DemoSeeder::PIN))->toBeTrue()
            ->and((float) $user->wallet('NGN')->balance)->toBe((float) $balance)
            ->and($user->wallets()->where('type', 'main')->count())->toBe(1);
    }

    $customer = User::where('email', 'customer@afritopup.test')->first();
    expect($customer->transactions()->count())->toBe(5)
        ->and($customer->referrer->email)->toBe('agent@afritopup.test');

    $this->postJson('/api/v1/auth/login', ['login' => 'customer@afritopup.test', 'password' => 'password', 'device_name' => 'test'])
        ->assertOk();
});

it('gives demo accounts working web access: buyers can buy, staff reach the admin', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoSeeder::class);

    $customer = User::where('email', 'customer@afritopup.test')->first();
    expect($customer->can('Buy services'))->toBeTrue();
    $this->actingAs($customer)->get(route('topup.wallet'))->assertOk();
    $this->actingAs($customer)->get(route('topup.buy'))->assertOk();

    $admin = User::where('email', 'admin@afritopup.test')->first();
    expect($admin->hasRole('Super Administrator'))->toBeTrue()->and($admin->must_change_password)->toBeFalse();
    $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    $this->actingAs($admin)->get(route('admin.countries.index'))->assertOk();

    $this->post(route('logout'));
    $this->post(route('login'), ['email' => 'finance@afritopup.test', 'password' => 'password'])->assertRedirect();
    $this->assertAuthenticatedAs(User::where('email', 'finance@afritopup.test')->first());
});
