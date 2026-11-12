<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A number / meter / smartcard someone buys for often. Saved automatically after a successful purchase. */
class Beneficiary extends Model
{
    protected $fillable = ['user_id', 'category', 'service_id', 'recipient', 'label', 'uses', 'last_used_at'];

    protected $casts = ['last_used_at' => 'datetime', 'uses' => 'integer'];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function present(): array
    {
        return [
            'id' => $this->id, 'category' => $this->category, 'recipient' => $this->recipient, 'label' => $this->label,
            'service' => $this->service ? ['id' => $this->service->id, 'code' => $this->service->code, 'name' => $this->service->display_name, 'color' => $this->service->color] : null,
            'uses' => $this->uses, 'last_used_at' => $this->last_used_at?->toIso8601String(),
        ];
    }

    /** Remember a recipient after a successful purchase (not SMS lists or school fees). */
    public static function remember(Transaction $t): void
    {
        if (!in_array($t->category, ['airtime', 'data', 'cable', 'electricity'], true)) return;
        $b = static::firstOrNew(['user_id' => $t->user_id, 'category' => $t->category, 'recipient' => mb_substr($t->recipient, 0, 60)]);
        $b->service_id = $t->service_id;
        $b->label ??= $t->customer_name ? mb_substr($t->customer_name, 0, 80) : null;
        $b->uses = (int) $b->uses + 1;
        $b->last_used_at = now();
        $b->save();
    }
}
