<?php

namespace App\Services\Resilience;

use App\Models\InternshipApplication;
use App\Models\PerformanceBenchmarkRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Week 16 — Module 16 / SA10: controlled benchmark runner.
 *
 * Executes deterministic, synthetic workloads and computes p50/p95/p99
 * latency, throughput, and SLO compliance. Never touches real user data
 * or external services. Gated by config('performance.benchmark.allow_benchmark_runner').
 */
class BenchmarkRunnerService
{
    public function __construct(
        private readonly int $defaultVirtualUsers = 5,
        private readonly int $defaultRequestsPerUser = 20,
    ) {
    }

    /**
     * Run a controlled benchmark scenario.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function run(string $scenario, array $options = []): array
    {
        if (! config('performance.benchmark.allow_benchmark_runner', false)) {
            throw new \RuntimeException('Benchmark runner is disabled. Set PERF_ALLOW_BENCHMARK_RUNNER=true to enable.');
        }

        if (! in_array($scenario, PerformanceBenchmarkRun::SCENARIOS, true)) {
            throw new \InvalidArgumentException('Unknown benchmark scenario: '.$scenario);
        }

        $virtualUsers = (int) ($options['virtual_users'] ?? $this->defaultVirtualUsers);
        $requestsPerUser = (int) ($options['requests_per_user'] ?? $this->defaultRequestsPerUser);
        $totalRequests = $virtualUsers * $requestsPerUser;

        $latencies = [];
        $queryCounts = [];
        $successCount = 0;
        $conflictCount = 0;
        $errorCount = 0;

        $startTime = hrtime(true);

        try {
            match ($scenario) {
                PerformanceBenchmarkRun::SCENARIO_READ_PIPELINE_INDEXED => $this->runReadPipelineIndexed(
                    $virtualUsers,
                    $requestsPerUser,
                    $latencies,
                    $queryCounts,
                    $successCount,
                    $errorCount,
                ),
                PerformanceBenchmarkRun::SCENARIO_OPTIMISTIC_CONCURRENCY_RACE => $this->runOptimisticConcurrencyRace(
                    $virtualUsers,
                    $requestsPerUser,
                    $latencies,
                    $queryCounts,
                    $successCount,
                    $conflictCount,
                    $errorCount,
                ),
                PerformanceBenchmarkRun::SCENARIO_BURST_MIXED_WORKLOAD => $this->runBurstMixedWorkload(
                    $virtualUsers,
                    $requestsPerUser,
                    $latencies,
                    $queryCounts,
                    $successCount,
                    $conflictCount,
                    $errorCount,
                ),
            };
        } catch (Throwable $e) {
            Log::error('Benchmark scenario failed: '.$e->getMessage(), [
                'scenario' => $scenario,
                'exception' => $e::class,
            ]);
            throw $e;
        }

        $totalDurationNs = hrtime(true) - $startTime;
        $totalDurationSec = $totalDurationNs / 1_000_000_000;

        $p50 = $this->percentile($latencies, 50);
        $p95 = $this->percentile($latencies, 95);
        $p99 = $this->percentile($latencies, 99);
        $avgQueries = array_sum($queryCounts) / max(1, count($queryCounts));
        $throughputRps = $totalDurationSec > 0 ? $totalRequests / $totalDurationSec : 0;

        $sloPassed = $this->evaluateSlo(
            $p50,
            $p95,
            $p99,
            $avgQueries,
            $throughputRps,
        );

        return [
            'scenario_name' => $scenario,
            'virtual_users' => $virtualUsers,
            'total_requests' => $totalRequests,
            'success_count' => $successCount,
            'conflict_or_throttled_count' => $conflictCount,
            'error_count' => $errorCount,
            'p50_ms' => round($p50, 2),
            'p95_ms' => round($p95, 2),
            'p99_ms' => round($p99, 2),
            'avg_queries_per_req' => round($avgQueries, 2),
            'throughput_rps' => round($throughputRps, 2),
            'slo_passed' => $sloPassed,
        ];
    }

    /**
     * Scenario 1: Read Pipeline Indexed
     * Simulates concurrent GET /applications with filters, verifying <= 4 queries and p95 < 150ms.
     */
    private function runReadPipelineIndexed(
        int $virtualUsers,
        int $requestsPerUser,
        array &$latencies,
        array &$queryCounts,
        int &$successCount,
        int &$errorCount,
    ): void {
        $user = User::factory()->coordinator()->create();
        $token = $user->createToken('benchmark')->plainTextToken;

        $applications = InternshipApplication::factory()
            ->count(50)
            ->for($user, 'student')
            ->create();

        for ($i = 0; $i < $virtualUsers; $i++) {
            for ($j = 0; $j < $requestsPerUser; $j++) {
                $status = $j % 3 === 0 ? null : InternshipApplication::STATUS_SUBMITTED;

                $requestStart = hrtime(true);
                DB::enableQueryLog();

                try {
                    $query = InternshipApplication::query()
                        ->visibleTo($user)
                        ->with([
                            'student:id,student_or_employee_id,name,role',
                            'adviser:id,name,role',
                            'partnerReviewer:id,name,role',
                        ]);

                    if ($status !== null) {
                        $query->where('status', $status);
                    }

                    $query->select('id', 'reference_code', 'status', 'host_company_name', 'target_role', 'student_id', 'adviser_id', 'partner_reviewer_id', 'updated_at')
                        ->latest('id')
                        ->limit(15)
                        ->get();

                    $queries = DB::getQueryLog();
                    DB::disableQueryLog();

                    $latencies[] = (hrtime(true) - $requestStart) / 1_000_000;
                    $queryCounts[] = count($queries);
                    $successCount++;
                } catch (Throwable) {
                    DB::disableQueryLog();
                    $errorCount++;
                }
            }
        }
    }

    /**
     * Scenario 2: Optimistic Concurrency Race
     * Multiple concurrent PATCH /applications/{id} with expected_version.
     * First succeeds (200, version++), subsequent return 409 Conflict.
     */
    private function runOptimisticConcurrencyRace(
        int $virtualUsers,
        int $requestsPerUser,
        array &$latencies,
        array &$queryCounts,
        int &$successCount,
        int &$conflictCount,
        int &$errorCount,
    ): void {
        $user = User::factory()->coordinator()->create();
        $app = InternshipApplication::factory()->for($user, 'student')->create(['status' => InternshipApplication::STATUS_DRAFT]);

        for ($i = 0; $i < $virtualUsers; $i++) {
            for ($j = 0; $j < $requestsPerUser; $j++) {
                $requestStart = hrtime(true);
                DB::enableQueryLog();

                try {
                    $updated = InternshipApplication::where('id', $app->id)
                        ->where('version', $app->version)
                        ->update([
                            'host_company_name' => 'Benchmark Update '.now()->microsecond,
                            'version' => $app->version + 1,
                        ]);

                    $queries = DB::getQueryLog();
                    DB::disableQueryLog();

                    $latencies[] = (hrtime(true) - $requestStart) / 1_000_000;
                    $queryCounts[] = count($queries);

                    if ($updated > 0) {
                        $app->version++;
                        $successCount++;
                    } else {
                        $conflictCount++;
                    }
                } catch (Throwable) {
                    DB::disableQueryLog();
                    $errorCount++;
                }
            }
        }
    }

    /**
     * Scenario 3: Burst Mixed Workload
     * Mix of reads (70%) and writes (30%) under load.
     */
    private function runBurstMixedWorkload(
        int $virtualUsers,
        int $requestsPerUser,
        array &$latencies,
        array &$queryCounts,
        int &$successCount,
        int &$conflictCount,
        int &$errorCount,
    ): void {
        $user = User::factory()->coordinator()->create();
        $apps = InternshipApplication::factory()
            ->count(30)
            ->for($user, 'student')
            ->create(['status' => InternshipApplication::STATUS_DRAFT]);

        for ($i = 0; $i < $virtualUsers; $i++) {
            for ($j = 0; $j < $requestsPerUser; $j++) {
                $isRead = ($j % 10) < 7;

                $requestStart = hrtime(true);
                DB::enableQueryLog();

                try {
                    if ($isRead) {
                        InternshipApplication::query()
                            ->visibleTo($user)
                            ->with([
                                'student:id,student_or_employee_id,name,role',
                                'adviser:id,name,role',
                                'partnerReviewer:id,name,role',
                            ])
                            ->select('id', 'reference_code', 'status', 'host_company_name', 'target_role', 'student_id', 'adviser_id', 'partner_reviewer_id', 'updated_at')
                            ->latest('id')
                            ->limit(15)
                            ->get();
                        $successCount++;
                    } else {
                        $app = $apps->random();
                        $updated = InternshipApplication::where('id', $app->id)
                            ->where('version', $app->version)
                            ->update([
                                'host_company_name' => 'Burst Update '.now()->microsecond,
                                'version' => $app->version + 1,
                            ]);
                        if ($updated > 0) {
                            $app->version++;
                            $successCount++;
                        } else {
                            $conflictCount++;
                        }
                    }

                    $queries = DB::getQueryLog();
                    DB::disableQueryLog();

                    $latencies[] = (hrtime(true) - $requestStart) / 1_000_000;
                    $queryCounts[] = count($queries);
                } catch (Throwable) {
                    DB::disableQueryLog();
                    $errorCount++;
                }
            }
        }
    }

    /**
     * Compute the given percentile from a sorted array of values.
     */
    private function percentile(array $values, int $percentile): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values);
        $index = (int) ceil($percentile / 100 * count($values)) - 1;
        $index = max(0, min($index, count($values) - 1));

        return (float) $values[$index];
    }

    /**
     * Evaluate SLO compliance.
     */
    private function evaluateSlo(
        float $p50,
        float $p95,
        float $p99,
        float $avgQueries,
        float $throughputRps,
    ): bool {
        $slo = config('performance.slo', []);

        $targets = [
            'p50' => $slo['p50_ms'] ?? 50.0,
            'p95' => $slo['p95_ms'] ?? 150.0,
            'p99' => $slo['p99_ms'] ?? 300.0,
            'avg_queries' => $slo['avg_queries_per_req'] ?? 8.0,
            'throughput' => $slo['throughput_rps_min'] ?? 20.0,
        ];

        $passed = true;
        $passed &= $p50 <= $targets['p50'];
        $passed &= $p95 <= $targets['p95'];
        $passed &= $p99 <= $targets['p99'];
        $passed &= $avgQueries <= $targets['avg_queries'];
        $passed &= $throughputRps >= $targets['throughput'];

        return $passed;
    }
}