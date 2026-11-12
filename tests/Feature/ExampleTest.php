<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the website home page shows, and the sign-in page is branded', function () {
    $this->get('/')->assertOk()->assertSee(config('afritopup.name'));
    $this->get('/login')->assertOk()->assertSee(config('afritopup.name'));
    $this->get('/register')->assertOk();
});
