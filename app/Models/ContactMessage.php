<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    public const TOPICS = [
        'payment'  => 'A payment or purchase',
        'wallet'   => 'Wallet or funding',
        'account'  => 'My account',
        'business' => 'Agents, resellers & API',
        'partner'  => 'Partnership',
        'other'    => 'Something else',
    ];

    protected $fillable = ['user_id', 'name', 'email', 'phone', 'topic', 'message', 'status', 'ip'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
