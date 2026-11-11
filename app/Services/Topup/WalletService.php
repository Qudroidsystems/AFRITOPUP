<?php

namespace App\Services\Topup;

use App\Exceptions\TopupException;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The only place wallet balances change. Every credit or debit locks the
 * wallet row, checks the balance, updates it and writes the statement line
 * in one database transaction, so two purchases at once can never spend the
 * same money twice.
 */
class WalletService
{
    public function credit(Wallet $wallet, string|float|int $amount, string $reference, string $description, ?Model $source = null, ?User $by = null, array $meta = []): WalletEntry
    {
        return $this->move($wallet, 'credit', $amount, $reference, $description, $source, $by, $meta);
    }

    /** @throws TopupException when the balance is too low or the wallet is locked */
    public function debit(Wallet $wallet, string|float|int $amount, string $reference, string $description, ?Model $source = null, ?User $by = null, array $meta = []): WalletEntry
    {
        return $this->move($wallet, 'debit', $amount, $reference, $description, $source, $by, $meta);
    }

    protected function move(Wallet $wallet, string $type, string|float|int $amount, string $reference, string $description, ?Model $source, ?User $by, array $meta): WalletEntry
    {
        $minor = Money::minor($amount);
        if ($minor <= 0) throw new TopupException('The amount must be more than zero.');

        return DB::transaction(function () use ($wallet, $type, $minor, $reference, $description, $source, $by, $meta) {
            /** @var Wallet $w */
            $w = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            if ($w->is_locked && $type === 'debit') {
                throw new TopupException('This wallet is on hold. Please contact support.', 'wallet_locked', 423);
            }

            $before = Money::minor($w->balance);
            $after = $type === 'credit' ? $before + $minor : $before - $minor;
            if ($after < 0) throw TopupException::insufficientFunds();

            $w->forceFill(['balance' => Money::major($after)])->save();
            $wallet->setRawAttributes($w->getAttributes(), true);

            return WalletEntry::create([
                'wallet_id'      => $w->id,
                'type'           => $type,
                'amount'         => Money::major($minor),
                'balance_before' => Money::major($before),
                'balance_after'  => Money::major($after),
                'reference'      => $reference,
                'description'    => mb_substr($description, 0, 255),
                'source_type'    => $source?->getMorphClass(),
                'source_id'      => $source?->getKey(),
                'created_by'     => $by?->id,
                'meta'           => $meta ?: null,
            ]);
        });
    }
}
