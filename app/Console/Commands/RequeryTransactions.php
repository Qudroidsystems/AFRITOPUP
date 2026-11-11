<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\Topup\TopupService;
use Illuminate\Console\Command;

/** Re-checks purchases the provider has not confirmed yet. Scheduled every five minutes. */
class RequeryTransactions extends Command
{
    protected $signature = 'topup:requery {--limit=100}';

    protected $description = 'Ask providers about purchases that are still processing';

    public function handle(TopupService $topup): int
    {
        $after = (int) config('afritopup.requery_after_minutes', 2);
        $open = Transaction::with('service')->where('status', Transaction::PROCESSING)
            ->where('provider', '!=', 'manual')
            ->where('created_at', '<=', now()->subMinutes($after))
            ->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', now()->subMinutes(2)))
            ->orderBy('id')->limit((int) $this->option('limit'))->get();

        $done = 0;
        foreach ($open as $t) {
            if ($topup->requery($t)->isFinal()) $done++;
        }
        $this->info("Checked {$open->count()}, settled {$done}.");

        return self::SUCCESS;
    }
}
