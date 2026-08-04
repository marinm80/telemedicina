<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserRole
{
    /**
     * Manejar la verificación de roles permitidos para las rutas de la aplicación.
     *
     * @param  array<string>  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, $roles, true)) {
            abort(403, 'No posees el rol necesario para acceder a esta sección.');
        }

        return $next($request);
    }
}
