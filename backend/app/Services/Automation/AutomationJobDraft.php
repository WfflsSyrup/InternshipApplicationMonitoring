<?php

namespace App\Services\Automation;

final class AutomationJobDraft
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $type,
        public readonly string $idempotencyKey,
        public readonly ?int $applicationId,
        public readonly string $recipientRole,
        public readonly ?string $recipientIdentifier,
        public readonly array $payload,
    ) {
    }
}