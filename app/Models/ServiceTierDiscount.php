<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceTierDiscount extends Model
{
    protected $fillable = ['service_id', 'price_tier_id', 'type', 'value'];

    protected $casts = ['value' => 'decimal:2'];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function tier()
    {
        return $this->belongsTo(PriceTier::class, 'price_tier_id');
    }
}
