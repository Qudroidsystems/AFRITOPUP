<?php

namespace App\Services\Topup\Providers;

use App\Models\Product;
use App\Models\Service;
use App\Models\Transaction;
use App\Services\Settings\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Termii (https://developers.termii.com) for bulk and business SMS.
 * The transaction's meta holds `recipients` (international numbers without +),
 * `message` and `sender`. Up to 100 numbers go through /api/sms/send; more use
 * /api/sms/send/bulk (up to 10,000).
 */
class TermiiProvider implements Provider
{
    public function __construct(protected ?Settings $settings = null)
    {
        $this->settings ??= app(Settings::class);
    }

    public function key(): string
    {
        return 'termii';
    }

    public function name(): string
    {
        return 'Termii';
    }

    protected function cfg(): array
    {
        return $this->settings->provider('termii');
    }

    public function configured(): bool
    {
        return !empty($this->cfg()['api_key']);
    }

    protected function base(): string
    {
        return rtrim((string) ($this->cfg()['base_url'] ?? 'https://api.ng.termii.com'), '/');
    }

    public function purchase(Transaction $t): ProviderResult
    {
        if (!$this->configured()) return ProviderResult::failed('SMS sending is not available right now.');

        $c = $this->cfg();
        $to = array_values((array) ($t->meta['recipients'] ?? []));
        if (!$to) return ProviderResult::failed('There are no recipients.');

        $body = [
            'api_key' => $c['api_key'],
            'to'      => count($to) === 1 ? $to[0] : $to,
            'from'    => $t->meta['sender'] ?? ($c['sender_id'] ?? 'AfriTopup'),
            'sms'     => (string) ($t->meta['message'] ?? ''),
            'type'    => 'plain',
            'channel' => ($t->meta['route'] ?? null) === 'dnd' ? 'dnd' : ($c['channel'] ?? 'generic'),
        ];

        try {
            $res = Http::acceptJson()->asJson()->timeout(60)
                ->post($this->base() . (count($to) > 100 ? '/api/sms/send/bulk' : '/api/sms/send'), $body);
        } catch (ConnectionException $e) {
            return ProviderResult::processing('Waiting for the SMS gateway to confirm.');
        }

        $j = (array) $res->json();
        $raw = array_intersect_key($j, array_flip(['message_id', 'message', 'code', 'balance']));
        if ($res->successful() && (($j['code'] ?? '') === 'ok' || !empty($j['message_id']))) {
            return ProviderResult::successful('Sent to ' . number_format(count($to)) . ' ' . (count($to) === 1 ? 'number' : 'numbers') . '.', (string) ($j['message_id'] ?? ''), null, null, $raw);
        }
        if ($res->serverError()) return ProviderResult::processing('Waiting for the SMS gateway to confirm.', null, $raw);

        return ProviderResult::failed('The SMS could not be sent: ' . ($j['message'] ?? 'HTTP ' . $res->status()) . '.', null, $raw);
    }

    /** Termii has no requery by our reference; a send that timed out is treated as sent once accepted. */
    public function requery(Transaction $t): ProviderResult
    {
        return $t->provider_reference
            ? ProviderResult::successful('Sent.', $t->provider_reference)
            : ProviderResult::processing('Waiting for the SMS gateway to confirm.');
    }

    public function verify(Service $service, string $customerId, ?Product $product = null, array $extra = []): array
    {
        return ['valid' => true, 'name' => null, 'details' => [], 'message' => null];
    }

    public function products(Service $service): array
    {
        return [];
    }

    public function balance(): ?float
    {
        if (!$this->configured()) return null;
        try {
            $res = Http::acceptJson()->timeout(20)->get($this->base() . '/api/get-balance', ['api_key' => $this->cfg()['api_key']]);
        } catch (\Throwable $e) {
            return null;
        }

        return $res->json('balance') !== null ? (float) $res->json('balance') : null;
    }
}
