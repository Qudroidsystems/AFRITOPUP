<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Money into a wallet by card / transfer (Paystack) or a manual credit by staff. */
class WalletFunding extends Model
{
    public const GATEWAYS = ['paystack' => 'Paystack', 'manual' => 'Manual credit'];

    protected $fillable = ['reference', 'user_id', 'wallet_id', 'gateway', 'amount', 'fee', 'charged', 'currency', 'status',
        'gateway_reference', 'gateway_response', 'channel', 'note', 'created_by', 'paid_at'];

    protected $casts = ['amount' => 'decimal:2', 'fee' => 'decimal:2', 'charged' => 'decimal:2', 'gateway_response' => 'array', 'paid_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getGatewayLabelAttribute(): string
    {
        return self::GATEWAYS[$this->gateway] ?? ucfirst($this->gateway);
    }
}
