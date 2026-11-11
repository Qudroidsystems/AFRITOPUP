<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the front page sends visitors to sign in, and the sign-in page is branded', function () {
    $this->get('/')->assertRedirect(route('login'));
    $this->get('/login')->assertOk()->assertSee(config('afritopup.name'));
    $this->get('/register')->assertOk();
});
