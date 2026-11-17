<?php

namespace App\Support\Crypto;

use Throwable;

/**
 * Appends single-line JSON audit entries for every cryptographic verification
 * attempt.
 *
 * The writer is whitelist-based on purpose: only the audit fields below can
 * ever reach disk, so signatures, IVs, ciphertext, tags and key material are
 * structurally impossible to log even if a caller passes them in.
 */
class CryptoLogSanitizer
{
    /**
     * @var array<int, string>
     */
    private const ALLOWED_KEYS = [
        'timestamp',
        'level',
        'correlation_id',
        'envelope_id',
        'direction',
        'application_ref',
        'key_id',
        'cipher',
        'outcome',
        'http_status',
        'duration_ms',
        'failure_reason',
        'mode',
    ];

    public static function write(string $level, array $context): void
    {
        try {
            $line = self::sanitize($context + ['level' => $level, 'timestamp' => now()->toIso8601String()]);

            $encoded = json_encode(
                $line,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
            );

            if ($encoded === false) {
                return;
            }

            $path = (string) (config('crypto.log_path') ?: storage_path('logs/crypto_audit.log'));

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
        } catch (Throwable) {
            // Audit logging must never break the request it is describing.
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function sanitize(array $context): array
    {
        $line = [];

        foreach (self::ALLOWED_KEYS as $key) {
            $line[$key] = array_key_exists($key, $context)
                ? self::sanitizeValue($context[$key])
                : null;
        }

        return $line;
    }

    private static function sanitizeValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return is_scalar($value) || $value === null ? $value : null;
        }

        return self::redactString($value);
    }

    /**
     * Removes bearer tokens and long binary-looking blobs that would only ever
     * be key material or ciphertext.
     */
    private static function redactString(string $value): string
    {
        $value = preg_replace(
            '/(?i)(bearer\s+)[A-Za-z0-9\-._~+\/]+=*/',
            '$1[REDACTED]',
            $value,
        ) ?? $value;

        return preg_replace_callback(
            '/[A-Za-z0-9+\/]{48,}={0,2}/',
            static fn (array $match): string => '[REDACTED]',
            $value,
        ) ?? $value;
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}