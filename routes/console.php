<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduler — needs ONE cron job running every minute:
|   * * * * * cd /path/to/afritopup && php artisan schedule:run >> /dev/null 2>&1
*/

// Queued jobs (reseller webhooks, push notifications) on hosting without a queue worker.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping(5);

// Purchases the provider has not confirmed yet.
Schedule::command('topup:requery')->everyFiveMinutes()->withoutOverlapping(10);

Schedule::command('activity:prune --days=365')->dailyAt('02:30');
Schedule::command('notifications:prune --days=180')->dailyAt('02:45');
Schedule::command('notifications:digest')->dailyAt('07:00')->withoutOverlapping(30);
