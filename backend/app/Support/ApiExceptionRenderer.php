<?php

namespace App\Support;

use App\Exceptions\Crypto\CryptoConfigurationException;
use App\Exceptions\Crypto\CryptoVerificationException;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\SecurityAuditEvent;
use App\Services\SecurityAuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\MaintenanceModeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiExceptionRenderer
{
    use ApiResponse;

    public static function render(Throwable $e): \Illuminate\Http\JsonResponse
    {
        // Preserve headers (Retry-After, X-RateLimit-*) produced by a
        // response-returning middleware instead of rebuilding the envelope.
        if ($e instanceof HttpResponseException) {
            $existing = $e->getResponse();

            if ($existing instanceof \Illuminate\Http\JsonResponse) {
                return $existing;
            }
        }

        [$status, $message, $errors] = self::resolve($e);

        if ($status >= 500) {
            Log::error('Unhandled API exception: '.($e->getMessage() ?: 'no message'), [
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        $response = self::error($message, $status, $errors);

        if ($e instanceof ThrottleRequestsException) {
            $retryAfter = (int) ($e->getHeaders()['Retry-After'] ?? 60);
            $response->headers->set('Retry-After', (string) $retryAfter);
            self::auditRateLimit($retryAfter);
        }

        if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
            self::auditAuthorizationFailure($e);
        }

        return $response;
    }

    /**
     * @return array{0: int, 1: string, 2: array<string, array<int, string>>|null}
     */
    private static function resolve(Throwable $e): array
    {
        if ($e instanceof ValidationException) {
            return [422, 'The given data was invalid.', $e->errors()];
        }

        if ($e instanceof InvalidStateTransitionException) {
            return [422, $e->getMessage(), ['status' => [$e->getMessage()]]];
        }

        if ($e instanceof CryptoVerificationException) {
            return [
                $e->status(),
                $e->getMessage(),
                [$e->reasonCode() => [$e->getMessage()]],
            ];
        }

        if ($e instanceof CryptoConfigurationException) {
            return [
                503,
                'Cryptographic keyring is not configured for this environment.',
                ['crypto' => [$e->getMessage()]],
            ];
        }

        if ($e instanceof AuthenticationException) {
            return [401, 'Unauthenticated.', null];
        }

        if ($e instanceof AuthorizationException) {
            return [403, 'This action is unauthorized.', null];
        }

        if ($e instanceof ModelNotFoundException) {
            return [404, 'Resource not found.', null];
        }

        if ($e instanceof NotFoundHttpException) {
            return [404, 'Resource not found.', null];
        }

        if ($e instanceof ThrottleRequestsException) {
            return [429, 'Too many requests. Please slow down and retry.', null];
        }

        if ($e instanceof MaintenanceModeException) {
            return [503, 'Service temporarily unavailable.', null];
        }

        if ($e instanceof HttpResponseException) {
            $response = $e->getResponse();

            if ($response instanceof \Illuminate\Http\JsonResponse) {
                return [$response->getStatusCode(), (string) $response->getData()->message, null];
            }

            return [500, 'Server error.', null];
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $message = $status < 500 && $e->getMessage() !== ''
                ? $e->getMessage()
                : 'Server error.';

            return [$status, $message, null];
        }

        return [500, 'Server error.', null];
    }

    private static function auditRateLimit(int $retryAfter): void
    {
        try {
            app(SecurityAuditService::class)->record(
                SecurityAuditEvent::EVENT_RATE_LIMIT_EXCEEDED,
                429,
                ['retry_after' => $retryAfter, 'method' => request()->getMethod()],
                request()->user(),
                request(),
            );
        } catch (Throwable) {
            // Never let telemetry change the HTTP outcome.
        }
    }

    /**
     * 403s are split by scope: an object-level denial (a route parameter is
     * present) is BOLA, a function-level denial is BFLA.
     */
    private static function auditAuthorizationFailure(Throwable $e): void
    {
        try {
            $request = request();
            $route = $request?->route();
            $parameterNames = $route === null ? [] : array_keys($route->parameters());
            $isObjectLevel = $parameterNames !== [];

            app(SecurityAuditService::class)->record(
                $isObjectLevel
                    ? SecurityAuditEvent::EVENT_BOLA_BLOCKED
                    : SecurityAuditEvent::EVENT_BFLA_BLOCKED,
                403,
                [
                    'reason' => 'authorization_denied',
                    'route' => $route?->getName() ?? $request?->getPathInfo(),
                    'scope' => $isObjectLevel ? 'object' : 'function',
                    'parameters' => $parameterNames,
                    'policy' => $e->getMessage() ?: null,
                ],
                $request?->user(),
                $request,
            );
        } catch (Throwable $e) {
            Log::error('Security BOLA/BFLA audit failed: '.$e->getMessage());
        }
    }
}