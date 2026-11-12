<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A user's balance in one currency. Never change `balance` directly:
 * App\Services\Topup\WalletService credits and debits it under a row lock
 * and writes the matching WalletEntry.
 */
class Wallet extends Model
{
    public const MAIN = 'main';
    public const EARNINGS = 'earnings';

    protected $fillable = ['user_id', 'currency', 'type', 'balance', 'is_locked'];

    protected $casts = ['balance' => 'decimal:2', 'is_locked' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function entries()
    {
        return $this->hasMany(WalletEntry::class);
    }

    public function isEarnings(): bool
    {
        return $this->type === self::EARNINGS;
    }

    public function getFormattedBalanceAttribute(): string
    {
        return \App\Support\Money::format($this->balance, $this->currency);
    }
}
