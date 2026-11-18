<?php

namespace Tests\Feature;

use App\Models\InternshipApplication;
use App\Models\SecurityAuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Week 15 — Module 15 / SA9: API security hardening & OWASP verification.
 *
 * TC-15-01  benign request carries every hardened response header
 * TC-15-02  BOLA cross-student access is blocked and audited
 * TC-15-03  BOPLA mass assignment is rejected and audited
 * TC-15-04  stored XSS is stripped and SQLi payloads stay parameterized
 * TC-15-05  login brute force is throttled and audited
 * TC-15-06  oversized payloads are rejected and audited
 * TC-15-07  untrusted origins are excluded and revoke-all kills tokens
 */
class Week15SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const SECURITY_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), camera=(), microphone=()',
        'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'; base-uri 'none'",
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
    ];

    public function test_tc_15_01_benign_request_carries_every_security_header(): void
    {
        $response = $this->getJson('/api/v1/health')->assertOk();

        foreach (self::SECURITY_HEADERS as $name => $value) {
            $response->assertHeader($name, $value);
        }
    }

    public function test_tc_15_02_cross_student_access_is_blocked_and_audited_as_bola(): void
    {
        $studentOne = User::factory()->student()->create();
        $studentTwo = User::factory()->student()->create();

        $application = InternshipApplication::factory()
            ->for($studentTwo, 'student')
            ->create();

        Sanctum::actingAs($studentOne);

        $this->getJson('/api/v1/applications/'.$application->getKey())
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', null);

        $event = SecurityAuditEvent::query()
            ->where('event_type', SecurityAuditEvent::EVENT_BOLA_BLOCKED)
            ->firstOrFail();

        $this->assertSame('/api/v1/applications/'.$application->getKey(), $event->endpoint);
        $this->assertSame($studentOne->getKey(), $event->actor_id);
        $this->assertSame(User::ROLE_STUDENT, $event->actor_role);
        $this->assertSame(403, $event->http_status);
        $this->assertSame(SecurityAuditEvent::SEVERITY_HIGH, $event->severity);
        $this->assertSame(64, strlen($event->ip_hash));
        $this->assertNotSame('127.0.0.1', $event->ip_hash);
    }

    public function test_tc_15_03_privileged_attributes_are_rejected_and_audited(): void
    {
        $student = User::factory()->student()->create();

        Sanctum::actingAs($student);

        $this->postJson('/api/v1/applications', $this->applicationPayload([
            'status' => InternshipApplication::STATUS_PLACEMENT_CONFIRMED,
            'student_id' => 999,
            'adviser_id' => 999,
            'role' => User::ROLE_COORDINATOR,
            'placement_confirmed_at' => now()->toIso8601String(),
        ]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'errors' => ['status', 'student_id', 'adviser_id', 'role', 'placement_confirmed_at'],
            ]);

        $this->assertDatabaseCount('internship_applications', 0);

        $event = SecurityAuditEvent::query()
            ->where('event_type', SecurityAuditEvent::EVENT_MASS_ASSIGNMENT_BLOCKED)
            ->firstOrFail();

        $this->assertSame(422, $event->http_status);
        $this->assertSame($student->getKey(), $event->actor_id);
        $this->assertContains('status', (array) $event->details['attributes']);
        $this->assertContains('role', (array) $event->details['attributes']);
    }

    public function test_tc_15_04_stored_xss_is_stripped_and_sql_injection_stays_inert(): void
    {
        $student = User::factory()->student()->create();

        Sanctum::actingAs($student);

        $this->postJson('/api/v1/applications', $this->applicationPayload([
            'host_company_name' => '<script>alert(1)</script>Acme Corp',
            'target_role' => "Junior\0 Developer <b>Intern</b>",
        ]))->assertCreated();

        $application = InternshipApplication::query()->firstOrFail();

        $this->assertSame('Acme Corp', $application->host_company_name);
        $this->assertSame('Junior Developer Intern', $application->target_role);
        $this->assertStringNotContainsString('<script>', $application->host_company_name);
        $this->assertStringNotContainsString("\0", $application->target_role);

        $this->postJson('/api/v1/applications', $this->applicationPayload([
            'target_role' => "Robert'); DROP TABLE internship_applications;--",
        ]))->assertCreated();

        $this->assertDatabaseCount('internship_applications', 2);

        $stored = InternshipApplication::query()
            ->where('target_role', "Robert'); DROP TABLE internship_applications;--")
            ->exists();

        $this->assertTrue($stored, 'SQL metacharacters must be stored literally, not executed.');
    }

    public function test_tc_15_05_login_brute_force_is_throttled_and_audited(): void
    {
        User::factory()->student()->create([
            'email' => 'student1.demo@uls-test.edu.ph',
            'password' => 'Password123!',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'student1.demo@uls-test.edu.ph',
                'password' => 'WrongPassword123',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'student1.demo@uls-test.edu.ph',
            'password' => 'WrongPassword123',
        ])
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Rate limit exceeded. Please retry after the cooldown period.')
            ->assertJsonPath('data', null)
            ->assertHeader('Retry-After');

        $this->assertDatabaseHas('security_audit_events', [
            'event_type' => SecurityAuditEvent::EVENT_RATE_LIMIT_EXCEEDED,
            'http_status' => 429,
        ]);
    }

    public function test_tc_15_06_oversized_payload_is_rejected_and_audited(): void
    {
        $student = User::factory()->student()->create();

        Sanctum::actingAs($student);

        $this->postJson('/api/v1/applications', $this->applicationPayload([
            'host_company_name' => str_repeat('A', 70 * 1024),
        ]))
            ->assertStatus(413)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Request payload exceeds the 64 KB limit configured for this API.');

        $this->assertDatabaseCount('internship_applications', 0);

        $event = SecurityAuditEvent::query()
            ->where('event_type', SecurityAuditEvent::EVENT_PAYLOAD_TOO_LARGE)
            ->firstOrFail();

        $this->assertSame(413, $event->http_status);
        $this->assertSame('/api/v1/applications', $event->endpoint);
        $this->assertSame(SecurityAuditEvent::SEVERITY_WARNING, $event->severity);
    }

    public function test_tc_15_07_untrusted_origin_is_excluded_and_revoke_all_kills_tokens(): void
    {
        $this->getJson('/api/v1/health', ['Origin' => 'https://evil.example'])
            ->assertOk()
            ->assertHeaderMissing('Access-Control-Allow-Origin');

        $this->getJson('/api/v1/health', ['Origin' => 'http://localhost:5000'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5000');

        $coordinator = User::factory()->coordinator()->create();

        $first = $coordinator->createToken('web-a')->plainTextToken;
        $second = $coordinator->createToken('web-b')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$first)
            ->postJson('/api/v1/auth/revoke-all')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.revoked_tokens', 2);

        // The test container keeps the resolved guard between requests; a real
        // PHP-FPM request boots a fresh one.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$second)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);

        $this->assertDatabaseHas('security_audit_events', [
            'event_type' => SecurityAuditEvent::EVENT_SESSION_REVOKED,
            'actor_id' => $coordinator->getKey(),
        ]);
    }

    public function test_security_posture_and_audit_endpoints_are_coordinator_only(): void
    {
        $coordinator = User::factory()->coordinator()->create();

        SecurityAuditEvent::query()->create([
            'correlation_id' => (string) \Illuminate\Support\Str::uuid(),
            'event_type' => SecurityAuditEvent::EVENT_BFLA_BLOCKED,
            'severity' => SecurityAuditEvent::SEVERITY_HIGH,
            'actor_id' => $coordinator->getKey(),
            'actor_role' => User::ROLE_STUDENT,
            'ip_hash' => hash('sha256', 'controlled-fixture'),
            'endpoint' => '/api/v1/automation/jobs/1/requeue',
            'http_status' => 403,
            'details' => ['scope' => 'function'],
            'created_at' => now(),
        ]);

        Sanctum::actingAs($coordinator);

        config(['app.debug' => false]);

        $this->getJson('/api/v1/security/posture')
            ->assertOk()
            ->assertJsonPath('data.strict_cors', true)
            ->assertJsonPath('data.security_headers_enabled', true)
            ->assertJsonPath('data.max_payload_kb', 64)
            ->assertJsonPath('data.sanctum_ttl_minutes', 60)
            ->assertJsonPath('data.login_rate_limit_per_min', 5)
            ->assertJsonPath('data.api_rate_limit_per_min', 60)
            ->assertJsonPath('data.debug_mode_disabled', true);

        $this->getJson('/api/v1/security/audit-events?event_type=bfla_blocked')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event_label', 'BFLA Blocked')
            ->assertJsonPath('data.0.severity', 'high');

        $capped = $this->getJson('/api/v1/security/audit-events?per_page=500')->assertOk();

        $this->assertLessThanOrEqual(50, count($capped->json('data') ?? []));

        $student = User::factory()->student()->create();

        Sanctum::actingAs($student);

        $this->getJson('/api/v1/security/audit-events')->assertForbidden();
        $this->getJson('/api/v1/security/posture')->assertForbidden();

        $this->assertDatabaseHas('security_audit_events', [
            'event_type' => SecurityAuditEvent::EVENT_BFLA_BLOCKED,
            'http_status' => 403,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function applicationPayload(array $overrides = []): array
    {
        return array_merge([
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
        ], $overrides);
    }
}