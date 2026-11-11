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
            PortalNotifier::toUsers([$funding->user_id], 'Wallet funded', Money::format($funding->amount, $funding->currency) . ' has been added to your wallet.',
                \Illuminate\Support\Facades\Route::has('topup.wallet') ? route('topup.wallet') : null, 'wallet', 'funding:' . $funding->reference);
        }

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
