<?php
declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterPatientAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class RegisterController extends Controller
{
    /**
     * Mostrar vista de registro de cuenta para pacientes vía Inertia.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Procesar solicitud de registro desde el formulario Inertia.
     */
    public function store(Request $request, RegisterPatientAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:150'],
            'last_name'             => ['required', 'string', 'max:150'],
            'email'                 => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'timezone'              => ['nullable', 'string', 'max:50'],
        ]);

        $user = $action->handle($validated);

        // Establecer el contexto RLS de usuario ANTES de iniciar sesión
        DB::statement("SET app.current_user_id = '{$user->id}'");

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/admin');
    }

    /**
     * Procesar solicitud de registro vía API JSON.
     */
    public function storeApi(Request $request, RegisterPatientAction $action): JsonResponse
    {
        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:150'],
            'last_name'             => ['required', 'string', 'max:150'],
            'email'                 => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'timezone'              => ['nullable', 'string', 'max:50'],
        ]);

        $user = $action->handle($validated);

        DB::statement("SET app.current_user_id = '{$user->id}'");
        Auth::login($user);

        return response()->json([
            'message' => 'Cuenta registrada exitosamente.',
            'user'    => [
                'id'        => $user->id,
                'name'      => $user->name,
                'last_name' => $user->last_name,
                'email'     => $user->email,
                'role'      => 'patient',
            ],
        ], 201);
    }
}
