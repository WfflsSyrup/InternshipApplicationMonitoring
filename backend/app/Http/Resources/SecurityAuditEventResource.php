<?php

namespace App\Http\Resources;

use App\Models\SecurityAuditEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SecurityAuditEvent
 *
 * The full HMAC hash of the client address is never exposed — only a short
 * prefix so coordinators can correlate events without re-identifying a client.
 */
class SecurityAuditEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'correlation_id' => $this->correlation_id,
            'event_type' => $this->event_type,
            'event_label' => $this->eventLabel(),
            'severity' => $this->severity,
            'severity_label' => $this->severityLabel(),
            'actor_id' => $this->actor_id,
            'actor_role' => $this->actor_role,
            'ip_hash_prefix' => $this->ipHashPrefix(),
            'endpoint' => $this->endpoint,
            'http_status' => (int) $this->http_status,
            'details' => $this->details,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}