<?php
declare(strict_types=1);
/**
 * ====================================================================
 * EJEMPLAR CANÓNICO — Controlador (Laravel 11)
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * QUÉ FIJA ESTE EJEMPLAR
 * El controlador SOLO traduce HTTP. Reglas que no puede violar:
 *   1. No consulta la base de datos directamente.
 *   2. Ningún método pasa de ~15 líneas.
 *   3. No contiene reglas de negocio: si hay un `if` con una regla, va a la Action.
 *
 * Cada método recibe su Form Request (que ya validó y autorizó) e invoca una
 * Action. Nada más.
 */

namespace App\Http\Controllers;

use App\Actions\Users\CreateUserAction;
use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        // 'with' evita el N+1: la carga anticipada es explícita y visible.
        $users = User::query()
            ->with('team')
            ->when($request->string('search')->isNotEmpty(),
                fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->latest()
            ->paginate(perPage: 20);

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request, CreateUserAction $accion): JsonResponse
    {
        $user = $accion->handle($request->validated());

        return UserResource::make($user)
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        // Route binding + Policy. El 403 y el 404 los resuelve el framework.
        $this->authorize('view', $user);

        return UserResource::make($user);
    }
}
