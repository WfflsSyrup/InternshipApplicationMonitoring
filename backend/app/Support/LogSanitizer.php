<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Writes single-line JSON automation logs with credentials stripped.
 *
 * Every line carries a stable correlation id so a dispatcher attempt can be
 * traced end to end without ever persisting a token, password or secret.
 */
final class LogSanitizer
{
    public const REDACTED = '[redacted]';

    /**
     * Keys whose values must never reach the automation log.
     *
     * @var array<int, string>
     */
    private const SENSITIVE_KEYS = [
        'token',
        'password',
        'authorization',
        'api_key',
        'apikey',
        'secret',
        'client_secret',
        'access_token',
        'refresh_token',
        'bearer',
        'cookie',
        'set-cookie',
    ];

    /**
     * Keys always emitted first so log lines stay comparable across jobs.
     *
     * @var array<int, string>
     */
    private const BASE_KEYS = [
        'timestamp',
        'level',
        'correlation_id',
        'job_id',
        'idempotency_key',
        'application_ref',
        'automation_type',
        'attempt',
        'outcome',
        'http_status',
        'duration_ms',
    ];

    public static function redact(mixed $value): mixed
    {
        if ($value instanceof Throwable) {
            return self::redactMessage($value->getMessage());
        }

        if (is_array($value)) {
            $clean = [];

            foreach ($value as $key => $item) {
                $clean[$key] = self::isSensitive((string) $key) ? self::REDACTED : self::redact($item);
            }

            return $clean;
        }

        if (is_string($value)) {
            return self::redactString($value);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public static function sanitize(array $context): array
    {
        $line = [
            'timestamp' => $context['timestamp'] ?? Carbon::now()->toIso8601String(),
            'level' => $context['level'] ?? 'info',
            'correlation_id' => $context['correlation_id'] ?? null,
            'job_id' => $context['job_id'] ?? null,
            'idempotency_key' => $context['idempotency_key'] ?? null,
            'application_ref' => $context['application_ref'] ?? null,
            'automation_type' => $context['automation_type'] ?? null,
            'attempt' => $context['attempt'] ?? null,
            'outcome' => $context['outcome'] ?? null,
            'http_status' => $context['http_status'] ?? null,
            'duration_ms' => $context['duration_ms'] ?? null,
        ];

        foreach ($context as $key => $value) {
            if (in_array($key, self::BASE_KEYS, true)) {
                continue;
            }

            $line[$key] = self::isSensitive((string) $key) ? self::REDACTED : self::redact($value);
        }

        /** @var array<string, mixed> $line */
        return $line;
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function write(string $level, array $context): void
    {
        $line = self::sanitize($context + ['level' => $level]);

        $encoded = json_encode(
            $line,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        );

        if ($encoded === false) {
            return;
        }

        $path = (string) (config('automation.log_path') ?: storage_path('logs/automation.log'));

        if (! self::isAbsolutePath($path)) {
            $path = base_path($path);
        }

        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        if (! is_dir($directory)) {
            return;
        }

        file_put_contents($path, $encoded.PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    private static function isSensitive(string $key): bool
    {
        $normalized = strtolower($key);

        return in_array($normalized, self::SENSITIVE_KEYS, true)
            || str_contains($normalized, 'password')
            || str_contains($normalized, 'secret')
            || str_contains($normalized, 'token')
            || str_contains($normalized, 'api_key');
    }

    private static function redactString(string $value): string
    {
        return preg_replace(
            '/(?i)(bearer\s+)[A-Za-z0-9\-._~+\/]+=*/',
            '$1'.self::REDACTED,
            $value,
        ) ?? $value;
    }

    private static function redactMessage(string $message): string
    {
        return self::redactString(preg_replace('/\s+/', ' ', trim($message)) ?? '');
    }
}