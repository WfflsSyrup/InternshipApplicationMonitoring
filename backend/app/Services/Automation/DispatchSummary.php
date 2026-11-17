<?php

namespace App\Services\Automation;

use App\Models\AutomationJob;

final class DispatchSummary
{
    public int $processed = 0;

    public int $succeeded = 0;

    public int $retryScheduled = 0;

    public int $manualReviewRequired = 0;

    public function record(AutomationJob $job): void
    {
        $this->processed++;

        match ($job->status) {
            AutomationJob::STATUS_SUCCEEDED => $this->succeeded++,
            AutomationJob::STATUS_MANUAL_REVIEW_REQUIRED => $this->manualReviewRequired++,
            default => $this->retryScheduled++,
        };
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'processed' => $this->processed,
            'succeeded' => $this->succeeded,
            'retry_scheduled' => $this->retryScheduled,
            'manual_review_required' => $this->manualReviewRequired,
        ];
    }
}