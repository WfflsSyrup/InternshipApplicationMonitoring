<?php

use App\Http\Middleware\PayloadSizeAndSanitizationMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Support\ApiExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(null);

        // Week 15 — Module 15 / SA9: hardening runs before any controller.
        $middleware->api(prepend: [
            SecurityHeadersMiddleware::class,
            PayloadSizeAndSanitizationMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiExceptionRenderer::render($e);
        });
    })->create();