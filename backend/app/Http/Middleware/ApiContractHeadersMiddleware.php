<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Week 17 — Module 17 / SA11: API Contract Headers Middleware.
 *
 * Attaches contract version and OpenAPI spec version headers to all
 * /api/v1/* responses for client-side contract awareness.
 */
class ApiContractHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->appliesTo($request)) {
            return $next($request);
        }

        /** @var Response $response */
        $response = $next($request);

        $contractVersion = config('contract.spec.version', '1.4.0');
        $openApiVersion = config('contract.spec.openapi_version', '3.1.0');

        $response->headers->set(config('contract.headers.contract_version', 'X-API-Contract-Version'), $contractVersion);
        $response->headers->set(config('contract.headers.openapi_spec', 'X-OpenAPI-Spec'), $openApiVersion);

        return $response;
    }

    private function appliesTo(Request $request): bool
    {
        return $request->is('api/*') || str_starts_with($request->getPathInfo(), '/api/');
    }
}