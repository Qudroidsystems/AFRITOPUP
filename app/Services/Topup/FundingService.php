<?php

namespace App\Services\Topup;

use App\Exceptions\TopupException;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletFunding;
use App\Services\Messaging\PortalNotifier;
use App\Services\Payments\PaystackGateway;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Money into wallets. Card / transfer top-ups go through Paystack and are
 * credited only after Paystack confirms them (verify call or signed webhook)
 * — never because the customer came back to a "success" page. Crediting is
 * idempotent: the callback and the webhook may both arrive.
 */
class FundingService
{
    public function __construct(protected WalletService $wallets, protected PaystackGateway $paystack)
    {
    }

    /** Fee the customer pays on top of the amount they want in the wallet. */
    public function fee(string|float $amount): string
    {
        $c = (array) config('afritopup.funding');
        $minor = Money::minor($amount);
        $fee = intdiv($minor * (int) round(((float) ($c['fee_percent'] ?? 0)) * 100) + 5000, 10000) + Money::minor($c['fee_flat'] ?? 0);
        $cap = Money::minor($c['fee_cap'] ?? 0);
        if ($cap > 0) $fee = min($fee, $cap);

        return Money::major(max(0, $fee));
    }

    /** @return array{funding: WalletFunding, url: string} the page to send the customer to */
    public function start(User $user, string|float $amount, string $callbackUrl, string $channel = 'web'): array
    {
        $c = (array) config('afritopup.funding');
        if (!is_numeric($amount)) throw new TopupException('Enter the amount.');
        if ((float) $amount < (float) $c['min']) throw new TopupException('The smallest top-up is ' . Money::format($c['min'], $user->currency()) . '.');
        if ((float) $amount > (float) $c['max']) throw new TopupException('The largest top-up is ' . Money::format($c['max'], $user->currency()) . '.');
        if (!$this->paystack->enabled()) throw new TopupException('Card payments are not available right now. Please try again later.', 'gateway_unavailable', 503);

        $wallet = $user->wallet();
        $amount = Money::major(Money::minor($amount));
        $fee = $this->fee($amount);
        $charged = Money::major(Money::minor($amount) + Money::minor($fee));

        $funding = WalletFunding::create([
            'reference' => Reference::make('FND'),
            'user_id'   => $user->id,
            'wallet_id' => $wallet->id,
            'gateway'   => 'paystack',
            'amount'    => $amount,
            'fee'       => $fee,
            'charged'   => $charged,
            'currency'  => $wallet->currency,
            'status'    => 'pending',
            'channel'   => $channel,
        ]);

        try {
            $url = $this->paystack->initialize($user->email, Money::minor($charged), $wallet->currency, $funding->reference, $callbackUrl,
                ['funding' => $funding->reference, 'user_id' => $user->id, 'purpose' => 'wallet_funding']);
        } catch (\Throwable $e) {
            $funding->forceFill(['status' => 'failed', 'note' => mb_substr($e->getMessage(), 0, 255)])->save();
            throw new TopupException('We could not start the payment. Please try again.', 'gateway_error', 502);
        }

        return ['funding' => $funding, 'url' => $url];
    }

    /** Ask Paystack about a top-up and credit the wallet when it was paid. Safe to call many times. */
    public function confirm(string $reference): WalletFunding
    {
        $funding = WalletFunding::where('reference', $reference)->firstOrFail();
        if ($funding->status === 'successful' || $funding->gateway !== 'paystack') return $funding;

        try {
            $data = $this->paystack->verify($reference);
        } catch (\Throwable $e) {
            Log::warning('Paystack verify failed', ['ref' => $reference, 'error' => $e->getMessage()]);

            return $funding;
        }

        return $this->apply($funding, $data);
    }

    /** Paystack's `data` for a transaction (from verify or the webhook). */
    public function apply(WalletFunding $funding, array $data): WalletFunding
    {
        $status = (string) ($data['status'] ?? '');
        if ($status !== 'success') {
            if (in_array($status, ['failed', 'abandoned', 'reversed'], true) && $funding->status === 'pending') {
                $funding->forceFill(['status' => $status === 'abandoned' ? 'abandoned' : 'failed', 'gateway_response' => $this->trim($data)])->save();
            }

            return $funding;
        }

        // The money paid must match what we asked for, in the same currency.
        if ((int) ($data['amount'] ?? 0) < Money::minor($funding->charged) || strtoupper((string) ($data['currency'] ?? '')) !== $funding->currency) {
            Log::error('Paystack amount mismatch', ['ref' => $funding->reference, 'paid' => $data['amount'] ?? null, 'expected' => Money::minor($funding->charged)]);
            $funding->forceFill(['note' => 'Amount or currency did not match — check before crediting.', 'gateway_response' => $this->trim($data)])->save();

            return $funding;
        }

        $credited = DB::transaction(function () use ($funding, $data) {
            $f = WalletFunding::whereKey($funding->id)->lockForUpdate()->first();
            if ($f->status === 'successful') return false;
            $this->wallets->credit($f->wallet, $f->amount, $f->reference, 'Wallet funding (card / transfer)', $f);
            $f->forceFill([
                'status' => 'successful', 'paid_at' => now(),
                'gateway_reference' => (string) ($data['id'] ?? $data['reference'] ?? ''),
                'gateway_response'  => $this->trim($data),
            ])->save();

            return true;
        });

        $funding->refresh();
        if ($credited) {
            event(new \App\Events\WalletFunded($funding));
            PortalNotifier::toUsers([$funding->user_id], 'Wallet funded', Money::format($funding->amount, $funding->currency) . ' has been added to your wallet.',
                \Illuminate\Support\Facades\Route::has('topup.wallet') ? route('topup.wallet') : null, 'wallet', 'funding:' . $funding->reference);
        }

        return $funding;
    }

    // ── Bank transfer (dedicated account) ───────────────────────────────

    /** Fee taken from a bank transfer before it is credited (percent + flat, capped). */
    public function transferFee(string|float $amount): string
    {
        $c = (array) config('afritopup.funding');
        $minor = Money::minor($amount);
        $fee = intdiv($minor * (int) round(((float) ($c['transfer_fee_percent'] ?? 0)) * 100) + 5000, 10000) + Money::minor($c['transfer_fee_flat'] ?? 0);
        $cap = Money::minor($c['transfer_fee_cap'] ?? 0);
        if ($cap > 0) $fee = min($fee, $cap);

        return Money::major(max(0, min($fee, $minor)));
    }

    /** The user's account number, created with Paystack on first request. */
    public function virtualAccount(User $user): \App\Models\VirtualAccount
    {
        if ($va = $user->virtualAccounts()->where('is_active', true)->first()) return $va;
        if (!$this->paystack->enabled()) throw new TopupException('Bank-transfer funding is not available right now.', 'gateway_unavailable', 503);
        if (!$user->phone_number) throw new TopupException('Add your phone number to your profile first.', 'phone_required');

        try {
            if (!$user->paystack_customer_code) {
                $parts = preg_split('/\s+/', trim($user->name)) ?: ['Customer'];
                $code = $this->paystack->customer($user->email, $parts[0], count($parts) > 1 ? end($parts) : $parts[0], $user->phone_number);
                $user->forceFill(['paystack_customer_code' => $code])->save();
            }
            $a = $this->paystack->dedicatedAccount($user->paystack_customer_code, config('afritopup.funding.transfer_bank'));
        } catch (\RuntimeException $e) {
            Log::warning('Dedicated account failed', ['user' => $user->id, 'error' => $e->getMessage()]);
            throw new TopupException('We could not create your account number yet. ' . $e->getMessage(), 'gateway_error', 502);
        }

        return \App\Models\VirtualAccount::updateOrCreate(
            ['provider' => 'paystack', 'account_number' => $a['account_number']],
            ['user_id' => $user->id, 'currency' => $a['currency'], 'bank_name' => $a['bank_name'], 'bank_slug' => $a['bank_slug'],
                'account_name' => $a['account_name'], 'provider_reference' => $a['id'], 'is_active' => true],
        );
    }

    /**
     * A transfer arrived in someone's account number (Paystack webhook, channel "dedicated_nuban").
     * Re-checked with Paystack, then credited once: our reference is derived from Paystack's,
     * so a repeated webhook hits the unique index instead of crediting twice.
     */
    public function creditTransfer(array $webhookData): ?WalletFunding
    {
        $ref = (string) ($webhookData['reference'] ?? '');
        if ($ref === '') return null;
        $ours = 'TRF-' . strtoupper(substr(hash('sha256', $ref), 0, 20));
        if ($existing = WalletFunding::where('reference', $ours)->first()) return $existing;

        try {
            $data = $this->paystack->verify($ref);
        } catch (\Throwable $e) {
            Log::warning('Paystack verify failed for transfer', ['ref' => $ref, 'error' => $e->getMessage()]);

            return null;
        }
        if (($data['status'] ?? '') !== 'success') return null;

        $account = (string) (data_get($data, 'authorization.receiver_bank_account_number') ?: data_get($webhookData, 'authorization.receiver_bank_account_number') ?: data_get($data, 'metadata.receiver_account_number', ''));
        $va = $account !== '' ? \App\Models\VirtualAccount::where('account_number', $account)->first() : null;
        $user = $va?->user ?? User::where('paystack_customer_code', (string) data_get($data, 'customer.customer_code', '#'))->first();
        if (!$user) {
            Log::error('Transfer to an unknown account number', ['ref' => $ref, 'account' => $account]);

            return null;
        }

        $paid = Money::major((int) ($data['amount'] ?? 0));
        $fee = $this->transferFee($paid);
        $credit = Money::major(Money::minor($paid) - Money::minor($fee));
        $wallet = $user->wallet(strtoupper((string) ($data['currency'] ?? 'NGN')));

        try {
            $funding = DB::transaction(function () use ($ours, $user, $wallet, $paid, $fee, $credit, $ref, $data) {
                $f = WalletFunding::create([
                    'reference' => $ours, 'user_id' => $user->id, 'wallet_id' => $wallet->id, 'gateway' => 'transfer',
                    'amount' => $credit, 'fee' => $fee, 'charged' => $paid, 'currency' => $wallet->currency, 'status' => 'successful',
                    'gateway_reference' => $ref, 'gateway_response' => $this->trim($data), 'channel' => 'transfer', 'paid_at' => now(),
                    'note' => trim('From ' . data_get($data, 'authorization.sender_name', '') . ' ' . data_get($data, 'authorization.sender_bank', '')),
                ]);
                if (Money::minor($credit) > 0) $this->wallets->credit($wallet, $credit, $ours, 'Bank transfer', $f);

                return $f;
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return WalletFunding::where('reference', $ours)->first();   // the same webhook, twice at once
        }

        PortalNotifier::toUsers([$user->id], 'Wallet funded', Money::format($credit, $wallet->currency) . ' received by bank transfer.',
            \Illuminate\Support\Facades\Route::has('topup.wallet') ? route('topup.wallet') : null, 'wallet', 'funding:' . $ours);
        event(new \App\Events\WalletFunded($funding));

        return $funding;
    }

    /** Staff: add money to a wallet (bank deposit, promotion, correction). */
    public function manualCredit(Wallet $wallet, string|float $amount, string $note, User $by): WalletFunding
    {
        return DB::transaction(function () use ($wallet, $amount, $note, $by) {
            $amount = Money::major(Money::minor($amount));
            $f = WalletFunding::create([
                'reference' => Reference::make('MAN'), 'user_id' => $wallet->user_id, 'wallet_id' => $wallet->id, 'gateway' => 'manual',
                'amount' => $amount, 'fee' => 0, 'charged' => $amount, 'currency' => $wallet->currency, 'status' => 'successful',
                'note' => mb_substr($note, 0, 255), 'created_by' => $by->id, 'paid_at' => now(), 'channel' => 'web',
            ]);
            $this->wallets->credit($wallet, $amount, $f->reference, 'Manual credit: ' . $note, $f, $by);

            return $f;
        });
    }

    /** Staff: take money out of a wallet (correction, chargeback). */
    public function manualDebit(Wallet $wallet, string|float $amount, string $note, User $by): void
    {
        $this->wallets->debit($wallet, $amount, Reference::make('ADJ'), 'Manual debit: ' . $note, null, $by);
    }

    protected function trim(array $data): array
    {
        return array_intersect_key($data, array_flip(['id', 'status', 'reference', 'amount', 'currency', 'channel', 'paid_at', 'gateway_response', 'fees']));
    }
}
