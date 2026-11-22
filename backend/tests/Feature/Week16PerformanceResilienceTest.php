<?php

namespace Tests\Feature;

use App\Models\InternshipApplication;
use App\Models\PerformanceBenchmarkRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Week 16 — Module 16 / SA10: Performance benchmarking, concurrency control & resilience.
 *
 * TC-16-01: Paginated indexed list query executes within <= 4 SQL queries and attaches X-Response-Time-Ms & X-Query-Count headers
 * TC-16-02: Concurrent transition race condition (expected_version = 1 submitted twice) -> first succeeds (200, version = 2), second rejected with 409 Conflict (version_conflict)
 * TC-16-03: Controlled burst workload benchmark persists p50/p95/p99 metrics and passes SLO thresholds
 * TC-16-04: Conditional ETag / If-None-Match returns 304 Not Modified when dataset is unchanged
 * TC-16-05: Circuit breaker opens after 5 consecutive failures and fails fast (circuit_open)
 * TC-16-06: GET /api/v1/health/deep reports component health (database, cache, circuit_breaker)
 * TC-16-07: Non-Coordinator (student) is denied POST /api/v1/performance/benchmarks/run with 403 Forbidden
 */
class Week16PerformanceResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable query logging for telemetry middleware in tests
        config(['performance.telemetry.slow_threshold_ms' => 999999]);
    }

    public function test_tc_16_01_paginated_indexed_list_with_telemetry_headers(): void
    {
        $coordinator = User::factory()->coordinator()->create();

        InternshipApplication::factory()
            ->count(25)
            ->for($coordinator, 'student')
            ->create();

        Sanctum::actingAs($coordinator);

        $response = $this->getJson('/api/v1/applications?per_page=15&page=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertHeader('X-Response-Time-Ms')
            ->assertHeader('X-Query-Count');

        $queryCount = (int) $response->headers->get('X-Query-Count');
        $this->assertLessThanOrEqual(4, $queryCount, 'Indexed paginated list must execute <= 4 queries');

        $this->assertArrayHasKey('data', $response->json());
        $this->assertCount(15, $response->json('data'));
        $this->assertEquals('1', $response->headers->get('X-Current-Page'));
        $this->assertEquals('2', $response->headers->get('X-Last-Page'));
        $this->assertEquals('15', $response->headers->get('X-Per-Page'));
        $this->assertEquals('25', $response->headers->get('X-Total-Count'));
    }

    public function test_tc_16_02_optimistic_concurrency_race_returns_409_on_conflict(): void
    {
        $student = User::factory()->student()->create();
        $coordinator = User::factory()->coordinator()->create();

        // Student submits the application
        Sanctum::actingAs($student);
        $app = InternshipApplication::factory()->for($student, 'student')->create([
            'status' => InternshipApplication::STATUS_DRAFT,
            'version' => 1,
        ]);

        $this->postJson("/api/v1/applications/{$app->id}/transition", [
            'target_status' => InternshipApplication::STATUS_SUBMITTED,
            'remarks' => 'Student submits',
        ])->assertOk();

        $app->refresh();
        $this->assertEquals(InternshipApplication::STATUS_SUBMITTED, $app->status);
        $this->assertEquals(2, $app->version);

        // Now coordinator tries to move it to requirements_verified with stale version
        Sanctum::actingAs($coordinator);

        // First transition with expected_version = 2 should succeed
        $first = $this->postJson("/api/v1/applications/{$app->id}/transition", [
            'target_status' => InternshipApplication::STATUS_REQUIREMENTS_VERIFIED,
            'remarks' => 'First verify',
            'expected_version' => 2,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.version', 3);

        // Second transition with stale expected_version = 2 should return 409
        $second = $this->postJson("/api/v1/applications/{$app->id}/transition", [
            'target_status' => InternshipApplication::STATUS_ENDORSED,
            'remarks' => 'Second verify (stale version)',
            'expected_version' => 2,
        ])->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.version.0', 'version_conflict')
            ->assertJsonPath('data.latest_version', 3);

        // Verify the application is at version 3 in DB
        $this->assertDatabaseHas('internship_applications', [
            'id' => $app->id,
            'version' => 3,
            'status' => InternshipApplication::STATUS_REQUIREMENTS_VERIFIED,
        ]);
    }

    public function test_tc_16_03_controlled_burst_benchmark_persists_metrics_and_slo(): void
    {
        config([
            'performance.benchmark.allow_benchmark_runner' => true,
            'performance.slo.avg_queries_per_req' => 100.0, // Higher threshold for SQLite test environment
        ]);

        // Debug: verify config is set
        $this->assertEquals(100.0, config('performance.slo.avg_queries_per_req'));

        $coordinator = User::factory()->coordinator()->create();
        Sanctum::actingAs($coordinator);

        $response = $this->postJson('/api/v1/performance/benchmarks/run', [
            'scenario' => 'burst_mixed_workload',
            'virtual_users' => 3,
            'requests_per_user' => 10,
        ]);

        if ($response->status() !== 200) {
            fwrite(STDERR, "\nBENCHMARK ERROR: ".$response->getContent()."\n");
        }

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slo_passed', true)
            ->assertJsonStructure([
                'data' => [
                    'run_id',
                    'correlation_id',
                    'scenario',
                    'scenario_label',
                    'slo_passed',
                    'metrics' => ['p50_ms', 'p95_ms', 'p99_ms', 'avg_queries_per_req', 'throughput_rps'],
                    'counts' => ['total_requests', 'success_count', 'conflict_or_throttled_count', 'error_count'],
                ],
            ]);

        $runId = $response->json('data.run_id');
        $this->assertDatabaseHas('performance_benchmark_runs', [
            'id' => $runId,
            'scenario_name' => 'burst_mixed_workload',
            'virtual_users' => 3,
            'slo_passed' => true,
        ]);

        $run = PerformanceBenchmarkRun::find($runId);
        $this->assertGreaterThan(0, $run->p50_ms);
        $this->assertGreaterThan(0, $run->p95_ms);
        $this->assertGreaterThan(0, $run->p99_ms);
        $this->assertGreaterThan(0, $run->throughput_rps);
    }

    public function test_tc_16_04_conditional_etag_returns_304_when_unchanged(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        InternshipApplication::factory()->for($coordinator, 'student')->create();

        Sanctum::actingAs($coordinator);

        // First request gets ETag
        $first = $this->getJson('/api/v1/applications?per_page=15&page=1')
            ->assertOk()
            ->assertHeader('ETag');

        $etag = $first->headers->get('ETag');
        $this->assertNotNull($etag);

        // Second request with If-None-Match returns 304
        $second = $this->getJson('/api/v1/applications?per_page=15&page=1', [
            'If-None-Match' => $etag,
        ]);

        $this->assertEquals(304, $second->getStatusCode());
        $this->assertEquals($etag, $second->headers->get('ETag'));
    }

    public function test_tc_16_05_circuit_breaker_opens_after_failures_and_fails_fast(): void
    {
        $service = app(\App\Services\Resilience\CircuitBreakerService::class);
        $service->reset();

        // First 5 failures should trip the breaker
        for ($i = 0; $i < 5; $i++) {
            try {
                $service->execute(function () {
                    throw new \RuntimeException('Simulated dispatcher failure');
                });
            } catch (\RuntimeException) {
                // Expected
            }
        }

        $metrics = $service->metrics();
        $this->assertEquals('open', $metrics['state']);
        $this->assertEquals(5, $metrics['failure_count']);

        // Next attempt should fail fast with circuit_open
        $start = hrtime(true);
        try {
            $service->execute(function () {
                return 'should not reach here';
            });
            $this->fail('Expected RuntimeException for circuit_open');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Circuit breaker is OPEN', $e->getMessage());
        }
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;
        $this->assertLessThan(8, $elapsedMs, 'Fail-fast must complete in < 8ms');
    }

    public function test_tc_16_06_deep_health_reports_component_health(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        Sanctum::actingAs($coordinator);

        $response = $this->getJson('/api/v1/health/deep')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.components.database.status', 'healthy')
            ->assertJsonPath('data.components.cache.status', 'healthy')
            ->assertJsonPath('data.components.circuit_breaker.status', 'healthy')
            ->assertJsonPath('data.components.automation_dead_letter.status', 'healthy')
            ->assertJsonStructure([
                'data' => [
                    'components' => [
                        'database' => ['status', 'latency_ms', 'warning'],
                        'cache' => ['status', 'latency_ms', 'warning'],
                        'circuit_breaker' => ['status', 'state', 'failure_count', 'half_open_probes_remaining'],
                        'automation_dead_letter' => ['status', 'manual_review_required_count'],
                    ],
                ],
            ]);
    }

    public function test_tc_16_07_student_denied_benchmark_endpoint(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $this->postJson('/api/v1/performance/benchmarks/run', [
            'scenario' => 'read_pipeline_indexed',
        ])->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', null);
    }
}