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

    /**
     * The plan's code at a vendor. Each vendor names the same plan differently (VTpass "mtn-10mb-100",
     * ClubKonnect "500.0" …); the codes are kept in meta.vendor_codes, with `code` as the fallback.
     */
    public function codeFor(?string $vendor): string
    {
        $codes = (array) data_get($this->meta, 'vendor_codes', []);

        return (string) ($vendor && isset($codes[$vendor]) && $codes[$vendor] !== '' ? $codes[$vendor] : $this->code);
    }

    /** Does this plan have its own code at the vendor (set by importing plans, or typed on the plan)? */
    public function hasCodeFor(string $vendor): bool
    {
        return filled(data_get($this->meta, 'vendor_codes.' . $vendor));
    }

    public function setCodeFor(string $vendor, ?string $code): void
    {
        $meta = (array) $this->meta;
        if ($code === null || $code === '') unset($meta['vendor_codes'][$vendor]);
        else $meta['vendor_codes'][$vendor] = $code;
        if (empty($meta['vendor_codes'])) unset($meta['vendor_codes']);
        $this->meta = $meta ?: null;
    }
}
