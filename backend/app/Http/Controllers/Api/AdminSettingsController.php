<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class AdminSettingsController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        $db = DB::connection('pgsql_admin');
        
        $users = $db->table('users')
            ->leftJoin('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->leftJoin('roles', 'user_roles.role_id', '=', 'roles.id')
            ->select(
                'users.id',
                'users.name',
                'users.last_name',
                'users.email',
                'users.timezone',
                'users.created_at',
                'roles.name as role'
            )
            ->orderBy('roles.name')
            ->orderBy('users.name')
            ->get();

        return response()->json(['data' => $users], 200);
    }

    public function changePassword(Request $request, string $userId): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $db = DB::connection('pgsql_admin');
        $user = $db->table('users')->where('id', $userId)->first();

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado.'], 404);
        }

        $db->table('users')
            ->where('id', $userId)
            ->update([
                'password'   => Hash::make($validated['password']),
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'Contraseña actualizada exitosamente.'], 200);
    }

    public function updateUserRole(Request $request, string $userId): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:admin,doctor,patient,agent'],
        ]);

        $db = DB::connection('pgsql_admin');
        $role = $db->table('roles')->where('name', $validated['role'])->first();
        
        if (!$role) {
            return response()->json(['message' => 'Rol no válido.'], 422);
        }

        // Remove existing roles
        $db->table('user_roles')->where('user_id', $userId)->delete();
        
        // Assign new role
        $db->table('user_roles')->insert([
            'user_id' => $userId,
            'role_id' => $role->id,
        ]);

        return response()->json(['message' => 'Rol actualizado.'], 200);
    }
}
