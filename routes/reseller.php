<?php

use App\Http\Controllers\Api\Reseller\ResellerApiController;
use Illuminate\Support\Facades\Route;

/*
| Reseller API — /api/reseller/v1. Authenticated by API key (see
| App\Http\Middleware\AuthenticateApiKey); documented at /developer/docs.
*/

Route::middleware(['api.key', 'throttle:reseller-api'])->name('reseller.')->group(function () {
    Route::get('balance', [ResellerApiController::class, 'balance'])->name('balance');
    Route::get('services', [ResellerApiController::class, 'services'])->name('services');
    Route::get('services/{code}/products', [ResellerApiController::class, 'products'])->name('products');
    Route::post('verify', [ResellerApiController::class, 'verify'])->name('verify');
    Route::post('purchase', [ResellerApiController::class, 'purchase'])->name('purchase');
    Route::get('transactions/{reference}', [ResellerApiController::class, 'transaction'])->name('transaction');
});
