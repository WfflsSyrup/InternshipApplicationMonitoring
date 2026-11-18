<?php

namespace Tests\Feature;

use App\Models\ApplicationStatusLog;
use App\Models\InternshipApplication;
use App\Models\SecurityAuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InternshipApplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_standard_envelope(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('errors', null);
    }

    public function test_login_returns_token_and_role(): void
    {
        $user = User::factory()->coordinator()->create([
            'email' => 'coord.demo@uls-test.edu.ph',
            'password' => 'Password123!',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'coord.demo@uls-test.edu.ph',
            'password' => 'Password123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role', User::ROLE_COORDINATOR);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertSame($user->getKey(), $response->json('data.user.id'));
    }

    public function test_login_with_invalid_credentials_returns_uniform_401(): void
    {
        User::factory()->student()->create([
            'email' => 'student1.demo@uls-test.edu.ph',
            'password' => 'Password123!',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'student1.demo@uls-test.edu.ph',
            'password' => 'WrongPassword123',
        ])->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid credentials provided.')
            ->assertJsonPath('data', null)
            ->assertJsonPath('errors', null);

        $this->assertDatabaseHas('security_audit_events', [
            'event_type' => SecurityAuditEvent::EVENT_LOGIN_FAILED,
            'http_status' => 401,
        ]);
    }

    public function test_unauthenticated_request_returns_401_envelope(): void
    {
        $this->getJson('/api/v1/applications')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonPath('data', null);
    }

    public function test_application_listing_is_scoped_per_role(): void
    {
        $studentOne = User::factory()->student()->create();
        $studentTwo = User::factory()->student()->create();

        InternshipApplication::factory()->for($studentOne, 'student')->create();
        InternshipApplication::factory()->for($studentOne, 'student')->create();
        InternshipApplication::factory()->for($studentTwo, 'student')->create();

        Sanctum::actingAs($studentOne);

        $this->getJson('/api/v1/applications')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_non_student_cannot_create_application(): void
    {
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->postJson('/api/v1/applications', $this->applicationPayload())
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_student_can_create_application_with_initial_audit_log(): void
    {
        $student = User::factory()->student()->create();

        Sanctum::actingAs($student);

        $response = $this->postJson('/api/v1/applications', $this->applicationPayload());

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', InternshipApplication::STATUS_DRAFT)
            ->assertJsonPath('data.actions.0.status', InternshipApplication::STATUS_SUBMITTED);

        $application = InternshipApplication::query()->firstOrFail();

        $this->assertMatchesRegularExpression('/^INT-\d{4}-\d{4}$/', $application->reference_code);
        $this->assertSame(1, ApplicationStatusLog::query()->where('internship_application_id', $application->getKey())->count());
    }

    public function test_create_application_validation_errors_are_returned_per_field(): void
    {
        Sanctum::actingAs(User::factory()->student()->create());

        $this->postJson('/api/v1/applications', [
            'host_company_name' => '',
            'internship_hours_required' => 0,
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'host_company_name',
                'target_role',
                'internship_hours_required',
                'target_start_date',
                'requirements_checklist',
            ]);
    }

    public function test_invalid_state_jump_is_rejected_with_422(): void
    {
        $student = User::factory()->student()->create();
        $application = InternshipApplication::factory()
            ->for($student, 'student')
            ->withStatus(InternshipApplication::STATUS_SUBMITTED)
            ->create();

        Sanctum::actingAs($student);

        $this->postJson($this->transitionUrl($application), [
            'target_status' => InternshipApplication::STATUS_ENDORSED,
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['status' => []]]);
    }

    public function test_student_cannot_edit_application_that_is_not_theirs(): void
    {
        $owner = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $application = InternshipApplication::factory()
            ->for($owner, 'student')
            ->withStatus(InternshipApplication::STATUS_DRAFT)
            ->create();

        Sanctum::actingAs($otherStudent);

        $this->putJson($this->url($application), $this->applicationPayload())
            ->assertStatus(403);
    }

    public function test_student_cannot_view_application_of_another_department(): void
    {
        $adviser = User::factory()->adviser()->create(['department_or_company' => 'Other Department']);
        $application = InternshipApplication::factory()->create();

        Sanctum::actingAs($adviser);

        $this->getJson($this->url($application))->assertStatus(403);
    }

    public function test_complete_normal_path_through_placement_confirmation(): void
    {
        $student = User::factory()->student()->create();
        $adviser = User::factory()->adviser()->create();
        $coordinator = User::factory()->coordinator()->create();
        $partnerReviewer = User::factory()->partnerReviewer()->create();

        $application = InternshipApplication::factory()
            ->for($student, 'student')
            ->withStatus(InternshipApplication::STATUS_DRAFT)
            ->create();

        $steps = [
            [User::ROLE_STUDENT, InternshipApplication::STATUS_SUBMITTED],
            [User::ROLE_ADVISER, InternshipApplication::STATUS_REQUIREMENTS_VERIFIED],
            [User::ROLE_ADVISER, InternshipApplication::STATUS_ENDORSED],
        ];

        $actors = [
            User::ROLE_STUDENT => $student,
            User::ROLE_ADVISER => $adviser,
            User::ROLE_COORDINATOR => $coordinator,
            User::ROLE_PARTNER_REVIEWER => $partnerReviewer,
        ];

        foreach ($steps as [$role, $target]) {
            Sanctum::actingAs($actors[$role]);

            $this->postJson($this->transitionUrl($application), [
                'target_status' => $target,
                'remarks' => 'Transition executed by '.$role,
            ])->assertOk()->assertJsonPath('data.status', $target);
        }

        Sanctum::actingAs($coordinator);

        $this->postJson($this->transitionUrl($application), [
            'target_status' => InternshipApplication::STATUS_PARTNER_SUBMITTED,
            'remarks' => 'Forwarded to partner reviewer.',
            'partner_reviewer_id' => $partnerReviewer->getKey(),
        ])->assertOk()->assertJsonPath('data.status', InternshipApplication::STATUS_PARTNER_SUBMITTED);

        Sanctum::actingAs($partnerReviewer);

        $this->postJson($this->transitionUrl($application), [
            'target_status' => InternshipApplication::STATUS_INTERVIEW_SCHEDULED,
            'interview_schedule' => now()->addWeek()->toDateTimeString(),
        ])->assertOk()->assertJsonPath('data.status', InternshipApplication::STATUS_INTERVIEW_SCHEDULED);

        $this->postJson($this->transitionUrl($application), [
            'target_status' => InternshipApplication::STATUS_INTERVIEW_PASSED,
            'remarks' => 'Passed the panel interview.',
        ])->assertOk()->assertJsonPath('data.status', InternshipApplication::STATUS_INTERVIEW_PASSED);

        Sanctum::actingAs($coordinator);

        $this->postJson($this->transitionUrl($application), [
            'target_status' => InternshipApplication::STATUS_PLACEMENT_CONFIRMED,
            'remarks' => 'Placement confirmed.',
        ])->assertOk()->assertJsonPath('data.status', InternshipApplication::STATUS_PLACEMENT_CONFIRMED);

        Sanctum::actingAs($student);

        $this->postJson($this->transitionUrl($application), [
            'target_status' => InternshipApplication::STATUS_PLACEMENT_CONFIRMED,
            'remarks' => 'Acknowledged by the student.',
        ])->assertOk()
            ->assertJsonPath('data.status', InternshipApplication::STATUS_PLACEMENT_CONFIRMED);

        $application->refresh();

        $this->assertNotNull($application->placement_confirmed_at);
        $this->assertNotNull($application->placement_acknowledged_at);

        $this->assertSame(
            8,
            ApplicationStatusLog::query()->where('internship_application_id', $application->getKey())->count(),
        );
    }

    public function test_partner_reviewer_cannot_transition_application_that_is_not_assigned(): void
    {
        $partnerReviewer = User::factory()->partnerReviewer()->create();
        $application = InternshipApplication::factory()
            ->withStatus(InternshipApplication::STATUS_PARTNER_SUBMITTED)
            ->create();

        Sanctum::actingAs($partnerReviewer);

        $this->postJson($this->transitionUrl($application), [
            'target_status' => InternshipApplication::STATUS_INTERVIEW_SCHEDULED,
            'interview_schedule' => now()->addWeek()->toDateTimeString(),
        ])->assertStatus(403);
    }

    public function test_revision_request_requires_remarks(): void
    {
        $adviser = User::factory()->adviser()->create();
        $application = InternshipApplication::factory()
            ->withStatus(InternshipApplication::STATUS_SUBMITTED)
            ->create();

        Sanctum::actingAs($adviser);

        $this->postJson($this->transitionUrl($application), [
            'target_status' => InternshipApplication::STATUS_REVISION_REQUESTED,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['remarks']);
    }

    public function test_partner_submission_requires_a_partner_reviewer(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $application = InternshipApplication::factory()
            ->withStatus(InternshipApplication::STATUS_ENDORSED)
            ->create();

        Sanctum::actingAs($coordinator);

        $this->postJson($this->transitionUrl($application), [
            'target_status' => InternshipApplication::STATUS_PARTNER_SUBMITTED,
            'remarks' => 'Forwarding to a partner.',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['partner_reviewer_id']);
    }

    public function test_only_coordinators_can_list_partner_reviewers(): void
    {
        User::factory()->partnerReviewer()->create();

        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->getJson('/api/v1/partner-reviewers')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        Sanctum::actingAs(User::factory()->partnerReviewer()->create());

        $this->getJson('/api/v1/partner-reviewers')->assertStatus(403);
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationPayload(): array
    {
        return [
            'host_company_name' => 'Controlled Test Company',
            'target_role' => 'Junior Developer Intern',
            'internship_hours_required' => 486,
            'target_start_date' => now()->addMonths(2)->toDateString(),
            'requirements_checklist' => [
                'resume_ready' => true,
                'endorsement_letter' => false,
                'parent_consent' => true,
                'medical_clearance' => true,
            ],
        ];
    }

    private function transitionUrl(InternshipApplication $application): string
    {
        return $this->url($application).'/transition';
    }

    private function url(InternshipApplication $application): string
    {
        return '/api/v1/applications/'.$application->getKey();
    }
}