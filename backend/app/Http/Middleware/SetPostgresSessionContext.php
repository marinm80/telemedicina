<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class SetPostgresSessionContext
{
    public function handle(Request $request, Closure $next): Response
    {
        // Auth::id() lee el id directo de la sesión sin disparar la
        // hidratación del modelo (evita la circularidad: retrieveById()
        // es una consulta protegida por RLS, y hasta este punto el GUC
        // todavía no está seteado). Recién con app.current_user_id fijado
        // es seguro llamar a Auth::user(): las policies de users/user_roles
        // tienen una cláusula "id = current_user_id" que no depende del rol,
        // así que la propia fila del usuario ya es visible en este punto.
        $userId = Auth::id();

        DB::statement("SET app.current_user_id = '" . ($userId ?? '') . "'");

        $role = $userId ? (Auth::user()?->role ?? 'patient') : 'guest';
        DB::statement("SET app.current_user_role = '{$role}'");

        return $next($request);
    }
}
