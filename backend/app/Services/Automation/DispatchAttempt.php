<?php

namespace App\Services\Automation;

use App\Models\AutomationJob;

final class DispatchAttempt
{
    private function __construct(
        public readonly bool $success,
        public readonly string $failureClass,
        public readonly ?int $httpStatus,
        public readonly ?int $errorCode,
        public readonly ?string $summary,
    ) {
    }

    public static function success(?int $httpStatus, ?string $summary = null): self
    {
        return new self(true, '', $httpStatus, null, $summary);
    }

    public static function failure(string $failureClass, ?int $errorCode, string $summary, ?int $httpStatus = null): self
    {
        return new self(false, $failureClass, $httpStatus ?? $errorCode, $errorCode, $summary);
    }

    public function isPermanent(): bool
    {
        return ! $this->success && $this->failureClass === AutomationJob::FAILURE_PERMANENT;
    }
}