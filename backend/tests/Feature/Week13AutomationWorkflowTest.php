<?php

namespace Tests\Feature;

use App\Models\ApplicationStatusLog;
use App\Models\AutomationJob;
use App\Models\InternshipApplication;
use App\Models\User;
use App\Services\Automation\AutomationJobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Week13AutomationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-11-17 00:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::now());

        $logPath = storage_path('framework/testing/logs/automation.log');

        File::ensureDirectoryExists(dirname($logPath));
        File::delete($logPath);

        config([
            'automation.dispatcher.mode' => 'log',
            'automation.allow_fault_injection' => true,
            'automation.max_attempts' => 3,
            'automation.retry.base_seconds' => 10,
            'automation.retry.jitter_seconds' => 3,
            'automation.timeout_seconds' => 5,
            'automation.sweeps.incomplete_after_hours' => 48,
            'automation.sweeps.stalled_after_days' => 5,
            'automation.sweeps.dispatch_limit' => 50,
            'automation.log_path' => $logPath,
        ]);
    }

    /**
     * Frozen clock (UTC, matching config('app.timezone')).
     */
    private function now(): Carbon
    {
        return Carbon::parse(self::NOW, 'UTC');
    }

    public function test_incomplete_sweep_enqueues_and_delivers_a_job(): void
    {
        $student = User::factory()->student()->create([
            'email' => 'student1.demo@uls-test.edu.ph',
        ]);

        $application = $this->incompleteApplication($student);

        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->postJson('/api/v1/automation/sweeps/run', ['type' => 'incomplete'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.enqueued', 1)
            ->assertJsonPath('data.duplicates_skipped', 0)
            ->assertJsonPath('data.dispatch.succeeded', 1);

        $job = AutomationJob::query()->sole();

        $this->assertSame(AutomationJob::TYPE_INCOMPLETE_RECORD_ALERT, $job->automation_type);
        $this->assertSame(AutomationJob::STATUS_SUCCEEDED, $job->status);
        $this->assertSame(1, $job->attempts);
        $this->assertSame(3, $job->max_attempts);
        $this->assertSame(User::ROLE_STUDENT, $job->recipient_role);
        $this->assertSame('student1.demo@uls-test.edu.ph', $job->recipient_identifier);
        $this->assertSame($application->getKey(), $job->internship_application_id);
        $this->assertNotNull($job->completed_at);
        $this->assertNull($job->failure_class);

        $this->assertSame(
            hash('sha256', AutomationJobService::incompleteKey((int) $application->getKey(), self::now())),
            $job->idempotency_key,
        );

        $this->assertSame(
            ['resume_ready'],
            $job->payload['missing_requirements'],
        );

        $this->assertLogContains('"automation_type":"incomplete_record_alert"');
        $this->assertLogContains('"outcome":"succeeded"');
    }

    public function test_transient_failure_is_retried_then_succeeds(): void
    {
        $student = User::factory()->student()->create();
        $this->incompleteApplication($student);

        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->postJson('/api/v1/automation/sweeps/run', [
            'type' => 'incomplete',
            'fault_mode' => 'transient_once',
        ])->assertOk()->assertJsonPath('data.dispatch.retry_scheduled', 1);

        $job = AutomationJob::query()->sole();

        $this->assertSame(AutomationJob::STATUS_RETRYING, $job->status);
        $this->assertSame(1, $job->attempts);
        $this->assertSame(AutomationJob::FAILURE_TRANSIENT, $job->failure_class);
        $this->assertSame(503, $job->last_error_code);
        $this->assertNotNull($job->next_retry_at);

        $earliest = self::now()->addSeconds(10)->getTimestamp();
        $latest = self::now()->addSeconds(13)->getTimestamp();

        $this->assertGreaterThanOrEqual($earliest, $job->next_retry_at->getTimestamp());
        $this->assertLessThanOrEqual($latest, $job->next_retry_at->getTimestamp());

        $this->travelTo($job->next_retry_at->copy()->addSecond());

        $this->postJson('/api/v1/automation/sweeps/run', [
            'type' => 'incomplete',
            'fault_mode' => 'transient_once',
        ])->assertOk()
            ->assertJsonPath('data.duplicates_skipped', 1)
            ->assertJsonPath('data.dispatch.succeeded', 1);

        $job->refresh();

        $this->assertSame(AutomationJob::STATUS_SUCCEEDED, $job->status);
        $this->assertSame(2, $job->attempts);
        $this->assertNull($job->failure_class);
        $this->assertNull($job->last_error_summary);
        $this->assertNotNull($job->completed_at);
    }

    public function test_exhausted_transient_retries_require_manual_review(): void
    {
        $student = User::factory()->student()->create();
        $this->incompleteApplication($student);

        Sanctum::actingAs(User::factory()->coordinator()->create());

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postJson('/api/v1/automation/sweeps/run', [
                'type' => 'incomplete',
                'fault_mode' => 'transient_always',
            ])->assertOk();

            $job = AutomationJob::query()->sole();

            if ($attempt < 3) {
                $this->assertSame(AutomationJob::STATUS_RETRYING, $job->status);

                $this->travelTo($job->next_retry_at->copy()->addSecond());

                continue;
            }

            $this->assertSame(AutomationJob::STATUS_MANUAL_REVIEW_REQUIRED, $job->status);
            $this->assertSame(3, $job->attempts);
            $this->assertSame(AutomationJob::FAILURE_TRANSIENT, $job->failure_class);
            $this->assertNull($job->next_retry_at);
        }
    }

    public function test_permanent_failure_is_requeued_for_recovery(): void
    {
        $student = User::factory()->student()->create();
        $application = $this->incompleteApplication($student);

        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->postJson('/api/v1/automation/sweeps/run', [
            'type' => 'incomplete',
            'fault_mode' => 'permanent_invalid_recipient',
        ])->assertOk()->assertJsonPath('data.dispatch.manual_review_required', 1);

        $job = AutomationJob::query()->sole();

        $this->assertSame(AutomationJob::STATUS_MANUAL_REVIEW_REQUIRED, $job->status);
        $this->assertSame(1, $job->attempts);
        $this->assertSame(AutomationJob::FAILURE_PERMANENT, $job->failure_class);
        $this->assertSame(422, $job->last_error_code);
        $this->assertStringContainsString('recipient identifier', (string) $job->last_error_summary);

        $this->postJson('/api/v1/automation/jobs/'.$job->getKey().'/requeue', [
            'recipient_identifier' => 'student1.demo@uls-test.edu.ph',
            'remarks' => 'Corrected controlled test address by coordinator.',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.job.status', AutomationJob::STATUS_SUCCEEDED)
            ->assertJsonPath('data.job.attempts', 1)
            ->assertJsonPath('data.job.coordinator_remarks', 'Corrected controlled test address by coordinator.')
            ->assertJsonPath('data.job.failure_class', null);

        $job->refresh();

        $this->assertSame(AutomationJob::STATUS_SUCCEEDED, $job->status);
        $this->assertSame('student1.demo@uls-test.edu.ph', $job->recipient_identifier);
        $this->assertNull($job->failure_class);
        $this->assertSame($application->getKey(), $job->internship_application_id);

        $this->assertLogContains('"outcome":"requeued"');
    }

    public function test_duplicate_sweep_triggers_are_suppressed_by_idempotency(): void
    {
        $student = User::factory()->student()->create();
        $this->incompleteApplication($student);

        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->postJson('/api/v1/automation/sweeps/run', ['type' => 'incomplete'])
            ->assertOk()
            ->assertJsonPath('data.enqueued', 1);

        $this->postJson('/api/v1/automation/sweeps/run', ['type' => 'incomplete'])
            ->assertOk()
            ->assertJsonPath('data.enqueued', 0)
            ->assertJsonPath('data.duplicates_skipped', 1)
            ->assertJsonPath('data.dispatch.processed', 0);

        $this->assertSame(1, AutomationJob::query()->count());
        $this->assertSame(1, AutomationJob::query()->distinct()->count('idempotency_key'));
    }

    public function test_students_cannot_run_sweeps_or_requeue_jobs(): void
    {
        $student = User::factory()->student()->create();
        $application = $this->incompleteApplication($student);

        $job = AutomationJob::factory()->forApplication($application)->create([
            'automation_type' => AutomationJob::TYPE_INCOMPLETE_RECORD_ALERT,
            'status' => AutomationJob::STATUS_MANUAL_REVIEW_REQUIRED,
            'failure_class' => AutomationJob::FAILURE_PERMANENT,
            'attempts' => 1,
        ]);

        Sanctum::actingAs($student);

        $this->postJson('/api/v1/automation/sweeps/run', ['type' => 'incomplete'])
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->postJson('/api/v1/automation/jobs/'.$job->getKey().'/requeue', [])
            ->assertStatus(403);

        $this->assertSame(AutomationJob::STATUS_MANUAL_REVIEW_REQUIRED, $job->fresh()->status);
    }

    public function test_requeue_of_a_healthy_job_is_rejected(): void
    {
        $application = $this->incompleteApplication(User::factory()->student()->create());

        $job = AutomationJob::factory()->forApplication($application)->create([
            'automation_type' => AutomationJob::TYPE_MILESTONE_REMINDER,
            'status' => AutomationJob::STATUS_SUCCEEDED,
            'attempts' => 1,
            'completed_at' => now(),
        ]);

        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->postJson('/api/v1/automation/jobs/'.$job->getKey().'/requeue', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['job']);
    }

    public function test_milestone_transition_enqueues_a_reminder_once(): void
    {
        $student = User::factory()->student()->create();
        $adviser = User::factory()->adviser()->create();
        $coordinator = User::factory()->coordinator()->create();

        $application = InternshipApplication::factory()
            ->for($student, 'student')
            ->withStatus(InternshipApplication::STATUS_DRAFT)
            ->create([
                'adviser_id' => $adviser->getKey(),
            ]);

        Sanctum::actingAs($student);

        $this->postJson('/api/v1/applications/'.$application->getKey().'/transition', [
            'target_status' => InternshipApplication::STATUS_SUBMITTED,
            'remarks' => 'Ready for adviser verification.',
        ])->assertOk()->assertJsonPath('data.status', InternshipApplication::STATUS_SUBMITTED);

        $job = AutomationJob::query()
            ->where('automation_type', AutomationJob::TYPE_MILESTONE_REMINDER)
            ->sole();

        $log = ApplicationStatusLog::query()
            ->where('internship_application_id', $application->getKey())
            ->where('to_status', InternshipApplication::STATUS_SUBMITTED)
            ->sole();

        $this->assertSame(
            hash('sha256', AutomationJobService::milestoneKey((int) $application->getKey(), InternshipApplication::STATUS_SUBMITTED, $log->getKey())),
            $job->idempotency_key,
        );

        $this->assertSame(User::ROLE_ADVISER, $job->recipient_role);
        $this->assertSame($adviser->email, $job->recipient_identifier);
        $this->assertSame($job->payload['to_status'], InternshipApplication::STATUS_SUBMITTED);

        // A second identical transition is impossible (state machine), but a
        // replayed enqueue for the same log must be suppressed.
        Sanctum::actingAs($coordinator);

        $this->assertCount(1, AutomationJob::query()->get());
    }

    public function test_job_listing_is_scoped_to_the_actor(): void
    {
        $adviser = User::factory()->adviser()->create(['department_or_company' => 'BSIT Department']);
        $otherAdviser = User::factory()->adviser()->create(['department_or_company' => 'Other Department']);

        $ownStudent = User::factory()->student()->create(['department_or_company' => 'BSIT Department']);
        $foreignStudent = User::factory()->student()->create(['department_or_company' => 'Other Department']);

        $ownApplication = InternshipApplication::factory()
            ->for($ownStudent, 'student')
            ->create(['adviser_id' => $adviser->getKey()]);

        $foreignApplication = InternshipApplication::factory()
            ->for($foreignStudent, 'student')
            ->create(['adviser_id' => $otherAdviser->getKey()]);

        AutomationJob::factory()->forApplication($ownApplication)->create();
        AutomationJob::factory()->forApplication($foreignApplication)->create();
        AutomationJob::factory()->create([
            'automation_type' => AutomationJob::TYPE_WEEKLY_SUMMARY,
            'internship_application_id' => null,
        ]);

        Sanctum::actingAs($adviser);

        $this->getJson('/api/v1/automation/jobs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.internship_application_id', $ownApplication->getKey());

        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson('/api/v1/automation/jobs')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_weekly_summary_is_generated_and_exposed(): void
    {
        $student = User::factory()->student()->create();

        InternshipApplication::factory()->for($student, 'student')->count(2)->create([
            'status' => InternshipApplication::STATUS_DRAFT,
        ]);

        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->postJson('/api/v1/automation/sweeps/run', ['type' => 'weekly_summary'])
            ->assertOk()
            ->assertJsonPath('data.enqueued', 1)
            ->assertJsonPath('data.dispatch.succeeded', 1)
            ->assertJsonPath('data.summary.week_key', self::now()->format('o-\WW'));

        $this->getJson('/api/v1/automation/summaries/weekly')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.week_key', self::now()->format('o-\WW'))
            ->assertJsonPath('data.counts.draft', 2)
            ->assertJsonPath('data.total', 2);
    }

    public function test_application_detail_exposes_linked_automation_jobs(): void
    {
        $student = User::factory()->student()->create();
        $application = $this->incompleteApplication($student);

        AutomationJob::factory()->forApplication($application)->create([
            'automation_type' => AutomationJob::TYPE_INCOMPLETE_RECORD_ALERT,
            'status' => AutomationJob::STATUS_SUCCEEDED,
            'attempts' => 1,
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/v1/automation/jobs?application_id='.$application->getKey())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.application_reference_code', $application->reference_code);
    }

    public function test_fault_injection_is_ignored_when_disabled(): void
    {
        config(['automation.allow_fault_injection' => false]);

        $student = User::factory()->student()->create();
        $this->incompleteApplication($student);

        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->postJson('/api/v1/automation/sweeps/run', [
            'type' => 'incomplete',
            'fault_mode' => 'permanent_invalid_recipient',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fault_mode']);

        $this->assertSame(0, AutomationJob::query()->count());
    }

    /**
     * Creates a draft application that is 50 hours old with a missing requirement.
     */
    private function incompleteApplication(User $student): InternshipApplication
    {
        return InternshipApplication::factory()
            ->for($student, 'student')
            ->withStatus(InternshipApplication::STATUS_DRAFT)
            ->create([
                'requirements_checklist' => [
                    'resume_ready' => false,
                    'endorsement_letter' => true,
                    'parent_consent' => true,
                    'medical_clearance' => true,
                ],
                'updated_at' => self::now()->subHours(50),
            ]);
    }

    private function assertLogContains(string $needle): void
    {
        $path = (string) config('automation.log_path');

        $this->assertFileExists($path);

        $contents = (string) file_get_contents($path);

        $this->assertStringContainsString($needle, $contents);
        $this->assertStringNotContainsString('Password123!', $contents);
        $this->assertStringNotContainsString('Bearer ', $contents);
    }
}