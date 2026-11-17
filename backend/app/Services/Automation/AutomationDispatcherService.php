<?php

namespace App\Services\Automation;

use App\Models\AutomationJob;
use App\Support\LogSanitizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Performs one bounded delivery attempt per job.
 *
 * Failure taxonomy:
 *  - transient  : HTTP 429/502/503/504 (and any other 5xx) or a timeout > 5s
 *                 -> retry with exponential backoff + jitter, then manual review
 *  - permanent  : HTTP 400/401/403/422 (invalid recipient, rejected payload)
 *                 -> no retry, straight to manual review on attempt 1
 */
class AutomationDispatcherService
{
    public function dispatchPending(int $limit = 100, ?string $faultMode = null): DispatchSummary
    {
        $fault = $this->normalizeFaultMode($faultMode);

        $jobs = AutomationJob::query()
            ->due()
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();

        $summary = new DispatchSummary;

        foreach ($jobs as $job) {
            $processed = $this->dispatch($job, $fault);

            $summary->record($processed);
        }

        return $summary;
    }

    /**
     * Runs one attempt. Safe to call repeatedly: a job that already succeeded
     * or is parked in manual review is returned untouched.
     */
    public function dispatch(AutomationJob $job, ?string $faultMode = null): AutomationJob
    {
        $fault = $this->normalizeFaultMode($faultMode);

        if ($job->status === AutomationJob::STATUS_SUCCEEDED
            || $job->status === AutomationJob::STATUS_MANUAL_REVIEW_REQUIRED) {
            return $job;
        }

        $attempts = $job->attempts + 1;
        $startedAt = microtime(true);

        $job->attempts = $attempts;
        $job->status = AutomationJob::STATUS_RETRYING;
        $job->next_retry_at = null;
        $job->save();

        $attempt = $this->runAttempt($job, $fault, $attempts);

        if ($attempt->success) {
            $job->status = AutomationJob::STATUS_SUCCEEDED;
            $job->failure_class = null;
            $job->last_error_code = null;
            $job->last_error_summary = null;
            $job->next_retry_at = null;
            $job->completed_at = now();
            $job->save();

            $this->log('succeeded', $job, $attempt, $startedAt);

            return $job;
        }

        $job->failure_class = $attempt->failureClass;
        $job->last_error_code = $attempt->errorCode;
        $job->last_error_summary = mb_substr((string) $attempt->summary, 0, 500);

        $permanent = $attempt->failureClass === AutomationJob::FAILURE_PERMANENT;
        $exhausted = $attempts >= $job->max_attempts;

        if ($permanent || $exhausted) {
            $job->status = AutomationJob::STATUS_MANUAL_REVIEW_REQUIRED;
            $job->next_retry_at = null;
            $job->save();

            $this->log(
                $permanent ? 'permanent_failure' : 'manual_review_required',
                $job,
                $attempt,
                $startedAt,
            );

            return $job;
        }

        $job->status = AutomationJob::STATUS_RETRYING;
        $job->next_retry_at = now()->addSeconds($this->backoffSeconds($attempts));
        $job->save();

        $this->log('retry_scheduled', $job, $attempt, $startedAt);

        return $job;
    }

    /**
     * Clears a parked job so a coordinator recovery run can retry it.
     */
    public function requeue(AutomationJob $job, ?string $recipientIdentifier, ?string $remarks): AutomationJob
    {
        $job->status = AutomationJob::STATUS_QUEUED;
        $job->attempts = 0;
        $job->failure_class = null;
        $job->last_error_code = null;
        $job->last_error_summary = null;
        $job->next_retry_at = null;
        $job->completed_at = null;
        $job->coordinator_remarks = $remarks;

        if (filled($recipientIdentifier)) {
            $job->recipient_identifier = $recipientIdentifier;
        }

        $job->save();

        $this->log('requeued', $job, null, null, ['remark' => $remarks]);

        return $job;
    }

    public function backoffSeconds(int $attempt): int
    {
        $base = max(1, (int) config('automation.retry.base_seconds', 10));
        $jitter = max(0, (int) config('automation.retry.jitter_seconds', 3));

        return ($base * (2 ** max(0, $attempt - 1))) + random_int(0, $jitter);
    }

    /**
     * @return array<int, string>
     */
    public function faultModes(): array
    {
        return (array) config('automation.fault_modes', []);
    }

    public function faultsAllowed(): bool
    {
        return config('automation.allow_fault_injection') === true;
    }

    /**
     * Returns the effective fault mode, or null when injection is unavailable
     * (unknown mode, or the config gate is closed outside non-production).
     */
    public function resolveFaultMode(?string $faultMode): ?string
    {
        return $this->normalizeFaultMode($faultMode);
    }

    private function runAttempt(AutomationJob $job, ?string $fault, int $attempts): DispatchAttempt
    {
        if ($fault !== null && $fault !== 'none') {
            $simulated = $this->simulateFault($fault, $attempts);

            if ($simulated !== null) {
                return $simulated;
            }
        }

        return match ((string) config('automation.dispatcher.mode', 'log')) {
            'http' => $this->runHttp($job, $attempts),
            'disabled' => DispatchAttempt::success(null, 'Dispatcher disabled by configuration; job persisted only.'),
            default => DispatchAttempt::success(null, 'Dispatcher running in log mode; payload written to the automation log.'),
        };
    }

    private function simulateFault(string $fault, int $attempts): ?DispatchAttempt
    {
        return match ($fault) {
            'transient_once' => $attempts === 1
                ? DispatchAttempt::failure(AutomationJob::FAILURE_TRANSIENT, 503, 'Simulated transient dispatcher outage.')
                : null,
            'transient_always' => DispatchAttempt::failure(
                AutomationJob::FAILURE_TRANSIENT,
                503,
                'Simulated persistent transient dispatcher outage.',
            ),
            'permanent_invalid_recipient' => DispatchAttempt::failure(
                AutomationJob::FAILURE_PERMANENT,
                422,
                'Simulated permanent failure: dispatcher rejected the recipient identifier.',
            ),
            default => null,
        };
    }

    private function runHttp(AutomationJob $job, int $attempts): DispatchAttempt
    {
        $url = (string) config('automation.dispatcher.url');

        if (blank($url)) {
            return DispatchAttempt::failure(
                AutomationJob::FAILURE_PERMANENT,
                0,
                'Dispatcher URL is not configured; fix AUTOMATION_DISPATCHER_URL and requeue.',
            );
        }

        $payload = [
            'correlation_id' => $job->correlation_id,
            'idempotency_key' => $job->idempotency_key,
            'automation_type' => $job->automation_type,
            'application_reference' => $job->application?->reference_code,
            'recipient_role' => $job->recipient_role,
            'recipient' => $job->recipient_identifier,
            'attempt' => $attempts,
            'data' => $job->payload,
        ];

        $request = Http::acceptJson()->timeout(max(1, (int) config('automation.timeout_seconds', 5)));

        $token = (string) config('automation.dispatcher.token');

        if (filled($token)) {
            $request = $request->withToken($token);
        }

        try {
            $response = $request->post($url, $payload);
        } catch (ConnectionException|RequestException) {
            return DispatchAttempt::failure(
                AutomationJob::FAILURE_TRANSIENT,
                408,
                'Dispatcher request timed out after '.(int) config('automation.timeout_seconds', 5).'s.',
            );
        } catch (Throwable $exception) {
            return DispatchAttempt::failure(
                AutomationJob::FAILURE_TRANSIENT,
                0,
                'Dispatcher request failed: '.LogSanitizer::redact($exception->getMessage()),
            );
        }

        $status = $response->status();

        if ($response->successful()) {
            return DispatchAttempt::success($status);
        }

        return DispatchAttempt::failure(
            $this->classify($status),
            $status,
            sprintf('Dispatcher responded with HTTP %d.', $status),
        );
    }

    public function classify(int $status): string
    {
        if (in_array($status, [400, 401, 403, 422], true)) {
            return AutomationJob::FAILURE_PERMANENT;
        }

        if (in_array($status, [429, 502, 503, 504], true) || $status >= 500) {
            return AutomationJob::FAILURE_TRANSIENT;
        }

        return AutomationJob::FAILURE_PERMANENT;
    }

    private function normalizeFaultMode(?string $faultMode): ?string
    {
        if (blank($faultMode) || $faultMode === 'none') {
            return null;
        }

        if (! in_array($faultMode, $this->faultModes(), true)) {
            return null;
        }

        if (! $this->faultsAllowed()) {
            $this->log('fault_injection_ignored', null, null, null, ['fault_mode' => $faultMode]);

            return null;
        }

        return $faultMode;
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function log(string $outcome, ?AutomationJob $job, ?DispatchAttempt $attempt, ?float $startedAt = null, array $extra = []): void
    {
        try {
            LogSanitizer::write('info', [
                'job_id' => $job?->getKey(),
                'correlation_id' => $job?->correlation_id,
                'idempotency_key' => $job?->idempotency_key,
                'application_ref' => $job?->application?->reference_code,
                'automation_type' => $job?->automation_type,
                'attempt' => $job?->attempts,
                'outcome' => $outcome,
                'http_status' => $attempt?->httpStatus,
                'duration_ms' => $startedAt === null ? null : (int) round((microtime(true) - $startedAt) * 1000),
            ] + $extra);
        } catch (Throwable) {
            // Logging must never break automation.
        }
    }
}