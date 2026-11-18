<?php

namespace App\Services;

use App\Models\SecurityAuditEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Week 15 — Module 15 / SA9 security telemetry writer.
 *
 * Every blocked security event is persisted and mirrored into a single-line
 * JSON log. Raw client addresses are never stored: only an HMAC-SHA256 hash
 * keyed by APP_KEY. Tokens, passwords and key material never reach either
 * sink.
 */
class SecurityAuditService
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function record(
        string $eventType,
        int $httpStatus = 0,
        array $details = [],
        ?User $actor = null,
        ?Request $request = null,
    ): ?SecurityAuditEvent {
        $request ??= request();

        $actor ??= $request?->user() instanceof User ? $request->user() : null;

        $endpoint = $this->endpoint($request);

        $record = null;

        try {
            $record = SecurityAuditEvent::query()->create([
                'correlation_id' => (string) Str::uuid(),
                'event_type' => $eventType,
                'severity' => self::severityFor($eventType),
                'actor_id' => $actor?->getKey(),
                'actor_role' => $actor?->role,
                'ip_hash' => $this->ipHash($request),
                'endpoint' => $endpoint,
                'http_status' => $httpStatus,
                'details' => $this->sanitizeDetails($details),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Security audit event could not be persisted.', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }

        $this->writeJsonLine([
            'logged_at' => now()->toIso8601String(),
            'correlation_id' => $record?->correlation_id,
            'event_type' => $eventType,
            'severity' => self::severityFor($eventType),
            'actor_id' => $actor?->getKey(),
            'actor_role' => $actor?->role,
            'ip_hash' => $this->ipHash($request),
            'endpoint' => $endpoint,
            'http_status' => $httpStatus,
            'details' => $this->sanitizeDetails($details),
        ]);

        return $record;
    }

    public static function severityFor(string $eventType): string
    {
        return match ($eventType) {
            SecurityAuditEvent::EVENT_BOLA_BLOCKED,
            SecurityAuditEvent::EVENT_BFLA_BLOCKED,
            SecurityAuditEvent::EVENT_MASS_ASSIGNMENT_BLOCKED => SecurityAuditEvent::SEVERITY_HIGH,
            SecurityAuditEvent::EVENT_RATE_LIMIT_EXCEEDED,
            SecurityAuditEvent::EVENT_LOGIN_FAILED,
            SecurityAuditEvent::EVENT_PAYLOAD_TOO_LARGE => SecurityAuditEvent::SEVERITY_WARNING,
            default => SecurityAuditEvent::SEVERITY_INFO,
        };
    }

    /**
     * Constant-length HMAC so the same client address always maps to the same
     * pseudonym inside one deployment, without ever storing the address.
     */
    public function ipHash(?Request $request): string
    {
        $ip = (string) ($request?->ip() ?? '');

        if ($ip === '') {
            return hash_hmac((string) config('security.audit.ip_hash_algo', 'sha256'), 'unknown', (string) config('app.key'));
        }

        return hash_hmac(
            (string) config('security.audit.ip_hash_algo', 'sha256'),
            $ip,
            (string) config('app.key'),
        );
    }

    private function endpoint(?Request $request): string
    {
        if ($request === null) {
            return 'cli';
        }

        return '/'.ltrim($request->getPathInfo(), '/');
    }

    /**
     * Defence in depth: even if a caller passes sensitive keys, they are
     * dropped before the event is persisted or logged.
     *
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function sanitizeDetails(array $details): array
    {
        $blocked = ['password', 'password_confirmation', 'token', 'plain_text_token', 'authorization', 'ip', 'ip_address'];

        $clean = [];

        foreach ($details as $key => $value) {
            if (in_array(strtolower((string) $key), $blocked, true)) {
                $clean[$key] = '[redacted]';

                continue;
            }

            $clean[$key] = is_array($value) ? $this->sanitizeDetails($value) : $value;
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeJsonLine(array $payload): void
    {
        try {
            $path = (string) config('security.audit.log_path', storage_path('logs/security_hardening.log'));
            $directory = dirname($path);

            if (! is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            file_put_contents(
                $path,
                json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL,
                FILE_APPEND | LOCK_EX,
            );
        } catch (Throwable $e) {
            Log::warning('Security audit log could not be written.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}