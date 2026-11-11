<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A discount level (Customer, Agent, Reseller …). Users without one use the default tier. */
class PriceTier extends Model
{
    protected $fillable = ['code', 'name', 'description', 'is_default', 'position'];

    protected $casts = ['is_default' => 'boolean'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function discounts()
    {
        return $this->hasMany(ServiceTierDiscount::class);
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->first() ?? static::orderBy('position')->first();
    }
}
