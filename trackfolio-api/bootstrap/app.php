<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum middleware for SPA authentication
        $middleware->statefulApi();
        
        // Enable CSRF protection for API routes that use cookies (SPA mode)
        // The register endpoint will require CSRF token from Sanctum
        $middleware->validateCsrfTokens(except: [
            // Public endpoints that don't need CSRF (if any)
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Return JSON for all exceptions in API context
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $statusCode = 500;
                
                // Check for HTTP exceptions with status codes
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                    $statusCode = $e->getStatusCode();
                } elseif ($e instanceof \Illuminate\Http\Exceptions\HttpResponseException) {
                    return $e->getResponse();
                }
                
                return response()->json([
                    'message' => $e->getMessage(),
                    'error' => class_basename($e),
                ], $statusCode);
            }
        });
    })->create();
