<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Http\ApiError;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('v1/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('v1/*')) {
                return ApiError::make(401, 'UNAUTHENTICATED', 'AUTHENTICATION_REQUIRED', 'Authentication is required.');
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            if ($request->is('v1/*')) {
                return ApiError::make(403, 'PERMISSION_DENIED', 'INSUFFICIENT_PERMISSIONS', 'You do not have permission to access this resource.');
            }
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if ($request->is('v1/*')) {
                return ApiError::make(404, 'NOT_FOUND', 'RESOURCE_NOT_FOUND', 'The requested resource was not found.');
            }
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->is('v1/*')) {
                return ApiError::make(400, 'INVALID_ARGUMENT', 'VALIDATION_FAILED', 'The request has invalid fields.', [[
                    '@type' => 'type.googleapis.com/google.rpc.BadRequest',
                    'fieldViolations' => collect($exception->errors())->flatMap(
                        fn (array $messages, string $field) => collect($messages)->map(
                            fn (string $message) => ['field' => $field, 'description' => $message],
                        ),
                    )->values()->all(),
                ]]);
            }
        });
    })->create();
