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

        $userForContext = \App\Models\User::on('pgsql_migration')->where('email', strtolower(trim($credentials['email'])))->first();
        if ($userForContext) {
            DB::statement("SET app.current_user_id = '{$userForContext->id}'");
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
        }

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
