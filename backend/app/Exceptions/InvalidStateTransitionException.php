<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidStateTransitionException extends RuntimeException
{
    /**
     * @var array<string, array<int, string>>|null
     */
    private ?array $errors = null;

    public function __construct(string $message = '', int $code = 0, ?array $errors = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    public function getErrors(): ?array
    {
        return $this->errors;
    }
}