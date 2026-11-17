<?php

$patternFor = static function (string $origin): string {
    $segments = array_map(
        static fn (string $segment): string => preg_quote($segment, '/'),
        explode('*', $origin),
    );

    return '/^'.implode('.*', $segments).'$/';
};

$origins = array_values(array_filter(array_map(
    static fn (string $origin): string => trim($origin),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:*,http://127.0.0.1:*')),
)));

$allowAll = in_array('*', $origins, true);

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowAll ? ['*'] : [],

    'allowed_origins_patterns' => $allowAll ? [] : array_map($patternFor, $origins),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];