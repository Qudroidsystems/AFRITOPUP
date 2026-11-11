<?php

use App\Http\Controllers\Settings\SettingsController;
use Illuminate\Support\Facades\Route;

// Platform settings: payment gateways and VTU / SMS provider keys. Loaded inside the signed-in group.
Route::prefix('admin/settings')->name('portal-settings.')->middleware('permission:Manage settings')->group(function () {
    Route::get('/payments', [SettingsController::class, 'payments'])->name('payments');
    Route::post('/payments', [SettingsController::class, 'savePayments'])->name('payments.save');
    Route::post('/payments/test', [SettingsController::class, 'testPayments'])->name('payments.test');
    Route::post('/payments/env', [SettingsController::class, 'useEnv'])->name('payments.env');

    Route::get('/providers', [SettingsController::class, 'providers'])->name('providers');
    Route::post('/providers/{provider}', [SettingsController::class, 'saveProvider'])->where('provider', 'vtpass|termii')->name('providers.save');
    Route::post('/providers/{provider}/test', [SettingsController::class, 'testProvider'])->where('provider', 'vtpass|termii')->middleware('throttle:10,1,settings.providers.test')->name('providers.test');
});
