<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dispatcher
    |--------------------------------------------------------------------------
    |
    | "log"     – write the payload to the structured automation log only.
    | "http"    – POST the payload to AUTOMATION_DISPATCHER_URL.
    | "disabled"– persist the job but never deliver it.
    |
    */

    'dispatcher' => [
        'mode' => env('AUTOMATION_DISPATCHER_MODE', 'log'),
        'url' => env('AUTOMATION_DISPATCHER_URL'),
        'token' => env('AUTOMATION_DISPATCHER_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bounded retry policy
    |--------------------------------------------------------------------------
    |
    | delay = (base_seconds * 2 ^ (attempt - 1)) + random_int(0, jitter_seconds)
    |
    */

    'max_attempts' => (int) env('AUTOMATION_MAX_ATTEMPTS', 3),

    'retry' => [
        'base_seconds' => (int) env('AUTOMATION_RETRY_BASE_SECONDS', 10),
        'jitter_seconds' => (int) env('AUTOMATION_RETRY_JITTER_SECONDS', 3),
    ],

    'timeout_seconds' => (int) env('AUTOMATION_TIMEOUT_SECONDS', 5),

    /*
    |--------------------------------------------------------------------------
    | Controlled fault injection
    |--------------------------------------------------------------------------
    |
    | Test-only hook. Must stay false in production so failure paths can be
    | exercised deterministically without touching an external dispatcher.
    |
    */

    'allow_fault_injection' => filter_var(
        env('AUTOMATION_ALLOW_FAULT_INJECTION', false),
        FILTER_VALIDATE_BOOLEAN,
    ),

    'fault_modes' => [
        'none',
        'transient_once',
        'transient_always',
        'permanent_invalid_recipient',
    ],

    /*
    |--------------------------------------------------------------------------
    | Recipients
    |--------------------------------------------------------------------------
    */

    'fallback_recipient' => env('AUTOMATION_FALLBACK_RECIPIENT', 'coord.demo@uls-test.edu.ph'),

    /*
    |--------------------------------------------------------------------------
    | Sweep thresholds
    |--------------------------------------------------------------------------
    */

    'sweeps' => [
        'incomplete_after_hours' => (int) env('AUTOMATION_INCOMPLETE_AFTER_HOURS', 48),
        'stalled_after_days' => (int) env('AUTOMATION_STALLED_AFTER_DAYS', 5),
        'dispatch_limit' => (int) env('AUTOMATION_SWEEP_DISPATCH_LIMIT', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Structured log destination
    |--------------------------------------------------------------------------
    */

    'log_path' => env(
        'AUTOMATION_LOG_PATH',
        storage_path('logs/automation.log')
    ),

];