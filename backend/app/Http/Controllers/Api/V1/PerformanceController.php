<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PerformanceBenchmarkRun;
use App\Services\Resilience\BenchmarkRunnerService;
use App\Services\Resilience\CircuitBreakerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PerformanceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly BenchmarkRunnerService $benchmarkRunner,
        private readonly CircuitBreakerService $circuitBreaker,
    ) {
    }

    public function metrics(): JsonResponse
    {
        $this->authorize('viewAny', PerformanceBenchmarkRun::class);

        $slo = config('performance.slo', []);

        $telemetryKey = 'perf:rolling:'.(int) (now()->timestamp / config('performance.telemetry.rolling_window_seconds', 300));
        $samples = Cache::get($telemetryKey, []);

        $p50 = $this->computePercentile($samples, 'duration_ms', 50);
        $p95 = $this->computePercentile($samples, 'duration_ms', 95);
        $p99 = $this->computePercentile($samples, 'duration_ms', 99);
        $avgQueries = $samples !== [] ? array_sum(array_column($samples, 'query_count')) / count($samples) : 0;

        $recentRuns = PerformanceBenchmarkRun::query()
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn ($run) => [
                'id' => $run->id,
                'correlation_id' => $run->correlation_id,
                'scenario' => $run->scenario_name,
                'scenario_label' => $run->scenarioLabel(),
                'virtual_users' => $run->virtual_users,
                'total_requests' => $run->total_requests,
                'success_count' => $run->success_count,
                'conflict_or_throttled_count' => $run->conflict_or_throttled_count,
                'error_count' => $run->error_count,
                'p50_ms' => (float) $run->p50_ms,
                'p95_ms' => (float) $run->p95_ms,
                'p99_ms' => (float) $run->p99_ms,
                'avg_queries_per_req' => (float) $run->avg_queries_per_req,
                'throughput_rps' => (float) $run->throughput_rps,
                'slo_passed' => $run->slo_passed,
                'slo_label' => $run->sloLabel(),
                'created_at' => optional($run->created_at)->toIso8601String(),
            ])
            ->toArray();

        return self::success([
            'slo_targets' => [
                'p50_ms' => (float) ($slo['p50_ms'] ?? 50.0),
                'p95_ms' => (float) ($slo['p95_ms'] ?? 150.0),
                'p99_ms' => (float) ($slo['p99_ms'] ?? 300.0),
                'avg_queries_per_req' => (float) ($slo['avg_queries_per_req'] ?? 8.0),
                'throughput_rps_min' => (float) ($slo['throughput_rps_min'] ?? 20.0),
            ],
            'live_metrics' => [
                'p50_ms' => round($p50, 2),
                'p95_ms' => round($p95, 2),
                'p99_ms' => round($p99, 2),
                'avg_queries_per_req' => round($avgQueries, 2),
                'sample_count' => count($samples),
            ],
            'circuit_breaker' => $this->circuitBreaker->metrics(),
            'recent_benchmarks' => $recentRuns,
        ], 'Performance metrics retrieved successfully.');
    }

    public function runBenchmark(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PerformanceBenchmarkRun::class);

        if (! config('performance.benchmark.allow_benchmark_runner', false)) {
            return self::error(
                'Benchmark runner is disabled. Set PERF_ALLOW_BENCHMARK_RUNNER=true to enable.',
                503,
            );
        }

        $validated = $request->validate([
            'scenario' => ['required', 'string', 'in:'.implode(',', PerformanceBenchmarkRun::SCENARIOS)],
            'virtual_users' => ['nullable', 'integer', 'min:1', 'max:20'],
            'requests_per_user' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        try {
            $result = $this->benchmarkRunner->run($validated['scenario'], [
                'virtual_users' => $validated['virtual_users'] ?? 5,
                'requests_per_user' => $validated['requests_per_user'] ?? 20,
            ]);

            $run = PerformanceBenchmarkRun::query()->create([
                'correlation_id' => (string) \Illuminate\Support\Str::uuid(),
                'scenario_name' => $result['scenario_name'],
                'virtual_users' => $result['virtual_users'],
                'total_requests' => $result['total_requests'],
                'success_count' => $result['success_count'],
                'conflict_or_throttled_count' => $result['conflict_or_throttled_count'],
                'error_count' => $result['error_count'],
                'p50_ms' => $result['p50_ms'],
                'p95_ms' => $result['p95_ms'],
                'p99_ms' => $result['p99_ms'],
                'avg_queries_per_req' => $result['avg_queries_per_req'],
                'throughput_rps' => $result['throughput_rps'],
                'slo_passed' => $result['slo_passed'],
                'executed_by' => $request->user()?->getKey(),
            ]);

            return self::success([
                'run_id' => $run->id,
                'correlation_id' => $run->correlation_id,
                'scenario' => $run->scenario_name,
                'scenario_label' => $run->scenarioLabel(),
                'slo_passed' => $run->slo_passed,
                'metrics' => [
                    'p50_ms' => (float) $run->p50_ms,
                    'p95_ms' => (float) $run->p95_ms,
                    'p99_ms' => (float) $run->p99_ms,
                    'avg_queries_per_req' => (float) $run->avg_queries_per_req,
                    'throughput_rps' => (float) $run->throughput_rps,
                ],
                'counts' => [
                    'total_requests' => $run->total_requests,
                    'success_count' => $run->success_count,
                    'conflict_or_throttled_count' => $run->conflict_or_throttled_count,
                    'error_count' => $run->error_count,
                ],
                'created_at' => optional($run->created_at)->toIso8601String(),
            ], 'Benchmark completed successfully.');
        } catch (Throwable $e) {
            Log::error('Benchmark execution failed: '.$e->getMessage(), [
                'scenario' => $validated['scenario'] ?? 'unknown',
                'exception' => $e::class,
            ]);

            return self::error('Benchmark execution failed: '.$e->getMessage(), 500);
        }
    }

    private function computePercentile(array $samples, string $field, int $percentile): float
    {
        if ($samples === []) {
            return 0.0;
        }

        $values = array_column($samples, $field);
        sort($values);
        $index = (int) ceil($percentile / 100 * count($values)) - 1;
        $index = max(0, min($index, count($values) - 1));

        return (float) $values[$index];
    }
}