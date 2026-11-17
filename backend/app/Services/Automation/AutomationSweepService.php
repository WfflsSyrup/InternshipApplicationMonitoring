<?php

namespace App\Services\Automation;

use App\Models\AutomationJob;
use App\Models\InternshipApplication;
use App\Support\AutomationRecipientResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AutomationSweepService
{
    public const TYPE_INCOMPLETE = 'incomplete';

    public const TYPE_STALLED = 'stalled';

    public const TYPE_WEEKLY_SUMMARY = 'weekly_summary';

    public const TYPES = [
        self::TYPE_INCOMPLETE,
        self::TYPE_STALLED,
        self::TYPE_WEEKLY_SUMMARY,
    ];

    public function __construct(
        private readonly AutomationJobService $jobs,
        private readonly AutomationDispatcherService $dispatcher,
    ) {
    }

    public function run(string $type, ?string $faultMode = null): array
    {
        return match ($type) {
            self::TYPE_INCOMPLETE => $this->runIncomplete($faultMode),
            self::TYPE_STALLED => $this->runStalled($faultMode),
            self::TYPE_WEEKLY_SUMMARY => $this->runWeekly($faultMode),
            default => throw new \InvalidArgumentException(sprintf('Unsupported automation sweep type [%s].', $type)),
        };
    }

    /**
     * Daily 08:00 sweep: students whose draft / revision_requested application
     * still has an unmet requirement and has been untouched for 48 hours.
     *
     * @return array<string, mixed>
     */
    public function runIncomplete(?string $faultMode = null): array
    {
        $hours = (int) config('automation.sweeps.incomplete_after_hours', 48);
        $cutoff = now()->subHours(max(1, $hours));
        $today = now()->copy();

        $scanned = 0;
        $enqueued = 0;
        $duplicates = 0;

        InternshipApplication::query()
            ->whereIn('status', [
                InternshipApplication::STATUS_DRAFT,
                InternshipApplication::STATUS_REVISION_REQUESTED,
            ])
            ->where('updated_at', '<=', $cutoff)
            ->with('student')
            ->orderBy('id')
            ->chunkById(100, function (Collection $applications) use (&$scanned, &$enqueued, &$duplicates, $today, $hours): void {
                foreach ($applications as $application) {
                    $scanned++;

                    $missing = $this->missingRequirements($application);

                    if ($missing === []) {
                        continue;
                    }

                    [$role, $identifier] = AutomationRecipientResolver::student($application);

                    $job = $this->jobs->enqueue(new AutomationJobDraft(
                        type: AutomationJob::TYPE_INCOMPLETE_RECORD_ALERT,
                        idempotencyKey: AutomationJobService::incompleteKey((int) $application->getKey(), $today),
                        applicationId: (int) $application->getKey(),
                        recipientRole: $role,
                        recipientIdentifier: $identifier,
                        payload: [
                            'reason' => 'requirements_incomplete',
                            'missing_requirements' => $missing,
                            'hours_since_update' => $application->updated_at === null
                                ? null
                                : (int) $application->updated_at->diffInHours(now()),
                            'threshold_hours' => $hours,
                        ],
                    ));

                    $job->wasRecentlyCreated ? $enqueued++ : $duplicates++;
                }
            });

        return $this->finish(self::TYPE_INCOMPLETE, [
            'scanned' => $scanned,
            'enqueued' => $enqueued,
            'duplicates_skipped' => $duplicates,
        ], $faultMode);
    }

    /**
     * Daily 08:30 sweep: applications parked in submitted / requirements_verified /
     * partner_submitted for at least five days without a status log change.
     *
     * @return array<string, mixed>
     */
    public function runStalled(?string $faultMode = null): array
    {
        $days = (int) config('automation.sweeps.stalled_after_days', 5);
        $cutoff = now()->subDays(max(1, $days));
        $today = now()->copy();

        $scanned = 0;
        $enqueued = 0;
        $duplicates = 0;

        InternshipApplication::query()
            ->whereIn('status', [
                InternshipApplication::STATUS_SUBMITTED,
                InternshipApplication::STATUS_REQUIREMENTS_VERIFIED,
                InternshipApplication::STATUS_PARTNER_SUBMITTED,
            ])
            ->where('created_at', '<=', $cutoff)
            ->where(function (Builder $query) use ($cutoff): void {
                $query->whereDoesntHave('statusLogs')
                    ->orWhere(function (Builder $query) use ($cutoff): void {
                        $query
                            ->whereHas('statusLogs', static fn (Builder $logs): Builder => $logs->where('created_at', '<=', $cutoff))
                            ->whereDoesntHave('statusLogs', static fn (Builder $logs): Builder => $logs->where('created_at', '>', $cutoff));
                    });
            })
            ->with(['student', 'adviser'])
            ->orderBy('id')
            ->chunkById(100, function (Collection $applications) use (&$scanned, &$enqueued, &$duplicates, $today, $days, $cutoff): void {
                foreach ($applications as $application) {
                    $scanned++;

                    $latestLog = $application->statusLogs()
                        ->orderByDesc('created_at')
                        ->orderByDesc('id')
                        ->first();

                    $stalledSince = $latestLog->created_at ?? $application->created_at;

                    [$role, $identifier] = AutomationRecipientResolver::coordinator();

                    $job = $this->jobs->enqueue(new AutomationJobDraft(
                        type: AutomationJob::TYPE_STALLED_ESCALATION,
                        idempotencyKey: AutomationJobService::stalledKey(
                            (int) $application->getKey(),
                            (string) $application->status,
                            $today,
                        ),
                        applicationId: (int) $application->getKey(),
                        recipientRole: $role,
                        recipientIdentifier: $identifier,
                        payload: [
                            'reason' => 'stalled_in_status',
                            'status' => $application->status,
                            'stalled_since' => optional($stalledSince)->toIso8601String(),
                            'days_stalled' => $stalledSince === null
                                ? null
                                : (int) $stalledSince->diffInDays(now()),
                            'threshold_days' => $days,
                            'cutoff' => $cutoff->toIso8601String(),
                        ],
                    ));

                    $job->wasRecentlyCreated ? $enqueued++ : $duplicates++;
                }
            });

        return $this->finish(self::TYPE_STALLED, [
            'scanned' => $scanned,
            'enqueued' => $enqueued,
            'duplicates_skipped' => $duplicates,
        ], $faultMode);
    }

    /**
     * Friday 17:00 sweep: pipeline counts by status for the active ISO week.
     *
     * @return array<string, mixed>
     */
    public function runWeekly(?string $faultMode = null): array
    {
        $now = now();
        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->addWeek()->startOfWeek();

        /** @var array<string, int> $counts */
        $counts = InternshipApplication::query()
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(static fn ($total): int => (int) $total)
            ->all();

        $summary = [
            'week_key' => $now->format('o-\WW'),
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->copy()->subDay()->toDateString(),
            'counts' => $counts,
            'total' => array_sum($counts),
        ];

        [$role, $identifier] = AutomationRecipientResolver::coordinator();

        $job = $this->jobs->enqueue(new AutomationJobDraft(
            type: AutomationJob::TYPE_WEEKLY_SUMMARY,
            idempotencyKey: AutomationJobService::weeklyKey($now),
            applicationId: null,
            recipientRole: $role,
            recipientIdentifier: $identifier,
            payload: $summary,
        ));

        return $this->finish(self::TYPE_WEEKLY_SUMMARY, [
            'scanned' => 1,
            'enqueued' => $job->wasRecentlyCreated ? 1 : 0,
            'duplicates_skipped' => $job->wasRecentlyCreated ? 0 : 1,
            'summary' => $summary,
        ], $faultMode);
    }

    /**
     * @return array<int, string>
     */
    private function missingRequirements(InternshipApplication $application): array
    {
        $checklist = array_merge(
            InternshipApplication::DEFAULT_REQUIREMENTS_CHECKLIST,
            $application->requirements_checklist ?? [],
        );

        $missing = [];

        foreach ($checklist as $key => $ready) {
            if ($ready === false || $ready === 0 || $ready === '0') {
                $missing[] = (string) $key;
            }
        }

        return $missing;
    }

    /**
     * @param array<string, mixed> $summary
     *
     * @return array<string, mixed>
     */
    private function finish(string $type, array $summary, ?string $faultMode): array
    {
        $dispatch = $this->dispatcher->dispatchPending(
            (int) config('automation.sweeps.dispatch_limit', 50),
            $faultMode,
        );

        $result = [
            'type' => $type,
            'scanned' => (int) $summary['scanned'],
            'enqueued' => (int) $summary['enqueued'],
            'duplicates_skipped' => (int) $summary['duplicates_skipped'],
        ];

        foreach ($summary as $key => $value) {
            if (! array_key_exists($key, $result)) {
                $result[$key] = $value;
            }
        }

        $result['dispatch'] = $dispatch->toArray();

        return $result;
    }

    /**
     * Weekly placement metrics for the latest succeeded weekly summary job.
     *
     * @return array<string, mixed>|null
     */
    public function latestWeeklySummary(): ?array
    {
        $job = AutomationJob::query()
            ->where('automation_type', AutomationJob::TYPE_WEEKLY_SUMMARY)
            ->where('status', AutomationJob::STATUS_SUCCEEDED)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();

        if ($job === null) {
            return null;
        }

        $payload = is_array($job->payload) ? $job->payload : [];

        return [
            'week_key' => $payload['week_key'] ?? null,
            'week_start' => $payload['week_start'] ?? null,
            'week_end' => $payload['week_end'] ?? null,
            'counts' => is_array($payload['counts'] ?? null) ? $payload['counts'] : [],
            'total' => (int) ($payload['total'] ?? 0),
            'generated_at' => optional($job->completed_at)->toIso8601String(),
            'job_id' => $job->getKey(),
        ];
    }
}