<?php

use App\Http\Controllers\Topup\PaystackWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Payment-provider webhooks. No sign-in and no CSRF token; each controller
| checks the provider's signature before trusting anything.
*/

Route::post('/webhook/paystack', PaystackWebhookController::class)->middleware('throttle:120,1,webhooks.paystack')->name('webhooks.paystack');
