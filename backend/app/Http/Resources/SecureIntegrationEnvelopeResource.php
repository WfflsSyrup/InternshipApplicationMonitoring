<?php

namespace App\Http\Resources;

use App\Models\SecureIntegrationEnvelope;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SecureIntegrationEnvelope
 *
 * Metadata only: never exposes ciphertext, tags, IVs, signatures or key material.
 */
class SecureIntegrationEnvelopeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'correlation_id' => $this->correlation_id,
            'application_id' => $this->internship_application_id,
            'application_ref' => $this->whenLoaded('application', fn ($application): ?string => $application?->reference_code)
                ?? $this->applicationReferenceCode(),
            'direction' => $this->direction,
            'direction_label' => $this->directionLabel(),
            'key_id' => $this->key_id,
            'cipher_suite' => $this->cipher_suite,
            'signature_verified' => (bool) $this->signature_verified,
            'replay_checked' => (bool) $this->replay_checked,
            'verification_status' => $this->verification_status,
            'verification_label' => $this->statusLabel(),
            'failure_reason' => $this->failure_reason,
            'payload_sha256' => $this->payload_sha256,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}