<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A plan of a service: "MTN 1GB – 30 days", "DStv Compact", "WAEC result checker". */
class Product extends Model
{
    protected $fillable = ['service_id', 'code', 'name', 'cost', 'price', 'validity', 'meta', 'is_active', 'position'];

    protected $casts = ['cost' => 'decimal:2', 'price' => 'decimal:2', 'meta' => 'array', 'is_active' => 'boolean'];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}
