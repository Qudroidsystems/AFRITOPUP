<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportConversation extends Model
{
    protected $fillable = ['user_id', 'status', 'assigned_to', 'last_message_at', 'last_message_preview', 'last_from_staff', 'customer_unread', 'staff_unread', 'staff_notified_at', 'closed_at'];

    protected $casts = ['last_message_at' => 'datetime', 'staff_notified_at' => 'datetime', 'closed_at' => 'datetime', 'last_from_staff' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages()
    {
        return $this->hasMany(SupportMessage::class, 'conversation_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
