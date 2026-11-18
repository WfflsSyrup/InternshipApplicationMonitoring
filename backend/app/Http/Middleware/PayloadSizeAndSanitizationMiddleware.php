<?php

namespace App\Http\Middleware;

use App\Models\SecurityAuditEvent;
use App\Services\SecurityAuditService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Week 15 — Module 15 / SA9.
 *
 * Two controls in one pass over /api/v1/*:
 *  1. Payload guard — reject bodies above SECURITY_MAX_PAYLOAD_KB with 413.
 *  2. Input sanitization — trim, drop null bytes and strip HTML/script tags
 *     from string inputs, while leaving cryptographic fields untouched.
 */
class PayloadSizeAndSanitizationMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $limitBytes = $this->limitBytes();

        if ($this->declaredLength($request) > $limitBytes || $this->actualLength($request) > $limitBytes) {
            $this->audit($request, $limitBytes);

            return new JsonResponse([
                'success' => false,
                'message' => sprintf(
                    'Request payload exceeds the %d KB limit configured for this API.',
                    (int) ceil($limitBytes / 1024),
                ),
                'data' => null,
                'errors' => [
                    'payload' => [sprintf('The request body must not exceed %d KB.', (int) ceil($limitBytes / 1024))],
                ],
            ], 413);
        }

        if ($this->sanitizationEnabled() && $request->isJson() && $request->getContent() !== '') {
            $this->sanitizeRequest($request);
        }

        return $next($request);
    }

    private function limitBytes(): int
    {
        $kilobytes = max(1, (int) config('security.max_payload_kb', 64));

        return $kilobytes * 1024;
    }

    private function declaredLength(Request $request): int
    {
        $length = $request->headers->get('Content-Length');

        return $length === null ? 0 : (int) $length;
    }

    private function actualLength(Request $request): int
    {
        if ($request->isJson()) {
            return strlen($request->getContent());
        }

        return 0;
    }

    private function sanitizationEnabled(): bool
    {
        return (bool) config('security.sanitization.enabled', true);
    }

    /**
     * @return array<int, string>
     */
    private function exemptFields(): array
    {
        return array_map(
            static fn (string $field): string => strtolower($field),
            (array) config('security.sanitization.exempt_fields', []),
        );
    }

    private function sanitizeRequest(Request $request): void
    {
        $payload = $request->json()->all();

        if (! is_array($payload) || $payload === []) {
            return;
        }

        $clean = $this->sanitizeValue($payload, '', $this->exemptFields());

        if ($clean !== $payload) {
            $request->merge($clean);
        }
    }

    /**
     * @param  array<string, mixed>  $exempt
     * @return array<string, mixed>
     */
    private function sanitizeValue(mixed $value, string $key, array $exempt): mixed
    {
        if (in_array(strtolower($key), $exempt, true)) {
            return $value;
        }

        if (is_array($value)) {
            $clean = [];

            foreach ($value as $childKey => $childValue) {
                $clean[$childKey] = $this->sanitizeValue($childValue, (string) $childKey, $exempt);
            }

            return $clean;
        }

        if (! is_string($value)) {
            return $value;
        }

        $clean = str_replace("\0", '', $value);
        $clean = (string) preg_replace('#<script\b[^>]*>.*?</script>#is', '', $clean);
        $clean = (string) preg_replace('#<style\b[^>]*>.*?</style>#is', '', $clean);
        $clean = trim(strip_tags($clean));

        return $clean;
    }

    private function audit(Request $request, int $limitBytes): void
    {
        try {
            app(SecurityAuditService::class)->record(
                SecurityAuditEvent::EVENT_PAYLOAD_TOO_LARGE,
                413,
                [
                    'limit_bytes' => $limitBytes,
                    'declared_bytes' => $this->declaredLength($request),
                    'actual_bytes' => $this->actualLength($request),
                    'method' => $request->getMethod(),
                ],
                null,
                $request,
            );
        } catch (\Throwable) {
            // Telemetry must never turn a rejection into a server error.
        }
    }
}