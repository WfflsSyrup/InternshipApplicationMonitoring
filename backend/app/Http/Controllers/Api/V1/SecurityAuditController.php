<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SecurityAuditEventResource;
use App\Models\SecurityAuditEvent;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Week 15 — Module 15 / SA9: coordinator-only security telemetry.
 */
class SecurityAuditController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SecurityAuditEvent::class);

        $maxPerPage = max(1, (int) config('security.audit.per_page_max', 50));
        $perPage = min($maxPerPage, max(1, (int) $request->query('per_page', 25)));

        $events = SecurityAuditEvent::query()
            ->withEventType($request->query('event_type'))
            ->withSeverity($request->query('severity'))
            ->latest('id')
            ->limit($perPage)
            ->get();

        return self::success(
            SecurityAuditEventResource::collection($events),
            'Security audit events retrieved successfully.',
        );
    }

    public function posture(): JsonResponse
    {
        $this->authorize('viewAny', SecurityAuditEvent::class);

        return self::success($this->posturePayload(), 'Security hardening posture retrieved successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function posturePayload(): array
    {
        /** @var array<int, string> $origins */
        $origins = (array) config('cors.allowed_origins', []);

        /** @var array<int, string> $patterns */
        $patterns = (array) config('cors.allowed_origins_patterns', []);

        $headers = (array) config('security.headers', []);

        $allowAll = in_array('*', $origins, true);

        return [
            'strict_cors' => ! $allowAll && (count($origins) > 0 || count($patterns) > 0),
            'cors_origin_count' => count($origins),
            'cors_pattern_count' => count($patterns),
            'security_headers_enabled' => count($headers) >= 6,
            'security_headers' => array_keys($headers),
            'max_payload_kb' => (int) config('security.max_payload_kb', 64),
            'input_sanitization_enabled' => (bool) config('security.sanitization.enabled', true),
            'sanctum_ttl_minutes' => (int) config('sanctum.expiration', 60),
            'login_rate_limit_per_min' => (int) config('security.rate_limits.auth_login_per_min', 5),
            'api_rate_limit_per_min' => (int) config('security.rate_limits.api_standard_per_min', 60),
            'webhook_rate_limit_per_min' => (int) config('security.rate_limits.integration_webhook_per_min', 30),
            'debug_mode_disabled' => ! (bool) config('app.debug'),
            'audit_events_total' => SecurityAuditEvent::query()->count(),
            'audit_events_last_24h' => SecurityAuditEvent::query()
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ];
    }
}