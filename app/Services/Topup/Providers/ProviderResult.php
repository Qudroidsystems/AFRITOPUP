<?php

namespace App\Services\Topup\Providers;

/** What a provider said about a purchase. */
final class ProviderResult
{
    public const SUCCESSFUL = 'successful';
    public const PROCESSING = 'processing';
    public const FAILED = 'failed';

    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly ?string $reference = null,
        public readonly ?string $token = null,
        public readonly ?string $cost = null,
        public readonly array $raw = [],
    ) {
    }

    public static function successful(string $message = 'Delivered.', ?string $reference = null, ?string $token = null, ?string $cost = null, array $raw = []): self
    {
        return new self(self::SUCCESSFUL, $message, $reference, $token, $cost, $raw);
    }

    public static function processing(string $message = 'Your request is being processed.', ?string $reference = null, array $raw = []): self
    {
        return new self(self::PROCESSING, $message, $reference, null, null, $raw);
    }

    public static function failed(string $message, ?string $reference = null, array $raw = []): self
    {
        return new self(self::FAILED, $message, $reference, null, null, $raw);
    }
}
