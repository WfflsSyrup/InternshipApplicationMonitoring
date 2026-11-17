<?php

namespace Tests\Feature;

use App\Models\InternshipApplication;
use App\Models\SecureIntegrationEnvelope;
use App\Models\User;
use App\Models\UsedIntegrationNonce;
use App\Services\Crypto\EnvelopeCryptoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Week14CryptographicIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-11-17 00:00:00';

    private const CALLBACK_PATH = '/api/v1/integrations/partner-callbacks/outcome';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));

        $logPath = storage_path('framework/testing/logs/crypto_audit.log');

        File::ensureDirectoryExists(dirname($logPath));
        File::delete($logPath);

        config([
            'crypto.active_kid' => 'v2',
            'crypto.clock_skew_seconds' => 300,
            'crypto.nonce_ttl_seconds' => 900,
            'crypto.allow_tamper_simulation' => true,
            'crypto.log_path' => $logPath,
            'crypto.keys' => [
                'v1' => [
                    'enc' => base64_encode(str_repeat('a', 32)),
                    'sig' => base64_encode(str_repeat('b', 32)),
                ],
                'v2' => [
                    'enc' => base64_encode(str_repeat('c', 32)),
                    'sig' => base64_encode(str_repeat('d', 32)),
                ],
            ],
        ]);
    }

    public function test_valid_signed_callback_verifies_and_transitions_the_application(): void
    {
        [$student, $partner, $application] = $this->interviewScheduledApplication();

        [$body, $headers] = $this->signedCallback($application, [
            'outcome' => 'interview_passed',
            'remarks' => 'Panel result: passed.',
        ]);

        $this->postCallback($body, $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.applied_transition.outcome', 'interview_passed')
            ->assertJsonPath('data.applied_transition.to_status', InternshipApplication::STATUS_INTERVIEW_PASSED)
            ->assertJsonPath('data.envelope.verification_status', SecureIntegrationEnvelope::STATUS_VERIFIED)
            ->assertJsonPath('data.envelope.signature_verified', true)
            ->assertJsonPath('data.envelope.replay_checked', true)
            ->assertJsonPath('data.envelope.direction', EnvelopeCryptoService::DIRECTION_INBOUND);

        $this->assertSame(InternshipApplication::STATUS_INTERVIEW_PASSED, $application->fresh()->status);
        $this->assertDatabaseCount('used_integration_nonces', 1);
        $this->assertSame($partner->getKey(), $application->fresh()->partner_reviewer_id);

        $this->assertLogContains('"outcome":"verified"');
        $this->assertLogSanitized();
    }

    public function test_tampered_ciphertext_is_rejected_with_422(): void
    {
        [$student, $partner, $application] = $this->interviewScheduledApplication();

        [$body, $headers] = $this->signedCallback($application, ['outcome' => 'interview_passed']);

        $envelope = json_decode($body, true);
        $raw = base64_decode((string) $envelope['ciphertext'], true);
        $last = strlen($raw) - 1;
        $raw[$last] = chr(ord($raw[$last]) ^ 0x01);
        $envelope['ciphertext'] = base64_encode($raw);

        $body = (string) json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = app(EnvelopeCryptoService::class)->signHeaders('POST', self::CALLBACK_PATH, $body);

        $this->postCallback($body, $headers)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.rejected_tamper.0', fn (string $message): bool => str_contains(
                $message,
                'AES-256-GCM tag',
            ));

        $this->assertSame(InternshipApplication::STATUS_INTERVIEW_SCHEDULED, $application->fresh()->status);

        $record = SecureIntegrationEnvelope::query()->sole();

        $this->assertSame(SecureIntegrationEnvelope::STATUS_REJECTED_TAMPER, $record->verification_status);
        $this->assertTrue($record->signature_verified);
        $this->assertTrue($record->replay_checked);
        $this->assertLogContains('"outcome":"rejected_tamper"');
    }

    public function test_tampered_aad_is_rejected_with_422(): void
    {
        [$student, $partner, $application] = $this->interviewScheduledApplication();

        [$body, $headers] = $this->signedCallback($application, ['outcome' => 'interview_passed']);

        $envelope = json_decode($body, true);
        $envelope['aad'] = $application->reference_code.'|v2|outbound_partner_packet';

        $body = (string) json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = app(EnvelopeCryptoService::class)->signHeaders('POST', self::CALLBACK_PATH, $body);

        $this->postCallback($body, $headers)
            ->assertStatus(422)
            ->assertJsonPath('errors.rejected_tamper.0', fn (string $message): bool => str_contains(
                $message,
                'AES-256-GCM tag',
            ));

        $this->assertSame(SecureIntegrationEnvelope::STATUS_REJECTED_TAMPER, SecureIntegrationEnvelope::query()->value('verification_status'));
        $this->assertSame(InternshipApplication::STATUS_INTERVIEW_SCHEDULED, $application->fresh()->status);
    }

    public function test_forged_signature_is_rejected_with_401(): void
    {
        [$student, $partner, $application] = $this->interviewScheduledApplication();

        [$body, $headers] = $this->signedCallback($application, ['outcome' => 'interview_passed']);

        $headers['X-Signature-256'] = 'sha256='.hash('sha256', 'forged-by-test');

        $this->postCallback($body, $headers)
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.rejected_signature.0', fn (string $message): bool => str_contains(
                $message,
                'HMAC-SHA256',
            ));

        $this->assertSame(SecureIntegrationEnvelope::STATUS_REJECTED_SIGNATURE, SecureIntegrationEnvelope::query()->value('verification_status'));
        $this->assertSame(InternshipApplication::STATUS_INTERVIEW_SCHEDULED, $application->fresh()->status);
        $this->assertLogContains('"outcome":"rejected_signature"');
    }

    public function test_missing_integration_headers_are_rejected_with_401(): void
    {
        [, , $application] = $this->interviewScheduledApplication();

        [$body] = $this->signedCallback($application, ['outcome' => 'interview_passed']);

        $this->postCallback($body, [])
            ->assertStatus(401)
            ->assertJsonPath('errors.rejected_signature.0', fn (string $message): bool => str_contains(
                $message,
                'mandatory',
            ));

        $this->assertSame(SecureIntegrationEnvelope::STATUS_REJECTED_SIGNATURE, SecureIntegrationEnvelope::query()->value('verification_status'));
    }

    public function test_replayed_nonce_is_rejected_with_409(): void
    {
        [$student, $partner, $application] = $this->interviewScheduledApplication();

        [$body, $headers] = $this->signedCallback($application, ['outcome' => 'interview_passed']);

        $this->postCallback($body, $headers)->assertOk();

        $this->postCallback($body, $headers)
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.rejected_replay.0', fn (string $message): bool => str_contains(
                $message,
                'already been used',
            ));

        $this->assertSame(InternshipApplication::STATUS_INTERVIEW_PASSED, $application->fresh()->status);
        $this->assertDatabaseCount('used_integration_nonces', 1);

        $statuses = SecureIntegrationEnvelope::query()
            ->orderBy('id')
            ->pluck('verification_status')
            ->all();

        $this->assertSame([
            SecureIntegrationEnvelope::STATUS_VERIFIED,
            SecureIntegrationEnvelope::STATUS_REJECTED_REPLAY,
        ], $statuses);
    }

    public function test_stale_timestamp_is_rejected_with_401(): void
    {
        [, , $application] = $this->interviewScheduledApplication();

        $stale = now()->subSeconds(400)->getTimestamp();

        [$body, $headers] = $this->signedCallback($application, ['outcome' => 'interview_passed'], null, $stale);

        $this->postCallback($body, $headers)
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.rejected_expired.0', fn (string $message): bool => str_contains(
                $message,
                'clock-skew',
            ));

        $this->assertSame(SecureIntegrationEnvelope::STATUS_REJECTED_EXPIRED, SecureIntegrationEnvelope::query()->value('verification_status'));
        $this->assertSame(0, UsedIntegrationNonce::query()->count());
        $this->assertLogContains('"outcome":"rejected_expired"');
    }

    public function test_unknown_key_id_is_rejected_with_401(): void
    {
        [, , $application] = $this->interviewScheduledApplication();

        [$body, $headers] = $this->signedCallback($application, ['outcome' => 'interview_passed']);

        $headers['X-Key-Id'] = 'v9';

        $this->postCallback($body, $headers)
            ->assertStatus(401)
            ->assertJsonPath('errors.rejected_signature.0', fn (string $message): bool => str_contains(
                $message,
                'not present in the active keyring',
            ));
    }

    public function test_grace_period_key_still_verifies_inbound_callbacks(): void
    {
        [, , $application] = $this->interviewScheduledApplication();

        [$body, $headers] = $this->signedCallback($application, ['outcome' => 'interview_failed'], 'v1');

        $this->assertSame('v1', $headers['X-Key-Id']);

        $this->postCallback($body, $headers)
            ->assertOk()
            ->assertJsonPath('data.envelope.key_id', 'v1')
            ->assertJsonPath('data.envelope.verification_status', SecureIntegrationEnvelope::STATUS_VERIFIED)
            ->assertJsonPath('data.applied_transition.to_status', InternshipApplication::STATUS_INTERVIEW_FAILED);

        $this->assertSame(InternshipApplication::STATUS_INTERVIEW_FAILED, $application->fresh()->status);
    }

    public function test_dispatch_requires_endorsed_or_partner_submitted_application(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $application = InternshipApplication::factory()
            ->for(User::factory()->student()->create(), 'student')
            ->withStatus(InternshipApplication::STATUS_DRAFT)
            ->create();

        Sanctum::actingAs($coordinator);

        $this->postJson('/api/v1/integrations/partner-packets/dispatch', [
            'application_id' => $application->getKey(),
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.application_id.0', fn (string $message): bool => str_contains(
                $message,
                'endorsed or submitted to the partner',
            ));

        $this->assertDatabaseCount('secure_integration_envelopes', 0);
    }

    public function test_dispatch_seals_a_packet_and_never_exposes_ciphertext(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $student = User::factory()->student()->create([
            'name' => 'Ana Dela Cruz',
            'student_or_employee_id' => '2026-00001',
        ]);
        $application = InternshipApplication::factory()
            ->for($student, 'student')
            ->withStatus(InternshipApplication::STATUS_ENDORSED)
            ->create();

        Sanctum::actingAs($coordinator);

        $response = $this->postJson('/api/v1/integrations/partner-packets/dispatch', [
            'application_id' => $application->getKey(),
            'remarks' => 'Cleared for partner review.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.key_id', 'v2')
            ->assertJsonPath('data.direction', EnvelopeCryptoService::DIRECTION_OUTBOUND)
            ->assertJsonPath('data.verification_status', SecureIntegrationEnvelope::STATUS_VERIFIED)
            ->assertJsonPath('data.signature_verified', true)
            ->assertJsonPath('data.application_ref', $application->reference_code);

        $json = $response->json();

        $this->assertArrayNotHasKey('ciphertext_b64', $json['data']);
        $this->assertArrayNotHasKey('iv_hex', $json['data']);
        $this->assertArrayNotHasKey('tag_b64', $json['data']);
        $this->assertArrayNotHasKey('aad', $json['data']);
        $this->assertArrayNotHasKey('payload', $json['data']);
        $this->assertNotNull($json['data']['payload_sha256']);

        $record = SecureIntegrationEnvelope::query()->sole();

        $this->assertStringContainsString('|v2|outbound_partner_packet', (string) $record->aad);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{24}$/', (string) $record->iv_hex);
        $this->assertNotEmpty($record->ciphertext_b64);
        $this->assertStringNotContainsString('Ana Dela Cruz', (string) $record->ciphertext_b64);
        $this->assertLogContains('"direction":"outbound_partner_packet"');
    }

    public function test_envelope_listing_is_role_scoped_and_hides_secrets(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $ownStudent = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $own = InternshipApplication::factory()->for($ownStudent, 'student')
            ->withStatus(InternshipApplication::STATUS_ENDORSED)->create();
        $foreign = InternshipApplication::factory()->for($otherStudent, 'student')
            ->withStatus(InternshipApplication::STATUS_ENDORSED)->create();

        $service = app(EnvelopeCryptoService::class);
        $service->sealOutboundPacket($own, ['probe' => 'own'], $coordinator);
        $service->sealOutboundPacket($foreign, ['probe' => 'foreign'], $coordinator);

        Sanctum::actingAs($ownStudent);

        $this->getJson('/api/v1/integrations/envelopes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.application_ref', $own->reference_code)
            ->assertJsonMissingPath('data.0.ciphertext_b64')
            ->assertJsonMissingPath('data.0.iv_hex')
            ->assertJsonMissingPath('data.0.tag_b64')
            ->assertJsonMissingPath('data.0.aad')
            ->assertJsonMissingPath('data.0.payload');

        Sanctum::actingAs($coordinator);

        $this->getJson('/api/v1/integrations/envelopes')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_keyring_status_exposes_policy_but_no_key_material(): void
    {
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $response = $this->getJson('/api/v1/integrations/keyring/status')
            ->assertOk()
            ->assertJsonPath('data.active_kid', 'v2')
            ->assertJsonPath('data.supported_kids', ['v2', 'v1'])
            ->assertJsonPath('data.cipher_suite', 'AES-256-GCM+HMAC-SHA256')
            ->assertJsonPath('data.signature_algorithm', 'HMAC-SHA256')
            ->assertJsonPath('data.clock_skew_seconds', 300)
            ->assertJsonPath('data.iv_length_bytes', 12)
            ->assertJsonPath('data.tag_length_bytes', 16)
            ->assertJsonPath('data.key_material_exposed', false)
            ->assertJsonPath('data.client_keys_embedded', 0);

        $json = $response->json();

        $this->assertArrayNotHasKey('keys', $json['data']);
        $this->assertArrayNotHasKey('enc', $json['data']);
        $this->assertArrayNotHasKey('sig', $json['data']);
    }

    public function test_non_coordinators_cannot_dispatch_or_inspect_the_keyring(): void
    {
        $application = InternshipApplication::factory()
            ->for(User::factory()->student()->create(), 'student')
            ->withStatus(InternshipApplication::STATUS_ENDORSED)
            ->create();

        Sanctum::actingAs(User::factory()->student()->create());

        $this->postJson('/api/v1/integrations/partner-packets/dispatch', [
            'application_id' => $application->getKey(),
        ])->assertForbidden();

        $this->getJson('/api/v1/integrations/keyring/status')->assertForbidden();

        $this->postJson('/api/v1/integrations/verify-simulation', [
            'mode' => 'valid_callback',
            'reference_code' => $application->reference_code,
        ])->assertForbidden();

        Sanctum::actingAs(User::factory()->adviser()->create());

        $this->postJson('/api/v1/integrations/verify-simulation', [
            'mode' => 'tamper_ciphertext',
            'reference_code' => $application->reference_code,
        ])->assertForbidden();

        $this->assertSame(0, SecureIntegrationEnvelope::query()->count());
    }

    public function test_verification_lab_reports_every_mode(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $application = InternshipApplication::factory()
            ->for(User::factory()->student()->create(), 'student')
            ->withStatus(InternshipApplication::STATUS_ENDORSED)
            ->create();

        Sanctum::actingAs($coordinator);

        $expectations = [
            'valid_callback' => true,
            'tamper_ciphertext' => false,
            'forged_signature' => false,
            'replay_nonce' => false,
            'expired_timestamp' => false,
        ];

        foreach ($expectations as $mode => $accepted) {
            $response = $this->postJson('/api/v1/integrations/verify-simulation', [
                'mode' => $mode,
                'reference_code' => $application->reference_code,
                'outcome' => 'interview_passed',
            ])
                ->assertOk()
                ->assertJsonPath('data.mode', $mode)
                ->assertJsonPath('data.label', EnvelopeCryptoService::SIMULATION_LABELS[$mode])
                ->assertJsonPath('data.accepted', $accepted)
                ->assertJsonPath('data.matched_expectation', true)
                ->assertJsonPath('data.cipher_suite', 'AES-256-GCM+HMAC-SHA256')
                ->assertJsonPath('data.key_id', 'v2')
                ->assertJsonStructure(['data' => ['steps']]);

            $correlationId = (string) $response->json('data.correlation_id');

            $this->assertStringStartsWith('SIM-', $correlationId);
            $this->assertLessThanOrEqual(
                36,
                strlen($correlationId),
                'The simulation correlation id must fit the 36-character correlation_id column.',
            );
        }

        $this->assertSame(InternshipApplication::STATUS_ENDORSED, $application->fresh()->status);
    }

    public function test_verification_lab_rejects_an_unknown_mode(): void
    {
        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->postJson('/api/v1/integrations/verify-simulation', [
            'mode' => 'not_a_mode',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('mode');
    }

    public function test_tamper_simulation_can_be_disabled_by_configuration(): void
    {
        config(['crypto.allow_tamper_simulation' => false]);

        $application = InternshipApplication::factory()
            ->for(User::factory()->student()->create(), 'student')
            ->withStatus(InternshipApplication::STATUS_ENDORSED)
            ->create();

        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->postJson('/api/v1/integrations/verify-simulation', [
            'mode' => 'valid_callback',
            'reference_code' => $application->reference_code,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.mode.0', fn (string $message): bool => str_contains(
                $message,
                'enable the verification lab',
            ));
    }

    public function test_v1_envelopes_are_rotated_onto_the_active_v2_key(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $application = InternshipApplication::factory()
            ->for(User::factory()->student()->create(), 'student')
            ->withStatus(InternshipApplication::STATUS_ENDORSED)
            ->create();

        Sanctum::actingAs($coordinator);

        config(['crypto.active_kid' => 'v1']);

        $this->postJson('/api/v1/integrations/partner-packets/dispatch', [
            'application_id' => $application->getKey(),
        ])->assertCreated()->assertJsonPath('data.key_id', 'v1');

        config(['crypto.active_kid' => 'v2']);

        $this->postJson('/api/v1/integrations/partner-packets/dispatch', [
            'application_id' => $application->getKey(),
        ])->assertCreated()->assertJsonPath('data.key_id', 'v2');

        $retired = SecureIntegrationEnvelope::query()->where('key_id', 'v1')->sole();
        $payloadHash = $retired->payload_sha256;

        $this->artisan('crypto:rotate-envelopes')
            ->expectsOutputToContain('re-sealed')
            ->assertSuccessful();

        $retired->refresh();

        $this->assertSame('v2', $retired->key_id);
        $this->assertSame($payloadHash, $retired->payload_sha256);
        $this->assertStringContainsString('|v2|outbound_partner_packet', (string) $retired->aad);
        $this->assertSame(0, SecureIntegrationEnvelope::query()->where('key_id', '!=', 'v2')->count());

        $this->assertLogContains('"outcome":"rotation_completed"');
    }

    public function test_missing_key_material_is_reported_as_unavailable(): void
    {
        config(['crypto.keys.v2.enc' => null]);

        $application = InternshipApplication::factory()
            ->for(User::factory()->student()->create(), 'student')
            ->withStatus(InternshipApplication::STATUS_ENDORSED)
            ->create();

        Sanctum::actingAs(User::factory()->coordinator()->create());

        $this->postJson('/api/v1/integrations/partner-packets/dispatch', [
            'application_id' => $application->getKey(),
        ])
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.crypto.0', fn (string $message): bool => str_contains(
                $message,
                'not configured',
            ));
    }

    /**
     * @return array{0: User, 1: User, 2: InternshipApplication}
     */
    private function interviewScheduledApplication(): array
    {
        $student = User::factory()->student()->create([
            'email' => 'student1.demo@uls-test.edu.ph',
        ]);

        $partner = User::factory()->partnerReviewer()->create([
            'email' => 'partner.demo@techcorp-test.com',
        ]);

        $application = InternshipApplication::factory()
            ->for($student, 'student')
            ->withStatus(InternshipApplication::STATUS_INTERVIEW_SCHEDULED)
            ->create([
                'partner_reviewer_id' => $partner->getKey(),
                'interview_schedule' => now()->addDays(3),
                'adviser_id' => User::factory()->adviser()->create()->getKey(),
            ]);

        return [$student, $partner, $application];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: string, 1: array<string, string>}
     */
    private function signedCallback(
        InternshipApplication $application,
        array $overrides = [],
        ?string $keyId = null,
        ?int $timestamp = null,
    ): array {
        $payload = array_merge([
            'reference_code' => $application->reference_code,
            'outcome' => 'interview_passed',
            'remarks' => 'Panel interview outcome.',
        ], $overrides);

        /** @var EnvelopeCryptoService $crypto */
        $crypto = app(EnvelopeCryptoService::class);

        return $crypto->sealInboundCallback($payload, $keyId, $timestamp);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function postCallback(string $body, array $headers): \Illuminate\Testing\TestResponse
    {
        $server = $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ] + $headers);

        return $this->call('POST', self::CALLBACK_PATH, [], [], [], $server, $body);
    }

    private function assertLogContains(string $needle): void
    {
        $path = (string) config('crypto.log_path');

        $this->assertFileExists($path);

        $this->assertStringContainsString($needle, (string) file_get_contents($path));
    }

    private function assertLogSanitized(): void
    {
        $contents = (string) file_get_contents((string) config('crypto.log_path'));

        $this->assertStringNotContainsString('sha256=', $contents);
        $this->assertStringNotContainsString('ciphertext', $contents);
        $this->assertStringNotContainsString('Bearer ', $contents);

        foreach (['a', 'b', 'c', 'd'] as $letter) {
            $this->assertStringNotContainsString(str_repeat($letter, 32), $contents);
        }
    }
}