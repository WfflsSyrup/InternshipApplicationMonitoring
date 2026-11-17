<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Active key id
    |--------------------------------------------------------------------------
    |
    | Every new outbound envelope is sealed with this key id. Inbound
    | verification accepts any key id that still has material in the keyring,
    | which is what makes zero-downtime rotation possible.
    |
    */

    'active_kid' => env('CRYPTO_ACTIVE_KID', 'v2'),

    /*
    |--------------------------------------------------------------------------
    | Versioned keyring
    |--------------------------------------------------------------------------
    |
    | Keys are base64-encoded 256-bit values supplied through the environment
    | only. "enc" drives AES-256-GCM, "sig" drives HMAC-SHA256. Never commit
    | real key material: the repository ships placeholders only.
    |
    */

    'keys' => [
        'v1' => [
            'enc' => env('CRYPTO_KEY_V1_ENC'),
            'sig' => env('CRYPTO_KEY_V1_SIG'),
        ],
        'v2' => [
            'enc' => env('CRYPTO_KEY_V2_ENC'),
            'sig' => env('CRYPTO_KEY_V2_SIG'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Envelope primitives
    |--------------------------------------------------------------------------
    */

    'envelope_version' => env('CRYPTO_ENVELOPE_VERSION', '1.0'),

    'cipher' => 'aes-256-gcm',

    'iv_length_bytes' => 12,

    'tag_length_bytes' => 16,

    'signature_algorithm' => 'sha256',

    'signature_prefix' => 'sha256=',

    /*
    |--------------------------------------------------------------------------
    | Replay protection
    |--------------------------------------------------------------------------
    |
    | Timestamps outside +/- clock_skew_seconds are rejected as expired. Every
    | accepted nonce is stored until it passes nonce_ttl_seconds.
    |
    */

    'clock_skew_seconds' => (int) env('CRYPTO_CLOCK_SKEW_SECONDS', 300),

    'nonce_ttl_seconds' => (int) env('CRYPTO_NONCE_TTL_SECONDS', 900),

    /*
    |--------------------------------------------------------------------------
    | Partner callback route
    |--------------------------------------------------------------------------
    |
    | Used as the canonical request path when signing outbound simulation
    | headers and when verifying the public webhook.
    |
    */

    'callback_path' => env('CRYPTO_CALLBACK_PATH', '/api/v1/integrations/partner-callbacks/outcome'),

    /*
    |--------------------------------------------------------------------------
    | Controlled tamper simulation
    |--------------------------------------------------------------------------
    |
    | Must stay false in production. The coordinator-only verification lab is
    | refused with a 422 when disabled.
    |
    */

    'allow_tamper_simulation' => filter_var(
        env('CRYPTO_ALLOW_TAMPER_SIMULATION', false),
        FILTER_VALIDATE_BOOLEAN,
    ),

    /*
    |--------------------------------------------------------------------------
    | Structured audit destination
    |--------------------------------------------------------------------------
    */

    'log_path' => env('CRYPTO_LOG_PATH', storage_path('logs/crypto_audit.log')),

    /*
    |--------------------------------------------------------------------------
    | Rotated envelope retention
    |--------------------------------------------------------------------------
    |
    | Envelopes are re-sealed in place, so no history table is required. The
    | rotation command reports how many rows it moved to the active key.
    |
    */

    'rotation' => [
        'batch_size' => (int) env('CRYPTO_ROTATION_BATCH_SIZE', 200),
    ],

];