<?php
declare(strict_types=1);

namespace App\Bootstrap;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            \App\Http\Middleware\SetPostgresSessionContext::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (\App\Exceptions\IdempotencyCollisionException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'IDEMPOTENCY_KEY_REUSED_WITH_DIFFERENT_PAYLOAD'
            ], 400);
        });

        $exceptions->render(function (\App\Exceptions\SlotCollisionException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'SLOT_ALREADY_BOOKED',
                'errors' => [
                    'franja' => ['La franja horaria coincide con una cita activa del médico.']
                ]
            ], 409);
        });

        $exceptions->render(function (\App\Exceptions\DoctorNotFoundException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'RESOURCE_NOT_FOUND'
            ], 404);
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'RESOURCE_NOT_FOUND'
            ], 404);
        });
    })->create();
