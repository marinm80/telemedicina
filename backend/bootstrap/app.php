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
        $middleware->web(append: [
            \App\Http\Middleware\SetPostgresSessionContext::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // SetPostgresSessionContext necesita correr DESPUÉS de StartSession
        // (para que la sesión ya esté cargada) pero ANTES de Authenticate
        // (alias 'auth'): Authenticate rehidrata al usuario desde la sesión
        // vía retrieveById(), una consulta protegida por RLS. Sin el GUC
        // app.current_user_id seteado antes de esa consulta, RLS la bloquea
        // y el usuario queda "deslogueado" en cada request posterior al
        // login. Por defecto Laravel ordena el grupo 'web' con Authenticate
        // (vía el contrato AuthenticatesRequests) antes que SubstituteBindings,
        // y este middleware -al no estar en la lista de prioridad- terminaba
        // corriendo después de ambos sin importar dónde se lo registrara.
        $middleware->appendToPriorityList(
            after: \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            append: \App\Http\Middleware\SetPostgresSessionContext::class,
        );

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
