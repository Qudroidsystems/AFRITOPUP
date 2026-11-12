<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An identity check. The ID number is encrypted; photos are on the private disk. */
class KycSubmission extends Model
{
    public const ID_TYPES = ['nin' => 'NIN', 'bvn' => 'BVN', 'passport' => 'International passport', 'drivers_licence' => "Driver's licence", 'voters_card' => "Voter's card"];

    protected $fillable = ['user_id', 'id_type', 'id_number', 'date_of_birth', 'document_path', 'selfie_path', 'status', 'level_granted', 'note', 'reviewed_by', 'reviewed_at'];

    protected $casts = ['id_number' => 'encrypted', 'date_of_birth' => 'date', 'reviewed_at' => 'datetime'];

    protected $hidden = ['id_number', 'document_path', 'selfie_path'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** "*******4521" */
    public function getMaskedNumberAttribute(): string
    {
        $n = (string) $this->id_number;

        return str_repeat('•', max(0, strlen($n) - 4)) . substr($n, -4);
    }

    public function present(): array
    {
        return ['id' => $this->id, 'id_type' => $this->id_type, 'id_type_label' => self::ID_TYPES[$this->id_type] ?? $this->id_type, 'id_number' => $this->masked_number,
            'status' => $this->status, 'level_granted' => $this->level_granted, 'note' => $this->note, 'created_at' => $this->created_at?->toIso8601String()];
    }
}
