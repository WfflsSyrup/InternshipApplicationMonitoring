<?php

use App\Models\PerformanceBenchmarkRun;

return [

    /*
    |--------------------------------------------------------------------------
    | SLO Targets
    |--------------------------------------------------------------------------
    |
    | Service Level Objectives for the API. Used by the benchmark runner
    | and the PerformanceController to evaluate compliance.
    |
    */

    'slo' => [
        'p50_ms' => (float) env('PERF_SLO_P50_MS', 50.0),
        'p95_ms' => (float) env('PERF_SLO_P95_MS', 150.0),
        'p99_ms' => (float) env('PERF_SLO_P99_MS', 300.0),
        'avg_queries_per_req' => (float) env('PERF_SLO_AVG_QUERIES', 8.0),
        'throughput_rps_min' => (float) env('PERF_SLO_RPS_MIN', 20.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Benchmark Runner
    |--------------------------------------------------------------------------
    |
    | The benchmark runner is gated by allow_benchmark_runner and only
    | executes deterministic, synthetic workloads. It never touches real
    | user data or external services.
    |
    */

    'benchmark' => [
        'allow_benchmark_runner' => filter_var(
            env('PERF_ALLOW_BENCHMARK_RUNNER', false),
            FILTER_VALIDATE_BOOLEAN,
        ),
        'scenarios' => [
            PerformanceBenchmarkRun::SCENARIO_READ_PIPELINE_INDEXED,
            PerformanceBenchmarkRun::SCENARIO_OPTIMISTIC_CONCURRENCY_RACE,
            PerformanceBenchmarkRun::SCENARIO_BURST_MIXED_WORKLOAD,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Telemetry
    |--------------------------------------------------------------------------
    |
    | Rolling window for in-cache latency/query metrics. The middleware
    | logs slow/over-budget calls to storage/logs/performance_telemetry.log.
    |
    */

    'telemetry' => [
        'log_path' => env(
            'PERF_TELEMETRY_LOG_PATH',
            storage_path('logs/performance_telemetry.log'),
        ),
        'slow_threshold_ms' => (float) env('PERF_SLOW_THRESHOLD_MS', 150.0),
        'query_budget' => (int) env('PERF_QUERY_BUDGET', 8),
        'rolling_window_seconds' => (int) env('PERF_ROLLING_WINDOW_SECONDS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker (Automation Dispatcher)
    |--------------------------------------------------------------------------
    |
    | Three-state circuit breaker backed by Laravel Cache. Protects the
    | automation dispatcher from cascading failures.
    |
    */

    'circuit_breaker' => [
        'failure_threshold' => (int) env('PERF_CB_FAILURE_THRESHOLD', 5),
        'open_ttl_seconds' => (int) env('PERF_CB_OPEN_TTL_SECONDS', 60),
        'half_open_probes' => (int) env('PERF_CB_HALF_OPEN_PROBES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Deep Health Probe
    |--------------------------------------------------------------------------
    |
    | Thresholds for the deep readiness endpoint.
    |
    */

    'deep_health' => [
        'db_latency_warning_ms' => (float) env('PERF_DB_LATENCY_WARN_MS', 50.0),
        'cache_latency_warning_ms' => (float) env('PERF_CACHE_LATENCY_WARN_MS', 10.0),
    ],
];