<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A school that receives fees paid through AfriTopup. Payouts are made by staff. */
class School extends Model
{
    protected $fillable = ['country_code', 'name', 'code', 'city', 'contact_email', 'contact_phone', 'bank_name', 'account_number', 'account_name', 'fee_items', 'is_active'];

    protected $casts = ['fee_items' => 'array', 'is_active' => 'boolean'];

    protected $hidden = ['account_number', 'bank_name', 'account_name'];
}
