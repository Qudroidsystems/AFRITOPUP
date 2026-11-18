<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the website home page shows, and the sign-in page is branded', function () {
    $this->get('/')->assertOk()->assertSee(config('pericharge.name'));
    $this->get('/login')->assertOk()->assertSee(config('pericharge.name'));
    $this->get('/register')->assertOk();
});
