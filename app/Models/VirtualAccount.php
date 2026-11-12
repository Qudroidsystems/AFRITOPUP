<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A customer's own bank account number: transfers into it fund their wallet. */
class VirtualAccount extends Model
{
    protected $fillable = ['user_id', 'provider', 'currency', 'bank_name', 'bank_slug', 'account_number', 'account_name', 'provider_reference', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function present(): array
    {
        return ['bank_name' => $this->bank_name, 'account_number' => $this->account_number, 'account_name' => $this->account_name, 'currency' => $this->currency];
    }
}
