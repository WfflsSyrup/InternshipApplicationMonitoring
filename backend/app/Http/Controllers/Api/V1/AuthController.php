<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\SecurityAuditEvent;
use App\Models\User;
use App\Services\SecurityAuditService;
use App\Support\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            $this->auditLoginFailure($user);

            // Uniform, anti-enumeration response: unknown e-mail and wrong
            // password are indistinguishable to the caller.
            throw new HttpResponseException(self::error('Invalid credentials provided.', 401));
        }

        $token = $user->createToken('flutter-web')->plainTextToken;

        return self::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 'Signed in successfully.');
    }

    public function logout(): JsonResponse
    {
        $token = request()->user()?->currentAccessToken();
        $token?->delete();

        return self::success(null, 'Signed out successfully.');
    }

    /**
     * Week 15 — Module 15 / SA9: global session revocation. Every personal
     * access token of the caller is deleted so a stolen bearer token dies.
     */
    public function revokeAll(): JsonResponse
    {
        $user = request()->user();

        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        $revoked = (int) $user->tokens()->delete();

        try {
            app(SecurityAuditService::class)->record(
                SecurityAuditEvent::EVENT_SESSION_REVOKED,
                200,
                ['revoked_tokens' => $revoked],
                $user,
                request(),
            );
        } catch (\Throwable) {
            // Revocation must succeed even when telemetry is unavailable.
        }

        return self::success(['revoked_tokens' => $revoked], 'All sessions have been revoked.');
    }

    public function me(): JsonResponse
    {
        $user = request()->user();

        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        return self::success(new UserResource($user), 'Session is active.');
    }

    private function auditLoginFailure(?User $user): void
    {
        try {
            app(SecurityAuditService::class)->record(
                SecurityAuditEvent::EVENT_LOGIN_FAILED,
                401,
                [
                    'reason' => 'invalid_credentials',
                    'actor_hint' => $user === null ? 'unknown_account' : 'known_account',
                ],
                $user,
                request(),
            );
        } catch (\Throwable) {
            // Never leak authentication internals through telemetry failures.
        }
    }
}