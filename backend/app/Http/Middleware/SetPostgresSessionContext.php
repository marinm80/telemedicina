<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class SetPostgresSessionContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();
            $userId = $user->id;
            $userRole = $user->role ?? 'patient';

            // Establecer variables de contexto en PostgreSQL
            DB::statement("SET app.current_user_id = '{$userId}'");
            DB::statement("SET app.current_user_role = '{$userRole}'");
        } else {
            // Valores por defecto para invitados (guest)
            DB::statement("SET app.current_user_id = ''");
            DB::statement("SET app.current_user_role = 'guest'");
        }

        return $next($request);
    }
}
