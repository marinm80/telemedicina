<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
     * Procesar intento de autenticación.
     *
     * Delegación limpia: el controlador valida las entradas HTTP y llama a
     * Auth::attempt(). La verificación del hash y la consulta a PostgreSQL
     * (fn_user_for_auth) suceden dentro de SecureEloquentUserProvider.
     *
     * Incluye RateLimiter (máximo 5 intentos por minuto por email+IP).
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($credentials['email']) . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $user = Auth::user();
        if ($user) {
            DB::statement("SET app.current_user_id = '{$user->id}'");
            if (in_array($user->role, ['admin', 'doctor', 'agent'], true)) {
                return redirect()->intended('/admin');
            }
        }

        return redirect()->intended('/');
    }

    /**
     * Destruir la sesión autenticada (Logout).
     *
     * Al cerrar sesión, si hay usuario autenticado, invocamos
     * fn_rotate_remember_token() en PostgreSQL para invalidar
     * cualquier cookie de persistencia previa.
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
