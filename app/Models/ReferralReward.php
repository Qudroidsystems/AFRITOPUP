<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A referral bonus or commission paid into someone's earnings wallet. */
class ReferralReward extends Model
{
    public const TYPES = ['signup_bonus' => 'Referral bonus', 'welcome_bonus' => 'Welcome bonus', 'commission' => 'Referral commission'];

    protected $fillable = ['user_id', 'from_user_id', 'type', 'source_type', 'source_id', 'amount', 'currency', 'status', 'description'];

    protected $casts = ['amount' => 'decimal:2'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function source()
    {
        return $this->morphTo();
    }

    public function present(): array
    {
        return ['id' => $this->id, 'type' => $this->type, 'type_label' => self::TYPES[$this->type] ?? $this->type, 'amount' => (float) $this->amount,
            'currency' => $this->currency, 'status' => $this->status, 'description' => $this->description,
            'from' => $this->fromUser?->first_name, 'created_at' => $this->created_at?->toIso8601String()];
    }
}
