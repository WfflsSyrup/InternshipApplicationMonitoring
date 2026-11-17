<?php

namespace App\Services\Automation;

use App\Models\AutomationJob;
use App\Support\LogSanitizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

class AutomationJobService
{
    /**
     * Persists an automation job exactly once per idempotency key.
     *
     * Re-triggering the same window (same milestone log, same sweep day, same
     * ISO week) is a no-op that is logged as `duplicate_skipped`.
     */
    public function enqueue(AutomationJobDraft $draft): AutomationJob
    {
        $idempotencyKey = hash('sha256', $draft->idempotencyKey);

        $existing = AutomationJob::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null) {
            $this->log('duplicate_skipped', $existing, ['reason' => 'idempotency_key already present']);

            return $existing;
        }

        try {
            $job = AutomationJob::query()->create([
                'correlation_id' => (string) Str::uuid(),
                'idempotency_key' => $idempotencyKey,
                'internship_application_id' => $draft->applicationId,
                'automation_type' => $draft->type,
                'recipient_role' => $draft->recipientRole,
                'recipient_identifier' => $draft->recipientIdentifier,
                'payload' => $draft->payload,
                'status' => AutomationJob::STATUS_QUEUED,
                'attempts' => 0,
                'max_attempts' => self::maxAttempts(),
                'next_retry_at' => null,
            ]);
        } catch (QueryException $exception) {
            // Lost a race with a concurrent worker: the unique index already
            // guarantees single delivery, so re-read and treat as duplicate.
            if (! $this->isDuplicateKeyViolation($exception)) {
                throw $exception;
            }

            $job = AutomationJob::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();

            $this->log('duplicate_skipped', $job, ['reason' => 'unique constraint race']);

            return $job;
        }

        $this->log('enqueued', $job);

        return $job;
    }

    /**
     * Builds the deterministic idempotency key for a milestone notification.
     */
    public static function milestoneKey(int $applicationId, string $toStatus, int|string $statusLogId): string
    {
        return sprintf('app:%d:milestone:%s:%s', $applicationId, $toStatus, $statusLogId);
    }

    public static function incompleteKey(int $applicationId, ?Carbon $date = null): string
    {
        return sprintf('app:%d:incomplete:%s', $applicationId, ($date ?? Carbon::now())->toDateString());
    }

    public static function stalledKey(int $applicationId, string $status, ?Carbon $date = null): string
    {
        return sprintf('app:%d:stalled:%s:%s', $applicationId, $status, ($date ?? Carbon::now())->toDateString());
    }

    public static function weeklyKey(?Carbon $date = null): string
    {
        return sprintf('summary:weekly:%s', ($date ?? Carbon::now())->format('o-\WW'));
    }

    public static function maxAttempts(): int
    {
        $configured = (int) config('automation.max_attempts', 3);

        return max(1, min(5, $configured));
    }

    /**
     * @return array<string, mixed>
     */
    private function context(AutomationJob $job): array
    {
        return [
            'job_id' => $job->getKey(),
            'correlation_id' => $job->correlation_id,
            'idempotency_key' => $job->idempotency_key,
            'application_ref' => $job->application?->reference_code,
            'automation_type' => $job->automation_type,
            'attempt' => $job->attempts,
        ];
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function log(string $outcome, AutomationJob $job, array $extra = []): void
    {
        try {
            LogSanitizer::write('info', $this->context($job) + ['outcome' => $outcome] + $extra);
        } catch (Throwable) {
            // Logging must never break the workflow.
        }
    }

    private function isDuplicateKeyViolation(QueryException $exception): bool
    {
        $driver = (string) $exception->getConnectionName();

        if ($driver === '' || $driver === '0') {
            $driver = (string) config('database.default');
        }

        return match ($driver) {
            'sqlite' => str_contains($exception->getMessage(), 'UNIQUE constraint failed'),
            'pgsql' => $exception->getCode() === '23505',
            default => $exception->getCode() === '23000',
        };
    }
}