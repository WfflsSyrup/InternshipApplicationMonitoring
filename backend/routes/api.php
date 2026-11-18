<?php

use App\Http\Controllers\Api\V1\ApplicationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AutomationController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\PartnerReviewerController;
use App\Http\Controllers\Api\V1\SecureIntegrationController;
use App\Http\Controllers\Api\V1\SecurityAuditController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'show'])->name('health');

/*
|--------------------------------------------------------------------------
| Week 15 — Module 15 / SA9 authentication (tiered rate limiting)
|--------------------------------------------------------------------------
|
| auth-login: 5/min per e-mail + client address.
| api-standard: 60/min per authenticated user (or address when anonymous).
|
*/

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth-login');

    Route::middleware(['auth:sanctum', 'throttle:api-standard'])->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/revoke-all', [AuthController::class, 'revokeAll']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

/*
|--------------------------------------------------------------------------
| Week 14 — Module 14 / SA8 partner webhook (public)
|--------------------------------------------------------------------------
|
| Authenticated by the integration headers instead of a bearer token:
| X-Key-Id, X-Timestamp, X-Nonce and X-Signature-256, then AES-256-GCM.
| integration-webhook: 30/min per key id + client address.
|
*/

Route::post('/integrations/partner-callbacks/outcome', [SecureIntegrationController::class, 'partnerCallback'])
    ->middleware('throttle:integration-webhook');

Route::middleware(['auth:sanctum', 'throttle:api-standard'])->group(function (): void {
    Route::get('/applications', [ApplicationController::class, 'index']);
    Route::post('/applications', [ApplicationController::class, 'store']);
    Route::get('/applications/{application}', [ApplicationController::class, 'show']);
    Route::put('/applications/{application}', [ApplicationController::class, 'update']);
    Route::post('/applications/{application}/transition', [ApplicationController::class, 'transition']);

    Route::get('/partner-reviewers', [PartnerReviewerController::class, 'index']);

    Route::prefix('automation')->group(function (): void {
        Route::get('/jobs', [AutomationController::class, 'index']);
        Route::get('/summaries/weekly', [AutomationController::class, 'weeklySummary']);
        Route::post('/sweeps/run', [AutomationController::class, 'runSweep']);
        Route::post('/jobs/{job}/requeue', [AutomationController::class, 'requeue']);
    });

    Route::prefix('integrations')->group(function (): void {
        Route::get('/envelopes', [SecureIntegrationController::class, 'index']);
        Route::get('/keyring/status', [SecureIntegrationController::class, 'keyringStatus']);
        Route::post('/partner-packets/dispatch', [SecureIntegrationController::class, 'dispatchPartnerPacket']);
        Route::post('/verify-simulation', [SecureIntegrationController::class, 'verifySimulation']);
    });

    /*
    | Week 15 — Module 15 / SA9 security telemetry (coordinator only).
    */
    Route::prefix('security')->group(function (): void {
        Route::get('/audit-events', [SecurityAuditController::class, 'index']);
        Route::get('/posture', [SecurityAuditController::class, 'posture']);
    });
});