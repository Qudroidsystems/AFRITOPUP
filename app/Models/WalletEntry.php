<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line of a wallet statement. Written only by WalletService. */
class WalletEntry extends Model
{
    protected $fillable = ['wallet_id', 'type', 'amount', 'balance_before', 'balance_after', 'reference', 'description', 'source_type', 'source_id', 'created_by', 'meta'];

    protected $casts = ['amount' => 'decimal:2', 'balance_before' => 'decimal:2', 'balance_after' => 'decimal:2', 'meta' => 'array'];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function source()
    {
        return $this->morphTo();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCredit(): bool
    {
        return $this->type === 'credit';
    }
}
