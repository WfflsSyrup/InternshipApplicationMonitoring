<?php

namespace App\Http\Resources;

use App\Support\ApplicationStatusMachine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ApplicationStatusLog
 */
class ApplicationStatusLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'internship_application_id' => $this->internship_application_id,
            'actor_id' => $this->actor_id,
            'actor_name' => $this->whenLoaded('actor', fn ($actor): ?string => $actor?->name),
            'actor_role' => $this->actor_role,
            'from_status' => $this->from_status,
            'from_status_label' => $this->from_status === null
                ? null
                : ApplicationStatusMachine::label($this->from_status),
            'to_status' => $this->to_status,
            'to_status_label' => ApplicationStatusMachine::label($this->to_status),
            'remarks' => $this->remarks,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}