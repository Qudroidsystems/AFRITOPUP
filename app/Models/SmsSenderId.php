<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A sender name (max 11 characters) an account may send business SMS from, once approved. */
class SmsSenderId extends Model
{
    protected $fillable = ['user_id', 'sender_id', 'purpose', 'status', 'note', 'reviewed_by', 'reviewed_at'];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
