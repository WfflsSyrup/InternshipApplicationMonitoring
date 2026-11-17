<?php

namespace App\Exceptions\Crypto;

use RuntimeException;

/**
 * Thrown when an integration payload fails authenticity, integrity, freshness
 * or replay checks. The status/reason pair is rendered as a JSON envelope by
 * ApiExceptionRenderer so clients can react to the exact rejection reason.
 */
class CryptoVerificationException extends RuntimeException
{
    public function __construct(
        private readonly int $status,
        private readonly string $reasonCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }
}