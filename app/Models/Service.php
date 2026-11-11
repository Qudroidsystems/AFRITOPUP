<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A biller in one country: MTN airtime, DStv, Ikeja Electric, WAEC … fulfilled by `provider`. */
class Service extends Model
{
    protected $fillable = ['category', 'country_code', 'code', 'name', 'short_name', 'logo', 'color', 'provider', 'provider_code',
        'min_amount', 'max_amount', 'fee', 'options', 'is_active', 'position'];

    protected $casts = ['min_amount' => 'decimal:2', 'max_amount' => 'decimal:2', 'fee' => 'decimal:2', 'options' => 'array', 'is_active' => 'boolean'];

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    public function products()
    {
        return $this->hasMany(Product::class)->orderBy('position')->orderBy('price');
    }

    public function discounts()
    {
        return $this->hasMany(ServiceTierDiscount::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('position')->orderBy('name');
    }

    public function scopeIn(Builder $q, string $country): Builder
    {
        return $q->where('country_code', strtoupper($country));
    }

    public function categoryConfig(): array
    {
        return (array) config('afritopup.categories.' . $this->category, []);
    }

    /** Does the customer pick a plan? A service's options can override its category (e.g. school fees: no plans, typed amount). */
    public function needsProduct(): bool
    {
        return (bool) $this->option('product', $this->categoryConfig()['product'] ?? false);
    }

    public function needsAmount(): bool
    {
        return (bool) $this->option('amount', $this->categoryConfig()['amount'] ?? false);
    }

    public function needsVerification(): bool
    {
        return (bool) $this->option('verify', $this->categoryConfig()['verify'] ?? false);
    }

    /** What the recipient field is called on the form: "Phone number", "Meter number" … */
    public function recipientLabel(): string
    {
        return (string) $this->option('recipient_label', $this->categoryConfig()['recipient_label'] ?? 'Recipient');
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return data_get($this->options, $key, $default);
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) return null;

        return str_starts_with($this->logo, 'http') ? $this->logo : asset($this->logo);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->short_name ?: $this->name;
    }
}
