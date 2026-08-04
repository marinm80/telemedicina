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
        if (Auth::check() && ($user = Auth::user())) {
            $userId = $user->id;

            // 1. Establecer user_id en la conexión PostgreSQL para RLS
            DB::statement("SET app.current_user_id = '{$userId}'");

            // 2. Establecer el rol del usuario en PostgreSQL para RLS
            $role = $user->role ?? 'patient';
            DB::statement("SET app.current_user_role = '{$role}'");
        } else {
            DB::statement("SET app.current_user_id = ''");
            DB::statement("SET app.current_user_role = 'guest'");
        }

        return $next($request);
    }
}
