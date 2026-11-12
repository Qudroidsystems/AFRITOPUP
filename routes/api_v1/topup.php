<?php

use App\Http\Controllers\Api\V1\Topup\TopupApiController;
use App\Http\Controllers\Api\V1\Topup\WalletApiController;
use Illuminate\Support\Facades\Route;

// Mobile app: buying and the wallet. Loaded inside the signed-in (Sanctum) group of routes/api_v1.php.
Route::get('catalog', [TopupApiController::class, 'catalog'])->name('topup.catalog');
Route::put('me/country', [TopupApiController::class, 'setCountry'])->name('topup.country');
Route::get('services/{service}/products', [TopupApiController::class, 'products'])->name('topup.products');
Route::post('services/{service}/verify', [TopupApiController::class, 'verify'])->middleware('throttle:30,1,api.topup.verify')->name('topup.verify');
Route::get('services/{service}/quote', [TopupApiController::class, 'quote'])->name('topup.quote');
Route::post('purchase', [TopupApiController::class, 'purchase'])->middleware('throttle:purchase')->name('topup.purchase');
Route::get('transactions', [TopupApiController::class, 'transactions'])->name('topup.transactions');
Route::get('transactions/{reference}', [TopupApiController::class, 'transaction'])->name('topup.transaction');
Route::get('schools', [TopupApiController::class, 'schools'])->name('topup.schools');
Route::get('sms/senders', [TopupApiController::class, 'senders'])->name('topup.senders');
Route::post('sms/senders', [TopupApiController::class, 'requestSender'])->middleware('throttle:5,1,api.topup.sender')->name('topup.senders.request');

Route::get('beneficiaries', [TopupApiController::class, 'beneficiaries'])->name('topup.beneficiaries');
Route::put('beneficiaries/{beneficiary}', [TopupApiController::class, 'renameBeneficiary'])->name('topup.beneficiaries.update');
Route::delete('beneficiaries/{beneficiary}', [TopupApiController::class, 'deleteBeneficiary'])->name('topup.beneficiaries.destroy');

Route::get('wallet', [WalletApiController::class, 'show'])->name('wallet.show');
Route::get('wallet/account', [WalletApiController::class, 'virtualAccount'])->name('wallet.account');
Route::post('wallet/account', [WalletApiController::class, 'createVirtualAccount'])->middleware('throttle:5,1,api.wallet.account')->name('wallet.account.create');
Route::get('wallet/statement', [WalletApiController::class, 'statement'])->name('wallet.statement');
Route::get('wallet/fund/fee', [WalletApiController::class, 'fee'])->name('wallet.fee');
Route::post('wallet/fund', [WalletApiController::class, 'fund'])->middleware('throttle:10,1,api.wallet.fund')->name('wallet.fund');
Route::get('wallet/fund/{reference}', [WalletApiController::class, 'fundStatus'])->name('wallet.fund.status');
Route::post('wallet/pin', [WalletApiController::class, 'pin'])->middleware('throttle:5,1,api.wallet.pin')->name('wallet.pin');
