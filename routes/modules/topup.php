<?php

use App\Http\Controllers\Topup\BuyController;
use App\Http\Controllers\Topup\DeveloperController;
use App\Http\Controllers\Topup\MyTransactionController;
use App\Http\Controllers\Topup\WalletController;
use Illuminate\Support\Facades\Route;

// Buying, the wallet and the customer's own history. Loaded inside the signed-in group.
Route::name('topup.')->group(function () {
    Route::get('/buy', [BuyController::class, 'index'])->name('buy');
    Route::post('/buy/country', [BuyController::class, 'switchCountry'])->name('buy.country');
    Route::get('/buy/{category}', [BuyController::class, 'show'])->where('category', '[a-z]+')->name('buy.form');
    Route::post('/buy', [BuyController::class, 'store'])->middleware('throttle:purchase')->name('buy.store');
    Route::get('/services/{service}/products', [BuyController::class, 'products'])->name('services.products');
    Route::post('/services/{service}/verify', [BuyController::class, 'verify'])->middleware('throttle:30,1,topup.verify')->name('services.verify');
    Route::get('/services/{service}/quote', [BuyController::class, 'quote'])->name('services.quote');

    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet');
    Route::post('/wallet/fund', [WalletController::class, 'fund'])->middleware('throttle:10,1,topup.fund')->name('wallet.fund');
    Route::get('/wallet/fund/callback', [WalletController::class, 'callback'])->name('wallet.callback');
    Route::get('/wallet/fund/fee', [WalletController::class, 'fee'])->name('wallet.fee');
    Route::post('/wallet/account', [WalletController::class, 'virtualAccount'])->middleware('throttle:5,1,topup.va')->name('wallet.account');
    Route::get('/beneficiaries', [\App\Http\Controllers\Topup\BeneficiaryController::class, 'index'])->name('beneficiaries');
    Route::put('/beneficiaries/{beneficiary}', [\App\Http\Controllers\Topup\BeneficiaryController::class, 'update'])->name('beneficiaries.update');
    Route::delete('/beneficiaries/{beneficiary}', [\App\Http\Controllers\Topup\BeneficiaryController::class, 'destroy'])->name('beneficiaries.destroy');
    Route::post('/wallet/pin', [WalletController::class, 'pin'])->middleware('throttle:5,1,topup.pin')->name('wallet.pin');

    Route::get('/transactions', [MyTransactionController::class, 'index'])->name('transactions');
    Route::get('/transactions/{reference}', [MyTransactionController::class, 'show'])->name('transactions.show');

    Route::get('/sender-ids', [DeveloperController::class, 'senderIds'])->name('sender-ids');
    Route::post('/sender-ids', [DeveloperController::class, 'requestSenderId'])->middleware('throttle:5,1,topup.sender')->name('sender-ids.request');

    Route::get('/developer', [DeveloperController::class, 'index'])->name('developer');
    Route::get('/developer/docs', [DeveloperController::class, 'docs'])->name('developer.docs');
    Route::post('/developer/keys', [DeveloperController::class, 'store'])->name('developer.keys.store');
    Route::put('/developer/keys/{key}', [DeveloperController::class, 'update'])->name('developer.keys.update');
    Route::post('/developer/keys/{key}/revoke', [DeveloperController::class, 'revoke'])->name('developer.keys.revoke');
});
