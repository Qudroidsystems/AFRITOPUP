<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One per request, so presence lookups are shared across a page.
        $this->app->scoped(\App\Services\Presence\Presence::class);
    }

    public function boot(): void
    {
        // Pagination in the Bootstrap 5 theme (matches the Velzon UI).
        Paginator::useBootstrapFive();

        // Purchases: counted per account, so one busy reseller can't slow everyone else.
        RateLimiter::for('purchase', fn ($request) => Limit::perMinute(30)->by('buy|' . ($request->user()?->id ?: $request->ip())));
        // Reseller API: per key.
        RateLimiter::for('reseller-api', fn ($request) => Limit::perMinute(120)->by('api|' . ($request->attributes->get('api_key_id') ?: $request->ip())));

        // The Super Administrator passes every permission check, including
        // permissions added by modules that are built later.
        Gate::before(function ($user, $ability) {
            return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin() ? true : null;
        });

        // After a purchase: remember the recipient (beneficiaries).
        \Illuminate\Support\Facades\Event::listen(\App\Events\PurchaseCompleted::class, function ($e) {
            if ($e->transaction->status === \App\Models\Transaction::SUCCESSFUL) {
                try { \App\Models\Beneficiary::remember($e->transaction); } catch (\Throwable $ex) { report($ex); }
            }
        });

        // Branding for every view (layout, login, e-mails).
        View::share('brand', config('afritopup'));
    }
}
