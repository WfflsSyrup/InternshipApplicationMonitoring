<?php

namespace App\Http\Resources;

use App\Models\InternshipApplication;
use App\Models\User;
use App\Support\ApplicationStatusMachine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\InternshipApplication
 */
class InternshipApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_code' => $this->reference_code,
            'status' => $this->status,
            'status_label' => ApplicationStatusMachine::label($this->status),
            'host_company_name' => $this->host_company_name,
            'target_role' => $this->target_role,
            'internship_hours_required' => (int) $this->internship_hours_required,
            'target_start_date' => optional($this->target_start_date)->toDateString(),
            'requirements_checklist' => $this->checklist(),
            'requirements_complete' => $this->checklistComplete(),
            'interview_schedule' => optional($this->interview_schedule)->toIso8601String(),
            'interview_notes' => $this->interview_notes,
            'placement_confirmed_at' => optional($this->placement_confirmed_at)->toIso8601String(),
            'placement_acknowledged_at' => optional($this->placement_acknowledged_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'version' => (int) $this->version,
            'student' => new UserResource($this->whenLoaded('student')),
            'adviser' => new UserResource($this->whenLoaded('adviser')),
            'partner_reviewer' => new UserResource($this->whenLoaded('partnerReviewer')),
            'status_logs' => ApplicationStatusLogResource::collection($this->whenLoaded('statusLogs')),
            'actions' => $this->actions($request->user()),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function checklist(): array
    {
        return array_merge(
            InternshipApplication::DEFAULT_REQUIREMENTS_CHECKLIST,
            $this->requirements_checklist ?? [],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function actions(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $actions = [];

        foreach (ApplicationStatusMachine::targetsFor($this->status, (string) $user->role) as $target) {
            $actions[] = [
                'status' => $target,
                'label' => ApplicationStatusMachine::actionLabel($this->status, $target, (string) $user->role),
                'requires_partner_reviewer' => ApplicationStatusMachine::requiresPartnerReviewer($target),
                'requires_interview_schedule' => ApplicationStatusMachine::requiresInterviewSchedule($target),
                'requires_remarks' => ApplicationStatusMachine::requiresRemarks($target),
            ];
        }

        return $actions;
    }
}