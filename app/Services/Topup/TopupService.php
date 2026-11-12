<?php

namespace App\Services\Topup;

use App\Exceptions\TopupException;
use App\Jobs\SendResellerWebhook;
use App\Models\ApiKey;
use App\Models\Country;
use App\Models\Product;
use App\Models\School;
use App\Models\Service;
use App\Models\SmsSenderId;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Messaging\PortalNotifier;
use App\Services\Topup\Providers\ProviderManager;
use App\Services\Topup\Providers\ProviderResult;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Buying something: check the request, price it for the buyer, debit the
 * wallet, send it to the provider and settle the outcome — refunding the
 * wallet automatically when the provider refuses. Used by the web pages, the
 * mobile API and the reseller API alike.
 */
class TopupService
{
    public function __construct(
        protected WalletService $wallets,
        protected PricingService $pricing,
        protected ProviderManager $providers,
    ) {
    }

    /**
     * $input: service_id, product_id?, recipient, amount?, quantity?, phone?,
     *         meter_type? (electricity), subscription_type? (cable), profile_id? (JAMB),
     *         sender?, message?, recipients? (sms), school_id?, student_name?, student_ref?, session? (school fees),
     *         client_reference? (reseller API idempotency)
     */
    public function purchase(User $user, array $input, string $channel = 'web', ?ApiKey $apiKey = null): Transaction
    {
        if ($user->is_disabled) throw new TopupException('This account is disabled.', 'account_disabled', 403);

        // Reseller retries: the same client_reference returns the purchase already made.
        if (!empty($input['client_reference'])) {
            $existing = Transaction::where('user_id', $user->id)->where('client_reference', $input['client_reference'])->first();
            if ($existing) return $existing;
        }

        $service = Service::with('discounts')->find($input['service_id'] ?? 0);
        if (!$service || !$service->is_active) throw new TopupException('This service is not available.', 'service_unavailable');
        $country = Country::byCode($service->country_code);
        if (!$country || !$country->is_active) throw new TopupException('This service is not available in your country yet.', 'country_unavailable');

        $order = $this->prepare($user, $service, $country, $input);
        $quote = $this->pricing->quote($user, $service, $order['product'], $order['unit_amount'], $order['quantity']);
        $wallet = $user->wallet($country->currency);

        $this->guardDuplicate($user, $service, $order['recipient'], $quote['total']);

        $transaction = DB::transaction(function () use ($user, $service, $country, $order, $quote, $wallet, $channel, $apiKey, $input) {
            $t = Transaction::create([
                'reference'        => Reference::make(),
                'user_id'          => $user->id,
                'wallet_id'        => $wallet->id,
                'category'         => $service->category,
                'service_id'       => $service->id,
                'product_id'       => $order['product']?->id,
                'country_code'     => $country->code,
                'recipient'        => $order['recipient'],
                'customer_name'    => $order['customer_name'],
                'phone'            => $order['phone'],
                'quantity'         => $order['quantity'],
                'amount'           => $quote['amount'],
                'discount'         => $quote['discount'],
                'fee'              => $quote['fee'],
                'total'            => $quote['total'],
                'cost'             => $quote['cost'],
                'currency'         => $country->currency,
                'status'           => Transaction::PENDING,
                'provider'         => $service->provider,
                'channel'          => $channel,
                'api_key_id'       => $apiKey?->id,
                'client_reference' => $input['client_reference'] ?? null,
                'meta'             => $order['meta'] ?: null,
            ]);
            $t->setRelation('service', $service)->setRelation('product', $order['product']);

            if (Money::minor($quote['total']) > 0) {
                $this->wallets->debit($wallet, $quote['total'], $t->reference, $this->describe($t), $t, $user);
            }

            return $t;
        });

        return $this->dispatch($transaction);
    }

    /** Send a pending purchase to its provider and record the answer. */
    public function dispatch(Transaction $t): Transaction
    {
        $t->forceFill(['status' => Transaction::PROCESSING, 'attempts' => $t->attempts + 1, 'last_checked_at' => now()])->save();

        try {
            $result = $this->providers->for($t->service)->purchase($t);
        } catch (\Throwable $e) {
            Log::error('Provider error on purchase', ['ref' => $t->reference, 'provider' => $t->provider, 'error' => $e->getMessage()]);
            $result = ProviderResult::processing('Waiting for the network to confirm.');
        }

        return $this->settle($t, $result);
    }

    /** Ask the provider again about a purchase still processing. */
    public function requery(Transaction $t): Transaction
    {
        if (!$t->isOpen()) return $t;
        $t->forceFill(['attempts' => $t->attempts + 1, 'last_checked_at' => now()])->save();

        try {
            $result = $this->providers->for($t->service)->requery($t);
        } catch (\Throwable $e) {
            Log::warning('Provider error on requery', ['ref' => $t->reference, 'error' => $e->getMessage()]);

            return $t;
        }

        return $this->settle($t, $result);
    }

    /** Record a provider's answer: finish it, keep it processing, or fail and refund. */
    public function settle(Transaction $t, ProviderResult $r): Transaction
    {
        $was = $t->status;
        $t->forceFill([
            'provider_reference' => $r->reference ?: $t->provider_reference,
            'provider_response'  => $r->raw ?: $t->provider_response,
            'message'            => $r->message,
        ]);

        if ($r->status === ProviderResult::SUCCESSFUL) {
            $t->forceFill(['status' => Transaction::SUCCESSFUL, 'token' => $r->token ?? $t->token, 'completed_at' => now()]);
            if ($r->cost !== null && is_numeric($r->cost)) $t->cost = $r->cost;
            $t->save();
        } elseif ($r->status === ProviderResult::FAILED) {
            $t->save();
            $this->reverse($t, Transaction::FAILED, 'Refund: ' . $this->describe($t));
        } else {
            $t->save();
        }

        if ($t->status !== $was && $t->isFinal()) {
            $this->announce($t);
            event(new \App\Events\PurchaseCompleted($t, $was));
        }

        return $t->refresh();
    }

    /** Staff: give the money back for a purchase that is stuck or was not delivered. */
    public function refund(Transaction $t, User $by, string $reason): Transaction
    {
        if (!in_array($t->status, [Transaction::PENDING, Transaction::PROCESSING, Transaction::SUCCESSFUL], true)) {
            throw new TopupException('Only pending, processing or successful purchases can be refunded.');
        }
        $t->forceFill(['message' => mb_substr('Refunded: ' . $reason, 0, 255)])->save();
        $was = $t->status;
        $this->reverse($t, Transaction::REFUNDED, 'Refund: ' . $this->describe($t) . ' — ' . $reason, $by);
        $this->announce($t);
        event(new \App\Events\PurchaseCompleted($t, $was));

        return $t->refresh();
    }

    /** Staff: confirm delivery of a manual purchase (school fees) or one the provider has confirmed by other means. */
    public function markDelivered(Transaction $t, User $by, ?string $note = null, ?string $token = null): Transaction
    {
        if (!$t->isOpen()) throw new TopupException('Only pending or processing purchases can be marked delivered.');
        $t->forceFill([
            'status'       => Transaction::SUCCESSFUL,
            'completed_at' => now(),
            'token'        => $token ?: $t->token,
            'message'      => $note ? mb_substr($note, 0, 255) : 'Delivered.',
            'meta'         => array_merge((array) $t->meta, ['delivered_by' => $by->id]),
        ])->save();
        $this->announce($t);
        event(new \App\Events\PurchaseCompleted($t, Transaction::PROCESSING));

        return $t->refresh();
    }

    /**
     * Check a smartcard / meter / profile ID with the provider.
     * @return array{valid: bool, name: ?string, details: array, message: ?string}
     */
    public function verify(Service $service, string $customerId, ?Product $product = null, array $extra = []): array
    {
        $customerId = trim($customerId);
        if ($customerId === '') return ['valid' => false, 'name' => null, 'details' => [], 'message' => 'Enter the number to check.'];

        return $this->providers->for($service)->verify($service, $customerId, $product, $extra);
    }

    // ── helpers ────────────────────────────────────────────────────────

    /** Validate the request for its category and work out what is being bought. */
    protected function prepare(User $user, Service $service, Country $country, array $in): array
    {
        $product = null;
        if ($service->needsProduct()) {
            $product = Product::where('service_id', $service->id)->where('is_active', true)
                ->where(fn ($q) => $q->where('id', $in['product_id'] ?? 0)->orWhere('code', (string) ($in['product_code'] ?? '#')))->first();
            if (!$product) throw new TopupException('Choose a plan.', 'product_required');
        }

        $quantity = max(1, min(50, (int) ($in['quantity'] ?? 1)));
        $phone = !empty($in['phone']) ? $country->localPhone((string) $in['phone']) : ($user->phone_number ?: null);
        $meta = [];
        $customerName = null;
        $unitAmount = null;
        $recipient = trim((string) ($in['recipient'] ?? ''));

        switch ($service->category) {
            case 'airtime':
            case 'data':
                if (!$country->validPhone($recipient)) throw new TopupException('Enter a valid ' . $country->name . ' phone number.', 'invalid_recipient');
                $recipient = $country->localPhone($recipient);
                $quantity = 1;
                break;

            case 'cable':
            case 'electricity':
                if (!preg_match('/^[0-9A-Za-z-]{6,20}$/', $recipient)) throw new TopupException('Enter a valid ' . strtolower($service->recipientLabel()) . '.', 'invalid_recipient');
                $quantity = 1;
                if ($service->category === 'electricity') {
                    $meta['meter_type'] = in_array($in['meter_type'] ?? 'prepaid', ['prepaid', 'postpaid'], true) ? ($in['meter_type'] ?? 'prepaid') : 'prepaid';
                }
                if ($service->category === 'cable') {
                    $meta['subscription_type'] = ($in['subscription_type'] ?? 'change') === 'renew' ? 'renew' : 'change';
                }
                $check = $this->verify($service, $recipient, $product, $meta);
                if (!$check['valid']) throw new TopupException($check['message'] ?: 'We could not verify that number.', 'verification_failed');
                $customerName = $check['name'];
                if (!$phone) throw new TopupException('Enter a phone number for the receipt.', 'phone_required');
                break;

            case 'education':
                if ($service->code === 'school-fees' || $service->option('school')) {
                    $school = School::where('is_active', true)->where('country_code', $country->code)->find($in['school_id'] ?? 0);
                    if (!$school) throw new TopupException('Choose the school.', 'school_required');
                    $student = trim((string) ($in['student_name'] ?? ''));
                    if (mb_strlen($student) < 3) throw new TopupException('Enter the student\'s full name.', 'student_required');
                    $meta += ['school_id' => $school->id, 'school' => $school->name, 'student_name' => $student,
                        'student_ref' => mb_substr((string) ($in['student_ref'] ?? ''), 0, 60), 'session' => mb_substr((string) ($in['session'] ?? ''), 0, 60),
                        'fee_item' => mb_substr((string) ($in['fee_item'] ?? 'School fees'), 0, 80)];
                    $recipient = $student . ' — ' . $school->name;
                    $quantity = 1;
                } else {
                    if (!empty($in['profile_id'])) {
                        $meta['profile_id'] = preg_replace('/\s+/', '', (string) $in['profile_id']);
                        $check = $this->verify($service, $meta['profile_id'], $product);
                        if (!$check['valid']) throw new TopupException($check['message'] ?: 'We could not verify that profile ID.', 'verification_failed');
                        $customerName = $check['name'];
                    } elseif ($service->option('profile_required')) {
                        throw new TopupException('Enter the candidate\'s profile ID.', 'profile_required');
                    }
                    $recipient = $phone ?: $recipient;
                    if (!$recipient || !$country->validPhone($recipient)) throw new TopupException('Enter a valid phone number to receive the PIN.', 'invalid_recipient');
                    $recipient = $country->localPhone($recipient);
                }
                break;

            case 'sms':
                return $this->prepareSms($user, $service, $country, $in);
        }

        if ($service->needsAmount()) {
            $unitAmount = $in['amount'] ?? null;
            if (!is_numeric($unitAmount) || (float) $unitAmount <= 0) throw new TopupException('Enter the amount.', 'amount_required');
            $unitAmount = Money::major(Money::minor($unitAmount));
            if ($service->min_amount !== null && (float) $unitAmount < (float) $service->min_amount) {
                throw new TopupException('The smallest amount is ' . Money::format($service->min_amount, $country->currency) . '.', 'amount_too_small');
            }
            if ($service->max_amount !== null && (float) $unitAmount > (float) $service->max_amount) {
                throw new TopupException('The largest amount is ' . Money::format($service->max_amount, $country->currency) . '.', 'amount_too_large');
            }
        }

        // A typed amount is priced as typed; a plan is priced from the catalogue.
        return [
            'product'       => $product,
            'unit_amount'   => $product ? null : $unitAmount,
            'quantity'      => $quantity,
            'recipient'     => $recipient,
            'phone'         => $phone,
            'customer_name' => $customerName,
            'meta'          => $meta,
        ];
    }

    protected function prepareSms(User $user, Service $service, Country $country, array $in): array
    {
        $message = trim((string) ($in['message'] ?? ''));
        if ($message === '') throw new TopupException('Write the message.', 'message_required');
        if (mb_strlen($message) > 918) throw new TopupException('The message is too long (6 pages at most).', 'message_too_long');

        $sender = trim((string) ($in['sender'] ?? ''));
        $default = (string) (app(\App\Services\Settings\Settings::class)->provider('termii')['sender_id'] ?? 'AfriTopup');
        if ($sender === '' || strcasecmp($sender, $default) === 0) {
            $sender = $default;
        } elseif (!SmsSenderId::where('user_id', $user->id)->where('sender_id', $sender)->where('status', 'approved')->exists()) {
            throw new TopupException('The sender ID "' . $sender . '" is not approved on your account yet.', 'sender_not_approved');
        }

        $raw = is_array($in['recipients'] ?? null) ? implode(',', $in['recipients']) : (string) ($in['recipients'] ?? $in['recipient'] ?? '');
        $numbers = collect(preg_split('/[\s,;]+/', $raw))->map(fn ($n) => preg_replace('/\D+/', '', (string) $n))->filter()
            ->map(fn ($n) => str_starts_with($n, '0') ? $country->dial_code . substr($n, 1) : $n)
            ->filter(fn ($n) => strlen($n) >= 10 && strlen($n) <= 15)->unique()->values();
        if ($numbers->isEmpty()) throw new TopupException('Add at least one valid phone number.', 'recipients_required');
        $max = (int) config('afritopup.sms.max_recipients', 10000);
        if ($numbers->count() > $max) throw new TopupException('You can send to at most ' . number_format($max) . ' numbers at once.', 'too_many_recipients');

        $len = mb_strlen($message);
        $pages = $len <= 160 ? 1 : (int) ceil($len / 153);
        $perPage = (string) $service->option('price_per_page', '4.00');

        return [
            'product'       => null,
            'unit_amount'   => Money::major(Money::minor($perPage) * $pages),
            'quantity'      => $numbers->count(),
            'recipient'     => $numbers->count() === 1 ? '+' . $numbers[0] : number_format($numbers->count()) . ' recipients',
            'phone'         => $user->phone_number,
            'customer_name' => null,
            'meta'          => ['recipients' => $numbers->all(), 'message' => $message, 'sender' => $sender, 'pages' => $pages, 'route' => ($in['route'] ?? null) === 'dnd' ? 'dnd' : 'generic'],
        ];
    }

    /** The same purchase twice within a minute is almost always a double tap. */
    protected function guardDuplicate(User $user, Service $service, string $recipient, string $total): void
    {
        $dup = Transaction::where('user_id', $user->id)->where('service_id', $service->id)->where('recipient', $recipient)
            ->where('total', $total)->whereIn('status', [Transaction::PENDING, Transaction::PROCESSING, Transaction::SUCCESSFUL])
            ->where('created_at', '>=', now()->subSeconds(60))->exists();
        if ($dup) throw new TopupException('You made the same purchase less than a minute ago. Please wait a moment if you really want to repeat it.', 'duplicate');
    }

    /** Put the money back and close the purchase as failed or refunded (once only). */
    protected function reverse(Transaction $t, string $status, string $description, ?User $by = null): void
    {
        DB::transaction(function () use ($t, $status, $description, $by) {
            $fresh = Transaction::whereKey($t->id)->lockForUpdate()->first();
            if (in_array($fresh->status, [Transaction::FAILED, Transaction::REFUNDED], true)) return;   // already reversed
            if (Money::minor($fresh->total) > 0) {
                $this->wallets->credit($fresh->wallet, $fresh->total, $fresh->reference, $description, $fresh, $by);
            }
            $fresh->forceFill(['status' => $status, 'refunded_at' => now(), 'refunded_by' => $by?->id, 'completed_at' => $fresh->completed_at ?? now()])->save();
            $t->setRawAttributes($fresh->getAttributes(), true);
        });
    }

    protected function describe(Transaction $t): string
    {
        $name = $t->service?->display_name ?? '';
        $what = $t->product?->name ?: trim($name . ' ' . config('afritopup.categories.' . $t->category . '.name', $t->category));

        return $what . ' — ' . $t->recipient;
    }

    /** Bell + push to the buyer, and the reseller's webhook. */
    protected function announce(Transaction $t): void
    {
        try {
            $money = Money::format($t->total, $t->currency);
            [$title, $type] = match ($t->status) {
                Transaction::SUCCESSFUL => ['Purchase successful', 'purchase'],
                Transaction::FAILED     => ['Purchase failed — ' . $money . ' refunded', 'refund'],
                Transaction::REFUNDED   => [$money . ' refunded to your wallet', 'refund'],
                default                 => [null, null],
            };
            if ($title && $t->channel !== 'api') {
                $body = $this->describe($t) . ($t->token ? "\n" . $t->token : '');
                $url = \Illuminate\Support\Facades\Route::has('topup.transactions.show') ? route('topup.transactions.show', $t->reference) : null;
                PortalNotifier::toUsers([$t->user_id], $title, $body, $url, $type, 'txn:' . $t->reference . ':' . $t->status);
            }
            if ($t->api_key_id) SendResellerWebhook::dispatch($t->id);
        } catch (\Throwable $e) {
            Log::warning('Could not announce transaction', ['ref' => $t->reference, 'error' => $e->getMessage()]);
        }
    }
}
