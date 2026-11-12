<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A request to become an agent or reseller. */
class AccountUpgrade extends Model
{
    protected $fillable = ['user_id', 'requested_type', 'business_name', 'business_address', 'monthly_volume', 'reason', 'status', 'note', 'reviewed_by', 'reviewed_at'];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function present(): array
    {
        return ['id' => $this->id, 'requested_type' => $this->requested_type, 'business_name' => $this->business_name, 'status' => $this->status,
            'note' => $this->note, 'created_at' => $this->created_at?->toIso8601String()];
    }
}
