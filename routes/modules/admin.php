<?php

use App\Http\Controllers\Admin\CatalogueController;
use App\Http\Controllers\Admin\SchoolController;
use App\Http\Controllers\Admin\SenderIdController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\WalletAdminController;
use Illuminate\Support\Facades\Route;

// Running the platform. Each controller checks its own permissions. Loaded inside the signed-in group.
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/export', [TransactionController::class, 'export'])->name('transactions.export');
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    Route::post('/transactions/{transaction}/requery', [TransactionController::class, 'requery'])->middleware('throttle:30,1,admin.requery')->name('transactions.requery');
    Route::post('/transactions/{transaction}/refund', [TransactionController::class, 'refund'])->name('transactions.refund');
    Route::post('/transactions/{transaction}/deliver', [TransactionController::class, 'deliver'])->name('transactions.deliver');

    Route::get('/fundings', [WalletAdminController::class, 'fundings'])->name('fundings.index');
    Route::post('/fundings/{funding}/recheck', [WalletAdminController::class, 'recheck'])->name('fundings.recheck');
    Route::get('/wallets', [WalletAdminController::class, 'wallets'])->name('wallets.index');
    Route::get('/wallets/{user}', [WalletAdminController::class, 'show'])->name('wallets.show');
    Route::post('/wallets/{user}/adjust', [WalletAdminController::class, 'adjust'])->name('wallets.adjust');
    Route::post('/wallets/{user}/account', [WalletAdminController::class, 'account'])->name('wallets.account');
    Route::post('/wallets/{user}/lock', [WalletAdminController::class, 'toggleLock'])->name('wallets.lock');

    Route::get('/services', [CatalogueController::class, 'index'])->name('services.index');
    Route::get('/services/create', [CatalogueController::class, 'create'])->name('services.create');
    Route::post('/services', [CatalogueController::class, 'store'])->name('services.store');
    Route::get('/services/{service}/edit', [CatalogueController::class, 'edit'])->name('services.edit');
    Route::put('/services/{service}', [CatalogueController::class, 'update'])->name('services.update');
    Route::post('/services/{service}/toggle', [CatalogueController::class, 'toggle'])->name('services.toggle');
    Route::post('/services/{service}/sync', [CatalogueController::class, 'sync'])->middleware('throttle:10,1,admin.sync')->name('services.sync');
    Route::post('/services/{service}/products', [CatalogueController::class, 'saveProducts'])->name('services.products');

    Route::get('/countries', [CatalogueController::class, 'countries'])->name('countries.index');
    Route::post('/countries/{country}/toggle', [CatalogueController::class, 'toggleCountry'])->name('countries.toggle');
    Route::get('/tiers', [CatalogueController::class, 'tiers'])->name('tiers.index');
    Route::post('/tiers', [CatalogueController::class, 'saveTier'])->name('tiers.store');
    Route::put('/tiers/{tier}', [CatalogueController::class, 'saveTier'])->name('tiers.update');

    Route::get('/sender-ids', [SenderIdController::class, 'index'])->name('sender-ids.index');
    Route::post('/sender-ids/{sender}', [SenderIdController::class, 'decide'])->name('sender-ids.decide');

    Route::get('/schools', [SchoolController::class, 'index'])->name('schools.index');
    Route::post('/schools', [SchoolController::class, 'save'])->name('schools.store');
    Route::put('/schools/{school}', [SchoolController::class, 'save'])->name('schools.update');
});
