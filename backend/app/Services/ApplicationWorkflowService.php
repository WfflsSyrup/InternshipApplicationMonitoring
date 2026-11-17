<?php

namespace App\Services;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\ApplicationStatusLog;
use App\Models\InternshipApplication;
use App\Models\User;
use App\Support\ApplicationStatusMachine;
use Illuminate\Support\Facades\DB;

class ApplicationWorkflowService
{
    public function createForStudent(User $student, array $data): InternshipApplication
    {
        return DB::transaction(function () use ($student, $data): InternshipApplication {
            $application = new InternshipApplication([
                'reference_code' => $this->nextReferenceCode(),
                'student_id' => $student->getKey(),
                'host_company_name' => $data['host_company_name'],
                'target_role' => $data['target_role'],
                'internship_hours_required' => $data['internship_hours_required'],
                'target_start_date' => $data['target_start_date'],
                'requirements_checklist' => $this->normalizeChecklist($data['requirements_checklist'] ?? []),
                'status' => InternshipApplication::STATUS_DRAFT,
            ]);

            $application->save();

            $this->record($application, $student, null, InternshipApplication::STATUS_DRAFT, 'Application created.');

            return $application->load('statusLogs.actor');
        });
    }

    public function transition(InternshipApplication $application, User $actor, array $data): InternshipApplication
    {
        $target = (string) $data['target_status'];

        return DB::transaction(function () use ($application, $actor, $data, $target): InternshipApplication {
            $locked = InternshipApplication::query()
                ->whereKey($application->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $from = (string) $locked->status;

            if (! ApplicationStatusMachine::canTransition($from, $target, (string) $actor->role)) {
                throw new InvalidStateTransitionException(sprintf(
                    'Role [%s] may not move an application from [%s] to [%s].',
                    $actor->role,
                    $from,
                    $target,
                ));
            }

            $this->assertTransitionPayload($locked, $actor, $from, $target, $data);
            $this->applyStateChanges($locked, $actor, $from, $target, $data);

            $locked->status = $target;
            $locked->save();

            $this->record($locked, $actor, $from, $target, $data['remarks'] ?? null);

            return $locked->load('statusLogs.actor');
        });
    }

    private function assertTransitionPayload(InternshipApplication $application, User $actor, string $from, string $target, array $data): void
    {
        if (ApplicationStatusMachine::isAcknowledgement($from, $target)) {
            if ($application->placement_acknowledged_at !== null) {
                throw new InvalidStateTransitionException('The placement has already been acknowledged.');
            }

            return;
        }

        if (ApplicationStatusMachine::requiresRemarks($target) && blank($data['remarks'] ?? null)) {
            throw new InvalidStateTransitionException(sprintf('Remarks are required when moving an application to [%s].', $target));
        }

        if (ApplicationStatusMachine::requiresPartnerReviewer($target)) {
            $partnerReviewerId = $data['partner_reviewer_id'] ?? null;

            if (blank($partnerReviewerId)) {
                throw new InvalidStateTransitionException('A partner reviewer must be selected before submitting to a partner.');
            }

            $partnerReviewer = User::query()->whereKey($partnerReviewerId)->first();

            if ($partnerReviewer === null || ! $partnerReviewer->hasRole(User::ROLE_PARTNER_REVIEWER)) {
                throw new InvalidStateTransitionException('The selected partner reviewer is not a valid partner reviewer account.');
            }

            if ($partnerReviewer->getKey() === $application->student_id || $partnerReviewer->getKey() === $actor->getKey()) {
                throw new InvalidStateTransitionException('The selected partner reviewer must be independent of the applicant and the acting coordinator.');
            }
        }

        if (ApplicationStatusMachine::requiresInterviewSchedule($target)) {
            $schedule = $data['interview_schedule'] ?? null;

            if (blank($schedule)) {
                throw new InvalidStateTransitionException('An interview schedule is required before scheduling the interview.');
            }

            if (\Illuminate\Support\Carbon::parse($schedule)->isPast()) {
                throw new InvalidStateTransitionException('The interview schedule must be in the future.');
            }
        }
    }

    private function applyStateChanges(InternshipApplication $application, User $actor, string $from, string $target, array $data): void
    {
        if ($actor->isAdviser()
            && $application->adviser_id === null
            && in_array($target, [
                InternshipApplication::STATUS_REQUIREMENTS_VERIFIED,
                InternshipApplication::STATUS_REVISION_REQUESTED,
                InternshipApplication::STATUS_ENDORSED,
            ], true)) {
            $application->adviser_id = $actor->getKey();
        }

        if (ApplicationStatusMachine::isAcknowledgement($from, $target)) {
            $application->placement_acknowledged_at = now();

            return;
        }

        switch ($target) {
            case InternshipApplication::STATUS_PARTNER_SUBMITTED:
                $application->partner_reviewer_id = $data['partner_reviewer_id'];
                break;
            case InternshipApplication::STATUS_INTERVIEW_SCHEDULED:
                $application->interview_schedule = $data['interview_schedule'];
                $application->interview_notes = $data['interview_notes'] ?? null;
                break;
            case InternshipApplication::STATUS_INTERVIEW_PASSED:
            case InternshipApplication::STATUS_INTERVIEW_FAILED:
                $application->interview_notes = $data['interview_notes'] ?? $application->interview_notes;
                break;
            case InternshipApplication::STATUS_PLACEMENT_CONFIRMED:
                $application->placement_confirmed_at = now();
                break;
        }
    }

    private function record(InternshipApplication $application, User $actor, ?string $from, string $to, ?string $remarks): ApplicationStatusLog
    {
        return $application->statusLogs()->create([
            'actor_id' => $actor->getKey(),
            'actor_role' => (string) $actor->role,
            'from_status' => $from,
            'to_status' => $to,
            'remarks' => $remarks,
        ]);
    }

    private function normalizeChecklist(array $checklist): array
    {
        $allowed = array_keys(InternshipApplication::DEFAULT_REQUIREMENTS_CHECKLIST);

        return array_merge(
            InternshipApplication::DEFAULT_REQUIREMENTS_CHECKLIST,
            collect($checklist)
                ->only($allowed)
                ->map(static fn ($value): bool => (bool) $value)
                ->all(),
        );
    }

    private function nextReferenceCode(): string
    {
        $prefix = sprintf('INT-%04d-', now()->year);

        $latest = InternshipApplication::query()
            ->where('reference_code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('reference_code')
            ->value('reference_code');

        $sequence = $latest === null ? 1 : ((int) substr($latest, -4)) + 1;

        return sprintf('INT-%04d-%04d', now()->year, $sequence);
    }
}