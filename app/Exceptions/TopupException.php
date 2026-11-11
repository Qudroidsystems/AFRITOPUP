<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A purchase or wallet operation refused for a reason the customer should
 * see ("Insufficient balance", "Invalid meter number"). `code` is a short
 * machine-readable reason for the app and the reseller API.
 */
class TopupException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason = 'invalid', public readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public static function insufficientFunds(): self
    {
        return new self('Your wallet balance is not enough for this purchase. Please fund your wallet.', 'insufficient_funds', 402);
    }
}
