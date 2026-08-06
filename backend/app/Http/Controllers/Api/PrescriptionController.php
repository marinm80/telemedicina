<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PrescriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) return response()->json(['message' => 'No autenticado.'], 401);

        $db = DB::connection('pgsql_admin');
        
        $query = $db->table('prescriptions')->whereNull('deleted_at');

        if ($user->role === 'patient') {
            $query->where('patient_id', $user->id);
        }

        $prescriptions = $query->get();
        foreach ($prescriptions as $p) {
            $p->medicamentos = json_decode($p->medicamentos ?? '[]', true);
        }

        return response()->json(['data' => $prescriptions], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) return response()->json(['message' => 'No autenticado.'], 401);

        if ($user->role !== 'admin' && $user->role !== 'doctor') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $validated = $request->validate([
            'consultation_id' => ['nullable', 'string', 'uuid'],
            'patient_id' => ['required', 'string', 'uuid'],
            'fecha' => ['nullable', 'date'],
            'medicamentos' => ['required', 'array'],
            'medicamentos.*.nombre' => ['required', 'string'],
            'medicamentos.*.dosis' => ['nullable', 'string'],
            'medicamentos.*.frecuencia' => ['nullable', 'string'],
            'medicamentos.*.duracion' => ['nullable', 'string'],
            'indicaciones' => ['nullable', 'string'],
            'notas' => ['nullable', 'string'],
        ]);

        $db = DB::connection('pgsql_admin');

        $data = [
            'id' => Str::uuid()->toString(),
            'consultation_id' => $validated['consultation_id'] ?? null,
            'doctor_id' => $user->id,
            'patient_id' => $validated['patient_id'],
            'fecha' => $validated['fecha'] ?? now()->toDateString(),
            'medicamentos' => json_encode($validated['medicamentos']),
            'indicaciones' => $validated['indicaciones'] ?? null,
            'notas' => $validated['notas'] ?? null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $db->table('prescriptions')->insert($data);

        return response()->json(['message' => 'Receta creada exitosamente.', 'data' => $data], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        if (!$user) return response()->json(['message' => 'No autenticado.'], 401);

        $db = DB::connection('pgsql_admin');
        
        $prescription = $db->table('prescriptions')->where('id', $id)->whereNull('deleted_at')->first();
        if (!$prescription) return response()->json(['message' => 'Receta no encontrada.'], 404);

        if ($user->role !== 'admin' && $prescription->doctor_id !== $user->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $validated = $request->validate([
            'medicamentos' => ['sometimes', 'array'],
            'medicamentos.*.nombre' => ['required', 'string'],
            'medicamentos.*.dosis' => ['nullable', 'string'],
            'medicamentos.*.frecuencia' => ['nullable', 'string'],
            'medicamentos.*.duracion' => ['nullable', 'string'],
            'indicaciones' => ['nullable', 'string'],
            'notas' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,cancelled'],
        ]);

        $updateData = ['updated_at' => now()];
        if (isset($validated['medicamentos'])) $updateData['medicamentos'] = json_encode($validated['medicamentos']);
        if (array_key_exists('indicaciones', $validated)) $updateData['indicaciones'] = $validated['indicaciones'];
        if (array_key_exists('notas', $validated)) $updateData['notas'] = $validated['notas'];
        if (isset($validated['status'])) $updateData['status'] = $validated['status'];

        $db->table('prescriptions')->where('id', $id)->update($updateData);

        return response()->json(['message' => 'Receta actualizada exitosamente.'], 200);
    }

    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();
        if (!$user) return response()->json(['message' => 'No autenticado.'], 401);

        $db = DB::connection('pgsql_admin');
        
        $prescription = $db->table('prescriptions')->where('id', $id)->whereNull('deleted_at')->first();
        if (!$prescription) return response()->json(['message' => 'Receta no encontrada.'], 404);

        if ($user->role !== 'admin' && $prescription->doctor_id !== $user->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $db->table('prescriptions')->where('id', $id)->update(['deleted_at' => now()]);

        return response()->json(['message' => 'Receta eliminada exitosamente.'], 200);
    }
}
