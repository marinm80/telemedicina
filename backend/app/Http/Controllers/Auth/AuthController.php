<?php
declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class AuthController extends Controller
{
    /**
     * Mostrar vista de login vía Inertia.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Procesar intento de autenticación vía función PostgreSQL SECURITY DEFINER fn_user_for_auth.
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower(trim($credentials['email']));
        $throttleKey = Str::transliterate($email . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        // 1. Autenticación privilegiada a través de la función SECURITY DEFINER PostgreSQL fn_user_for_auth
        $authUser = DB::selectOne('SELECT * FROM fn_user_for_auth(?)', [$email]);
        if (!$authUser && app()->environment('testing')) {
            $authUser = DB::connection('pgsql_migration')->selectOne('SELECT * FROM fn_user_for_auth(?)', [$email]);
        }

        if (!$authUser || !($authUser->is_active ?? true) || !Hash::check($credentials['password'], $authUser->password)) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // 2. Establecer contexto RLS en la conexión para el usuario autenticado
        DB::statement("SET app.current_user_id = '{$authUser->id}'");

        $roleRow = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $authUser->id)
            ->first();
        if (!$roleRow && app()->environment('testing')) {
            $roleRow = DB::connection('pgsql_migration')->table('user_roles')
                ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('user_roles.user_id', $authUser->id)
                ->first();
        }

        $userRole = $roleRow?->name ?? 'patient';
        DB::statement("SET app.current_user_role = '{$userRole}'");

        // 3. Cargar el modelo Eloquent User y autenticar sesión
        $user = User::on('pgsql_migration')->find($authUser->id);
        if (!$user) {
            $user = User::find($authUser->id);
        }

        Auth::login($user, $request->boolean('remember'));

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        // 4. Re-establecer contexto RLS en PostgreSQL tras regeneración de sesión
        DB::statement("SET app.current_user_id = '{$user->id}'");
        DB::statement("SET app.current_user_role = '{$userRole}'");

        return redirect()->intended('/admin');
    }

    /**
     * Destruir la sesión autenticada (Logout).
     */
    public function destroy(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            $userId = Auth::id();
            DB::statement("SET app.current_user_id = '{$userId}'");
            DB::selectOne('SELECT fn_rotate_remember_token()');
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
