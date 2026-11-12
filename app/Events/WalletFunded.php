<?php

namespace App\Events;

use App\Models\WalletFunding;
use Illuminate\Foundation\Events\Dispatchable;

/** Money reached a wallet by card or bank transfer (not manual credits). Rewards listen for it. */
class WalletFunded
{
    use Dispatchable;

    public function __construct(public WalletFunding $funding)
    {
    }
}
