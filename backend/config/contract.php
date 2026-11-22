<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenAPI Contract Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the OpenAPI 3.1 contract specification, parity
    | auditing, and developer portal rendering.
    |
    */

    'spec' => [
        'path' => base_path('openapi/project10-v1.openapi.yaml'),
        'version' => '1.4.0',
        'openapi_version' => '3.1.0',
    ],

    'headers' => [
        'contract_version' => 'X-API-Contract-Version',
        'openapi_spec' => 'X-OpenAPI-Spec',
    ],

    'portal' => [
        'title' => 'Internship Monitor API — Developer Portal',
        'description' => 'Self-contained API documentation explorer generated from OpenAPI 3.1 spec',
        'csp' => "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'",
    ],

    'parity_audit' => [
        'normalize_route_params' => true,
        'exclude_patterns' => [
            '/docs/*',
        ],
    ],

    'secret_scan' => [
        'patterns' => [
            '/sk_live_[a-zA-Z0-9]{24,}/',  // Stripe-like live keys
            '/rk_live_[a-zA-Z0-9]{24,}/',  // Stripe restricted keys
            '/AKIA[0-9A-Z]{16}/',           // AWS access keys
            '/AIza[0-9A-Za-z\\-_]{35}/',    // Google API keys
            '/gh[pousr]_[A-Za-z0-9]{36,}/', // GitHub tokens
            '/password\s*[:=]\s*["\']?[^\s"\']{8,}["\']?/i',  // Password assignments
            '/secret\s*[:=]\s*["\']?[^\s"\']{16,}["\']?/i',  // Secret assignments
            '/private[_\s-]?key/i',         // Private key references
        ],
        'allowed_domains' => [
            'uls-test.edu.ph',
            '127.0.0.1',
            'localhost',
            'example.com',
        ],
    ],
];