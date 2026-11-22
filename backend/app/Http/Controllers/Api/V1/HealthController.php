<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AutomationJob;
use App\Services\Resilience\CircuitBreakerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    use ApiResponse;

    public function show(): JsonResponse
    {
        $database = 'connected';

        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            $database = 'unavailable';
        }

        if ($database === 'unavailable') {
            return self::error('Service temporarily unavailable.', 503);
        }

        return self::success([
            'status' => 'ok',
            'service' => config('app.name'),
            'environment' => config('app.env'),
            'database' => $database,
            'timestamp' => now()->toIso8601String(),
        ], 'Service is healthy.');
    }

    public function deep(): JsonResponse
    {
        $components = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'circuit_breaker' => $this->checkCircuitBreaker(),
            'automation_dead_letter' => $this->checkAutomationDeadLetter(),
        ];

        $allHealthy = collect($components)->every(fn (array $c) => $c['status'] === 'healthy');

        $statusCode = $allHealthy ? 200 : 503;

        return self::success([
            'status' => $allHealthy ? 'ok' : 'degraded',
            'service' => config('app.name'),
            'environment' => config('app.env'),
            'components' => $components,
            'timestamp' => now()->toIso8601String(),
        ], $allHealthy ? 'All systems operational.' : 'One or more components degraded.')->setStatusCode($statusCode);
    }

    private function checkDatabase(): array
    {
        $start = hrtime(true);

        try {
            DB::connection()->getPdo();
            DB::connection()->select('SELECT 1');

            $latencyMs = (hrtime(true) - $start) / 1_000_000;

            $warning = $latencyMs > config('performance.deep_health.db_latency_warning_ms', 50.0);

            return [
                'status' => 'healthy',
                'latency_ms' => round($latencyMs, 2),
                'warning' => $warning,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'unhealthy',
                'error' => 'Database connection failed: '.$e->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        $start = hrtime(true);

        try {
            $key = 'health:cache:'.now()->timestamp;
            Cache::put($key, 'ok', now()->addSeconds(10));
            $value = Cache::get($key);
            Cache::forget($key);

            $latencyMs = (hrtime(true) - $start) / 1_000_000;

            $warning = $latencyMs > config('performance.deep_health.cache_latency_warning_ms', 10.0);

            return [
                'status' => $value === 'ok' ? 'healthy' : 'unhealthy',
                'latency_ms' => round($latencyMs, 2),
                'warning' => $warning,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'unhealthy',
                'error' => 'Cache unavailable: '.$e->getMessage(),
            ];
        }
    }

    private function checkCircuitBreaker(): array
    {
        try {
            $service = app(CircuitBreakerService::class);
            $metrics = $service->metrics();

            return [
                'status' => $metrics['state'] === 'open' ? 'degraded' : 'healthy',
                'state' => $metrics['state'],
                'failure_count' => $metrics['failure_count'],
                'half_open_probes_remaining' => $metrics['half_open_probes_remaining'],
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'unhealthy',
                'error' => 'Circuit breaker check failed: '.$e->getMessage(),
            ];
        }
    }

    private function checkAutomationDeadLetter(): array
    {
        try {
            $count = AutomationJob::query()
                ->where('status', AutomationJob::STATUS_MANUAL_REVIEW_REQUIRED)
                ->count();

            return [
                'status' => 'healthy',
                'manual_review_required_count' => $count,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'unhealthy',
                'error' => 'Automation dead letter check failed: '.$e->getMessage(),
            ];
        }
    }
}