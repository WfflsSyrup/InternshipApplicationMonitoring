<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Week 15 — Module 15 / SA9.
 *
 * Attaches the OWASP recommended hardening headers to every /api/v1/*
 * response so browsers cannot MIME-sniff, frame, or leak referrer data.
 */
class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $this->appliesTo($request)) {
            return $response;
        }

        /** @var array<string, string> $headers */
        $headers = (array) config('security.headers', []);

        foreach ($headers as $name => $value) {
            if (! is_string($name) || $name === '' || ! is_string($value)) {
                continue;
            }

            $response->headers->set($name, $value);
        }

        return $response;
    }

    private function appliesTo(Request $request): bool
    {
        return $request->is('api/*') || str_starts_with($request->getPathInfo(), '/api/');
    }
}