<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A market AfriTopup operates in (or will). Inactive countries are hidden from customers. */
class Country extends Model
{
    protected $fillable = ['code', 'name', 'currency', 'dial_code', 'phone_pattern', 'flag', 'is_active', 'position'];

    protected $casts = ['is_active' => 'boolean', 'position' => 'integer'];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('position')->orderBy('name');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'country_code', 'code');
    }

    public static function byCode(?string $code): ?self
    {
        return $code ? static::where('code', strtoupper($code))->first() : null;
    }

    /** "08031234567" or "+2348031234567" → "08031234567" for NG; other countries keep their local form. */
    public function localPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($this->dial_code && str_starts_with($digits, $this->dial_code) && strlen($digits) > strlen($this->dial_code) + 6) {
            $digits = '0' . substr($digits, strlen($this->dial_code));
        }

        return $digits;
    }

    public function validPhone(string $phone): bool
    {
        $local = $this->localPhone($phone);

        return $this->phone_pattern ? (bool) preg_match('/' . $this->phone_pattern . '/', $local) : strlen($local) >= 7;
    }
}
