<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

/*
|--------------------------------------------------------------------------
| AfriTopup — web routes (foundation)
|--------------------------------------------------------------------------
| Authentication, users, roles and permissions, profile, notifications and
| the activity log. Each module (buying, wallet, admin catalogue …) adds its
| own file in routes/modules/ — every file there is loaded automatically
| inside the signed-in group below.
*/

// ── Public website ──────────────────────────────────────────────────
Route::controller(\App\Http\Controllers\Site\SiteController::class)->group(function () {
    Route::get('/', 'home')->name('home');
    Route::get('/pay/{category}', 'service')->where('category', '[a-z]+')->name('site.service');
    Route::get('/pricing', 'pricing')->name('site.pricing');
    Route::get('/app', 'app')->name('site.app');
    Route::get('/contact', 'contact')->name('site.contact');
    Route::post('/contact', 'sendContact')->middleware('throttle:5,10,site.contact')->name('site.contact.send');
    Route::get('/site/services/{service}/plans', 'plans')->name('site.plans');
    Route::post('/quick-pay', 'quickPay')->middleware('throttle:30,1,site.quickpay')->name('site.quickpay');
    Route::get('/{page}', 'page')->where('page', 'about|business|developers|faq|terms|privacy')->name('site.page');
});

Auth::routes([
    'register' => (bool) config('afritopup.allow_registration'),
    'verify'   => false,
]);

// Continue with Google / Apple (the page posts the provider's ID token)
Route::post('/auth/social/{provider}', \App\Http\Controllers\Auth\SocialLoginController::class)
    ->where('provider', 'google|apple')->middleware(['guest', 'throttle:20,1,web.social'])->name('auth.social');

// Payment-provider webhooks (no sign-in; signature-checked)
require __DIR__ . '/webhooks.php';

// Paystack sends the mobile app's browser here after paying; the app closes the web view.
Route::get('/wallet/fund/app-return', fn () => response()->view('topup.wallet.app-return'))->name('topup.fund.app-return');

// CSRF refresh (keeps long-open pages working)
Route::get('/refresh-csrf', function () {
    if (request()->ajax()) {
        Session::regenerateToken();
        return response()->json(['csrf_token' => csrf_token()]);
    }
    abort(404);
})->name('refresh.csrf');

// ── Support chat ─────────────────────────────────────────────────────
// Customers can reach support even before confirming their e-mail.
Route::middleware('auth')->prefix('support')->name('support.')->controller(\App\Http\Controllers\Support\CustomerChatController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/messages', 'messages')->name('messages');
    Route::post('/messages', 'send')->middleware('throttle:30,1,support.send')->name('send');
    Route::post('/typing', 'typing')->middleware('throttle:60,1,support.typing')->name('typing');
    Route::get('/summary', 'summary')->name('summary');
    Route::get('/files/{message}', 'attachment')->name('attachment');
});
Route::middleware(['auth', 'can:Manage support'])->prefix('admin/support')->name('admin.support.')->controller(\App\Http\Controllers\Support\SupportInboxController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/list', 'list')->name('list');
    Route::get('/{conversation}', 'show')->name('show');
    Route::get('/{conversation}/messages', 'messages')->name('messages');
    Route::post('/{conversation}/messages', 'send')->middleware('throttle:60,1,support.staff-send')->name('send');
    Route::post('/{conversation}/typing', 'typing')->name('typing');
    Route::post('/{conversation}/close', 'close')->name('close');
    Route::post('/{conversation}/reopen', 'reopen')->name('reopen');
    Route::post('/{conversation}/assign', 'assign')->name('assign');
});

// ── Confirm your e-mail (signed-in customers who haven't yet) ──────────
Route::controller(\App\Http\Controllers\Auth\EmailVerificationController::class)->group(function () {
    Route::get('/email/verify', 'notice')->middleware('auth')->name('verification.notice');
    Route::post('/email/verify', 'verifyCode')->middleware(['auth', 'throttle:15,10,web.verify'])->name('verification.code');
    Route::post('/email/verification-notification', 'resend')->middleware(['auth', 'throttle:6,10,web.verify-send'])->name('verification.send');
    // The link in the e-mail works even when signed out (signed + expires).
    Route::get('/email/verify/{id}/{hash}', 'verifyLink')->middleware(['signed', 'throttle:20,1,web.verify-link'])->name('verification.verify');
});

Route::middleware(['auth', 'email.confirmed'])->group(function () {

    // ── Temporary password → choose your own ────────────────────────────
    Route::get('/password/change', [PasswordChangeController::class, 'show'])->name('password.change');
    Route::post('/password/change', [PasswordChangeController::class, 'update'])->name('password.change.update');

    // ── Dashboard (app launcher) ────────────────────────────────────────
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Users ───────────────────────────────────────────────────────────
    Route::get('/users/all', [UserController::class, 'allUsers'])->name('users.all');
    Route::get('/users/paginate', [UserController::class, 'paginate'])->name('users.paginate');
    Route::get('/users/roles', [UserController::class, 'roles'])->name('users.roles');
    Route::get('/users/staff/template', [UserController::class, 'generateStaffTemplate'])->name('users.staff.template');
    Route::post('/users/staff/import', [UserController::class, 'importStaffUsers'])->name('users.staff.import');
    Route::post('/users/{id}/toggle-status', [UserController::class, 'toggleStatus'])->whereNumber('id')->name('users.toggle-status');
    Route::get('/user/overview/{id}', [UserController::class, 'show'])->whereNumber('id')->name('users.overview');
    Route::resource('users', UserController::class);

    // ── Roles & permissions ─────────────────────────────────────────────
    Route::post('roles/bulk-remove-users', [RoleController::class, 'bulkRemoveUsers'])->name('roles.bulkremoveusers');
    Route::get('/roles/{role}/users', [RoleController::class, 'getRoleUsers'])->name('roles.users');
    Route::get('/adduser/{id}', [RoleController::class, 'adduser'])->name('roles.adduser');
    Route::post('/updateuserrole', [RoleController::class, 'updateuserrole'])->name('roles.updateuserrole');
    Route::delete('roles/removeuserrole/{userid}/{roleid}', [RoleController::class, 'removeuserrole'])->name('roles.removeuserrole');
    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class);

    // ── Customer area: profile & security ───────────────────────────────
    Route::get('/account/profile', [\App\Http\Controllers\Account\AccountController::class, 'profile'])->name('account.profile');

    // ── Statements & PDF receipts ───────────────────────────────────────
    Route::controller(\App\Http\Controllers\Account\StatementController::class)->group(function () {
        Route::get('/statement', 'index')->name('account.statement');
        Route::get('/statement/download', 'download')->middleware('throttle:20,1,statement.dl')->name('account.statement.download');
        Route::post('/statement/email', 'email')->middleware('throttle:5,10,statement.email')->name('account.statement.email');
        Route::get('/receipts/{reference}.pdf', 'receipt')->name('account.receipt.pdf');
        Route::post('/account/receipts', 'receiptsPreference')->name('account.receipts');
    });

    // ── My profile ──────────────────────────────────────────────────────
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/settings/{id}', [ProfileController::class, 'show'])->whereNumber('id')->name('settings');
        Route::post('/update-info', [ProfileController::class, 'updateInfo'])->name('update-info');
        Route::post('/update-avatar', [ProfileController::class, 'updateAvatar'])->name('update-avatar');
        Route::post('/update-email', [ProfileController::class, 'updateEmail'])->name('update-email');
        Route::post('/update-password', [ProfileController::class, 'updatePassword'])->name('update-password');
    });

    // ── Notifications (the bell) ────────────────────────────────────────
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/settings', [NotificationController::class, 'mute'])->name('notifications.settings');
    Route::get('/me/status', [\App\Http\Controllers\StatusController::class, 'show'])->name('status.show');
    Route::post('/me/status', [\App\Http\Controllers\StatusController::class, 'update'])->middleware('throttle:60,1,web.status.update')->name('status.update');
    Route::post('/notifications/clear-read', [NotificationController::class, 'clearRead'])->name('notifications.clear-read');
    Route::get('/notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::post('/notifications/{id}/actions/{action}', [NotificationController::class, 'act'])->where('action', '[a-z_]{2,20}')->middleware('throttle:60,1,web.notifications.act')->name('notifications.act');

    // ── Activity log & who's online ─────────────────────────────────────
    Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity.index');
    Route::get('/activity-log/export', [ActivityLogController::class, 'export'])->name('activity.export');
    Route::get('/online-staff', [ActivityLogController::class, 'online'])->name('online-staff.index');
    Route::get('/online-staff/count', [ActivityLogController::class, 'onlineCount'])->name('online-staff.count');

    // ── Modules ─────────────────────────────────────────────────────────
    foreach (glob(__DIR__ . '/modules/*.php') ?: [] as $moduleRoutes) {
        require $moduleRoutes;
    }
});
