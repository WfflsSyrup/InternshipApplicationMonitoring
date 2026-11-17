<?php

namespace App\Support;

use App\Exceptions\InvalidStateTransitionException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\MaintenanceModeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiExceptionRenderer
{
    use ApiResponse;

    public static function render(Throwable $e): \Illuminate\Http\JsonResponse
    {
        [$status, $message, $errors] = self::resolve($e);

        if ($status >= 500) {
            Log::error('Unhandled API exception: '.($e->getMessage() ?: 'no message'), [
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        return self::error($message, $status, $errors);
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
}