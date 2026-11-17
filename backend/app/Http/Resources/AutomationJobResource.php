<?php

namespace App\Http\Resources;

use App\Models\AutomationJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AutomationJob
 */
class AutomationJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'correlation_id' => $this->correlation_id,
            'idempotency_key' => $this->idempotency_key,
            'internship_application_id' => $this->internship_application_id,
            'application_reference_code' => $this->whenLoaded('application', fn ($application): ?string => $application?->reference_code),
            'automation_type' => $this->automation_type,
            'automation_type_label' => $this->typeLabel(),
            'recipient_role' => $this->recipient_role,
            'recipient_identifier' => $this->recipient_identifier,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'attempts' => (int) $this->attempts,
            'attempts_label' => $this->attemptsLabel(),
            'max_attempts' => (int) $this->max_attempts,
            'failure_class' => $this->failure_class,
            'last_error_code' => $this->last_error_code,
            'last_error_summary' => $this->last_error_summary,
            'guidance' => $this->guidance(),
            'next_retry_at' => optional($this->next_retry_at)->toIso8601String(),
            'completed_at' => optional($this->completed_at)->toIso8601String(),
            'coordinator_remarks' => $this->coordinator_remarks,
            'payload' => is_array($this->payload) ? $this->payload : [],
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}