<?php

namespace App\Providers;

use App\Models\AutomationJob;
use App\Models\InternshipApplication;
use App\Models\SecureIntegrationEnvelope;
use App\Models\SecurityAuditEvent;
use App\Models\User;
use App\Policies\AutomationJobPolicy;
use App\Policies\InternshipApplicationPolicy;
use App\Policies\SecureIntegrationEnvelopePolicy;
use App\Policies\SecurityAuditEventPolicy;
use App\Services\ApplicationWorkflowService;
use App\Services\Automation\AutomationDispatcherService;
use App\Services\Automation\AutomationJobService;
use App\Services\Automation\AutomationSweepService;
use App\Services\Crypto\EnvelopeCryptoService;
use App\Services\SecurityAuditService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ApplicationWorkflowService::class);
        $this->app->singleton(AutomationJobService::class);
        $this->app->singleton(AutomationDispatcherService::class);
        $this->app->singleton(AutomationSweepService::class);
        $this->app->singleton(EnvelopeCryptoService::class);
        $this->app->singleton(SecurityAuditService::class);
    }

    public function boot(): void
    {
        Gate::policy(InternshipApplication::class, InternshipApplicationPolicy::class);
        Gate::policy(AutomationJob::class, AutomationJobPolicy::class);
        Gate::policy(SecureIntegrationEnvelope::class, SecureIntegrationEnvelopePolicy::class);
        Gate::policy(SecurityAuditEvent::class, SecurityAuditEventPolicy::class);

        $this->configureRateLimiters();
    }

    /**
     * Week 15 — Module 15 / SA9: tiered, per-identity rate limiting. Every
     * rejection returns the standard JSON envelope with Retry-After and is
     * recorded in the security audit trail.
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('auth-login', function (Request $request): Limit {
            $key = strtolower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute($this->perMinute('auth_login_per_min', 5))
                ->by($key)
                ->response(fn (Request $request, array $headers): JsonResponse => $this->tooManyRequests($request, $headers));
        });

        RateLimiter::for('api-standard', function (Request $request): Limit {
            $user = $request->user();
            $key = $user instanceof User ? 'user:'.$user->getKey() : 'ip:'.$request->ip();

            return Limit::perMinute($this->perMinute('api_standard_per_min', 60))
                ->by($key)
                ->response(fn (Request $request, array $headers): JsonResponse => $this->tooManyRequests($request, $headers));
        });

        RateLimiter::for('integration-webhook', function (Request $request): Limit {
            $key = ($request->header('X-Key-Id') ?? 'anon').'|'.$request->ip();

            return Limit::perMinute($this->perMinute('integration_webhook_per_min', 30))
                ->by($key)
                ->response(fn (Request $request, array $headers): JsonResponse => $this->tooManyRequests($request, $headers));
        });
    }

    private function perMinute(string $configKey, int $default): int
    {
        return max(1, (int) config('security.rate_limits.'.$configKey, $default));
    }

    /**
     * @param  array<string, string|int>  $headers
     */
    private function tooManyRequests(Request $request, array $headers): JsonResponse
    {
        $retryAfter = (int) ($headers['Retry-After'] ?? 60);

        try {
            app(SecurityAuditService::class)->record(
                SecurityAuditEvent::EVENT_RATE_LIMIT_EXCEEDED,
                429,
                [
                    'retry_after' => $retryAfter,
                    'method' => $request->getMethod(),
                ],
                $request->user() instanceof User ? $request->user() : null,
                $request,
            );
        } catch (\Throwable) {
            // Telemetry must never turn a throttle response into a 500.
        }

        return new JsonResponse([
            'success' => false,
            'message' => 'Rate limit exceeded. Please retry after the cooldown period.',
            'data' => null,
            'errors' => null,
        ], 429, $headers);
    }
}