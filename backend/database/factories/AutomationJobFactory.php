<?php

namespace Database\Factories;

use App\Models\AutomationJob;
use App\Models\InternshipApplication;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AutomationJob>
 */
class AutomationJobFactory extends Factory
{
    protected $model = AutomationJob::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'correlation_id' => (string) Str::uuid(),
            'idempotency_key' => hash('sha256', Str::uuid()->toString()),
            'internship_application_id' => null,
            'automation_type' => AutomationJob::TYPE_MILESTONE_REMINDER,
            'recipient_role' => 'coordinator',
            'recipient_identifier' => 'coord.demo@uls-test.edu.ph',
            'payload' => [],
            'status' => AutomationJob::STATUS_QUEUED,
            'attempts' => 0,
            'max_attempts' => (int) config('automation.max_attempts', 3),
            'failure_class' => null,
            'last_error_code' => null,
            'last_error_summary' => null,
            'next_retry_at' => null,
            'completed_at' => null,
            'coordinator_remarks' => null,
        ];
    }

    public function forApplication(?InternshipApplication $application): static
    {
        return $this->state(fn (array $attributes): array => [
            'internship_application_id' => $application?->getKey(),
        ]);
    }

    public function withStatus(string $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
        ]);
    }
}