<?php

/**
 * Week 15 — Module 15 / SA9: strict CORS allowlist.
 *
 * The API no longer answers arbitrary origins. Every entry of
 * CORS_ALLOWED_ORIGINS is either an exact origin
 * (http://localhost:5000) or, when it contains "*", a controlled
 * localhost-only pattern used for local Flutter web development.
 */

$patternFor = static function (string $origin): string {
    $segments = array_map(
        static fn (string $segment): string => preg_quote($segment, '/'),
        explode('*', $origin),
    );

    return '/^'.implode('.*', $segments).'$/';
};

$configured = array_values(array_filter(array_map(
    static fn (string $origin): string => trim($origin),
    explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:8080,http://127.0.0.1:8080,http://localhost:5000',
    )),
)));

$allowAll = in_array('*', $configured, true);

$patterns = array_values(array_filter(
    $configured,
    static fn (string $origin): bool => $origin !== '*' && str_contains($origin, '*'),
));

$origins = array_values(array_filter(
    $configured,
    static fn (string $origin): bool => $origin !== '*' && ! str_contains($origin, '*'),
));

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $allowAll ? ['*'] : $origins,

    'allowed_origins_patterns' => $allowAll ? [] : array_map($patternFor, $patterns),

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Key-Id',
        'X-Nonce',
        'X-Requested-With',
        'X-Signature-256',
        'X-Timestamp',
    ],

    'exposed_headers' => ['Retry-After'],

    'max_age' => 3600,

    'supports_credentials' => false,

];