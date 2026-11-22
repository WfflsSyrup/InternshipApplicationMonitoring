<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Week 16 — Module 16 / SA10: performance telemetry middleware.
 *
 * Measures request duration and SQL query count for every /api/v1/* request.
 * Attaches X-Response-Time-Ms and X-Query-Count headers.
 * Logs slow/over-budget calls to performance_telemetry.log as single-line JSON.
 */
class PerformanceTelemetryMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->appliesTo($request)) {
            return $next($request);
        }

        $queryLogEnabled = DB::connection()->getConfig('logging_queries', false);

        if (! $queryLogEnabled) {
            DB::enableQueryLog();
        }

        $start = hrtime(true);

        /** @var Response $response */
        $response = $next($request);

        $durationNs = hrtime(true) - $start;
        $durationMs = $durationNs / 1_000_000;

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        DB::disableQueryLog();

        if (! $queryLogEnabled) {
            DB::flushQueryLog();
        }

        $response->headers->set('X-Response-Time-Ms', (string) round($durationMs, 2));
        $response->headers->set('X-Query-Count', (string) $queryCount);

        $this->recordTelemetry($request, $response, $durationMs, $queryCount);

        $this->maybeLogSlowCall($request, $response, $durationMs, $queryCount);

        return $response;
    }

    private function appliesTo(Request $request): bool
    {
        return $request->is('api/*') || str_starts_with($request->getPathInfo(), '/api/');
    }

    private function recordTelemetry(Request $request, Response $response, float $durationMs, int $queryCount): void
    {
        try {
            $key = 'perf:rolling:'.$this->windowKey();
            $entry = [
                'timestamp' => now()->toIso8601String(),
                'endpoint' => '/'.ltrim($request->getPathInfo(), '/'),
                'method' => $request->getMethod(),
                'duration_ms' => round($durationMs, 2),
                'query_count' => $queryCount,
                'status' => $response->getStatusCode(),
            ];

            $existing = Cache::get($key, []);
            $existing[] = $entry;
            $existing = array_slice($existing, -500);

            Cache::put($key, $existing, now()->addSeconds(config('performance.telemetry.rolling_window_seconds', 300)));
        } catch (\Throwable $e) {
            // Telemetry must never affect the response.
            Log::warning('Performance telemetry cache write failed: '.$e->getMessage());
        }
    }

    private function maybeLogSlowCall(Request $request, Response $response, float $durationMs, int $queryCount): void
    {
        $slowThreshold = (float) config('performance.telemetry.slow_threshold_ms', 150.0);
        $queryBudget = (int) config('performance.telemetry.query_budget', 8);

        if ($durationMs <= $slowThreshold && $queryCount <= $queryBudget) {
            return;
        }

        try {
            $path = (string) config('performance.telemetry.log_path', storage_path('logs/performance_telemetry.log'));
            $directory = dirname($path);

            if (! is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            $payload = [
                'logged_at' => now()->toIso8601String(),
                'endpoint' => '/'.ltrim($request->getPathInfo(), '/'),
                'method' => $request->getMethod(),
                'duration_ms' => round($durationMs, 2),
                'query_count' => $queryCount,
                'slow' => $durationMs > $slowThreshold,
                'over_query_budget' => $queryCount > $queryBudget,
                'status' => $response->getStatusCode(),
                'user_id' => $request->user()?->getKey(),
                'user_role' => $request->user()?->role,
            ];

            file_put_contents(
                $path,
                json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL,
                FILE_APPEND | LOCK_EX,
            );
        } catch (\Throwable $e) {
            Log::warning('Performance telemetry log write failed: '.$e->getMessage());
        }
    }

    private function windowKey(): string
    {
        $window = (int) config('performance.telemetry.rolling_window_seconds', 300);
        return (string) (int) (now()->timestamp / $window);
    }
}