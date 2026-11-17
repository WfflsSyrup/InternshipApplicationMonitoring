<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the payload of the latest succeeded weekly summary automation job.
 */
class AutomationWeeklySummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = is_array($this->resource) ? $this->resource : [];

        return [
            'week_key' => $payload['week_key'] ?? null,
            'week_start' => $payload['week_start'] ?? null,
            'week_end' => $payload['week_end'] ?? null,
            'counts' => is_array($payload['counts'] ?? null) ? $payload['counts'] : [],
            'total' => (int) ($payload['total'] ?? 0),
            'generated_at' => $payload['generated_at'] ?? null,
            'job_id' => $payload['job_id'] ?? null,
        ];
    }
}