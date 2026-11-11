<?php

namespace App\Jobs;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/**
 * Tells a reseller's server that one of their purchases finished.
 * POST {webhook_url} with JSON {event, data}; header X-AfriTopup-Signature =
 * HMAC-SHA256 of the raw body with the key's webhook secret.
 */
class SendResellerWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $transactionId)
    {
    }

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(): void
    {
        $t = Transaction::with('apiKey', 'service', 'product')->find($this->transactionId);
        $key = $t?->apiKey;
        if (!$t || !$key || !$key->webhook_url || !$t->isFinal()) return;

        $body = json_encode(['event' => 'transaction.' . $t->status, 'data' => $t->present()], JSON_UNESCAPED_SLASHES);
        $res = Http::withHeaders([
            'Content-Type'          => 'application/json',
            'X-AfriTopup-Signature' => hash_hmac('sha256', $body, (string) $key->webhook_secret),
        ])->timeout(15)->withBody($body, 'application/json')->post($key->webhook_url);

        if (!$res->successful()) $this->release($this->backoff()[min($this->attempts() - 1, 3)] ?? 600);
    }
}
