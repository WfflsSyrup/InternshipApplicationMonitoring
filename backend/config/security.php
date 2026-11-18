<?php

use App\Models\SecurityAuditEvent;

return [

    /*
    |--------------------------------------------------------------------------
    | HTTP Security Response Headers
    |--------------------------------------------------------------------------
    |
    | Attached to every /api/v1/* response by SecurityHeadersMiddleware.
    |
    */

    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), camera=(), microphone=()',
        'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'; base-uri 'none'",
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payload Guard
    |--------------------------------------------------------------------------
    |
    | Requests whose declared or actual body exceeds SECURITY_MAX_PAYLOAD_KB
    | are rejected with 413 before they reach any controller.
    |
    */

    'max_payload_kb' => (int) env('SECURITY_MAX_PAYLOAD_KB', 64),

    /*
    |--------------------------------------------------------------------------
    | Input Sanitization
    |--------------------------------------------------------------------------
    |
    | String inputs are trimmed, stripped of null bytes and of HTML/script
    | tags. Fields holding ciphertext, signatures or secrets must never be
    | rewritten or AES-256-GCM authentication would fail.
    |
    */

    'sanitization' => [

        'enabled' => (bool) env('SECURITY_SANITIZE_INPUT', true),

        'exempt_fields' => [
            'aad',
            'ciphertext',
            'ciphertext_b64',
            'iv',
            'iv_hex',
            'nonce',
            'password',
            'password_confirmation',
            'signature',
            'signature_256',
            'tag',
            'tag_b64',
            'token',
            'x-signature-256',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tiered Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Named limiter rules registered in AppServiceProvider.
    |
    */

    'rate_limits' => [

        'auth_login_per_min' => (int) env('SECURITY_AUTH_LOGIN_PER_MIN', 5),

        'api_standard_per_min' => (int) env('SECURITY_API_STANDARD_PER_MIN', 60),

        'integration_webhook_per_min' => (int) env('SECURITY_WEBHOOK_PER_MIN', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Audit Trail
    |--------------------------------------------------------------------------
    |
    | Single-line JSON log with zero raw IP addresses. Client IPs are stored
    | only as an HMAC-SHA256 hash keyed by APP_KEY.
    |
    */

    'audit' => [

        'log_path' => env('SECURITY_AUDIT_LOG_PATH', storage_path('logs/security_hardening.log')),

        'ip_hash_algo' => 'sha256',

        'per_page_max' => 50,

        'event_types' => [
            SecurityAuditEvent::EVENT_BOLA_BLOCKED,
            SecurityAuditEvent::EVENT_BFLA_BLOCKED,
            SecurityAuditEvent::EVENT_RATE_LIMIT_EXCEEDED,
            SecurityAuditEvent::EVENT_MASS_ASSIGNMENT_BLOCKED,
            SecurityAuditEvent::EVENT_PAYLOAD_TOO_LARGE,
            SecurityAuditEvent::EVENT_LOGIN_FAILED,
            SecurityAuditEvent::EVENT_SESSION_REVOKED,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Privileged Attributes (BOPLA / mass assignment)
    |--------------------------------------------------------------------------
    |
    | Client-supplied attributes that may never be set through the API. The
    | application FormRequests reject them with 422 and audit the attempt.
    |
    */

    'banned_attributes' => [
        'role',
        'status',
        'student_id',
        'adviser_id',
        'partner_reviewer_id',
        'placement_confirmed_at',
        'interview_schedule',
        'interview_passed_at',
        'interview_failed_at',
        'placement_acknowledged_at',
        'reference_code',
        'created_at',
        'updated_at',
    ],

];