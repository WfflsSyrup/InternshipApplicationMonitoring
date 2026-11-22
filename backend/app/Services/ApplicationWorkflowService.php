<?php

namespace App\Services;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\ApplicationStatusLog;
use App\Models\AutomationJob;
use App\Models\InternshipApplication;
use App\Models\User;
use App\Services\Automation\AutomationJobDraft;
use App\Services\Automation\AutomationJobService;
use App\Support\ApplicationStatusMachine;
use App\Support\AutomationRecipientResolver;
use Illuminate\Support\Facades\DB;

class ApplicationWorkflowService
{
    /**
     * Statuses that emit an event-driven milestone reminder.
     *
     * @var array<int, string>
     */
    public const MILESTONE_STATUSES = [
        InternshipApplication::STATUS_SUBMITTED,
        InternshipApplication::STATUS_ENDORSED,
        InternshipApplication::STATUS_PARTNER_SUBMITTED,
        InternshipApplication::STATUS_INTERVIEW_SCHEDULED,
        InternshipApplication::STATUS_PLACEMENT_CONFIRMED,
    ];

    public function __construct(private readonly AutomationJobService $automationJobs)
    {
    }

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
        $expectedVersion = isset($data['expected_version']) ? (int) $data['expected_version'] : null;

        return DB::transaction(function () use ($application, $actor, $data, $target, $expectedVersion): InternshipApplication {
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

            if ($expectedVersion !== null) {
                $updatedRows = InternshipApplication::where('id', $locked->id)
                    ->where('version', $expectedVersion)
                    ->update(array_merge(
                        ['status' => $target, 'version' => $expectedVersion + 1],
                        $this->extractStateChanges($locked),
                    ));

                if ($updatedRows === 0) {
                    $latest = InternshipApplication::find($locked->id);
                    throw new InvalidStateTransitionException(
                        'Conflict detected: this application was modified by another session. Reload the latest version and retry.',
                        409,
                        ['version' => ['version_conflict'], 'latest_version' => $latest?->version ?? 0],
                    );
                }

                // Refresh the locked model to get the new version
                $locked->refresh();
            } else {
                $locked->version = $locked->version + 1;
                $locked->save();
            }

            $log = $this->record($locked, $actor, $from, $target, $data['remarks'] ?? null);

            $this->enqueueMilestone($locked, $from, $target, $log);

            return $locked->load('statusLogs.actor');
        });
    }

    /**
     * Extract state changes from the locked application for CAS update.
     */
    private function extractStateChanges(InternshipApplication $application): array
    {
        $changes = [];

        $fillable = $application->getFillable();
        foreach ($fillable as $field) {
            if ($field === 'version' || $field === 'id' || $field === 'status') {
                continue;
            }
            $changes[$field] = $application->getAttribute($field);
        }

        return $changes;
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

    /**
     * Week 13 milestone automation: every qualifying transition enqueues a
     * reminder exactly once per status log entry.
     */
    private function enqueueMilestone(InternshipApplication $application, string $from, string $target, ApplicationStatusLog $log): void
    {
        if ($from === $target || ! in_array($target, self::MILESTONE_STATUSES, true)) {
            return;
        }

        [$recipientRole, $recipientIdentifier] = AutomationRecipientResolver::forMilestone($application, $target);

        $this->automationJobs->enqueue(new AutomationJobDraft(
            type: AutomationJob::TYPE_MILESTONE_REMINDER,
            idempotencyKey: AutomationJobService::milestoneKey(
                (int) $application->getKey(),
                $target,
                $log->getKey(),
            ),
            applicationId: (int) $application->getKey(),
            recipientRole: $recipientRole,
            recipientIdentifier: $recipientIdentifier,
            payload: [
                'reason' => 'milestone_reached',
                'from_status' => $from,
                'to_status' => $target,
                'status_log_id' => $log->getKey(),
                'reference_code' => $application->reference_code,
                'host_company_name' => $application->host_company_name,
                'target_role' => $application->target_role,
            ],
        ));
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