<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;

/** A purchase finished: successful, failed or refunded. Beneficiaries and rewards listen for it. */
class PurchaseCompleted
{
    use Dispatchable;

    public function __construct(public Transaction $transaction, public string $previousStatus)
    {
    }
}
