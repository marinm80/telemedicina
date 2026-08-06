<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Actions\Schedules\CreateScheduleAction;
use App\Actions\Schedules\DeleteScheduleAction;
use App\Exceptions\ScheduleCollisionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Doctor self-service schedule management.
 * Doctors can only manage THEIR OWN schedules.
 */
final class DoctorScheduleController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $db = DB::connection('pgsql_admin');

        $profile = $db->table('doctor_profiles')
            ->where('user_id', $user->id)
            ->first();

        if (!$profile) {
            return response()->json(['data' => []], 200);
        }

        $schedules = $db->table('schedules')
            ->where('doctor_profile_id', $profile->id)
            ->whereNull('deleted_at')
            ->orderBy('day_of_week')
            ->get()
            ->map(function ($s) {
                // Parse franja range [HH:MM:SS,HH:MM:SS)
                $franja = trim($s->franja, '[]()');
                $parts = explode(',', $franja);
                return [
                    'id'             => $s->id,
                    'day_of_week'    => $s->day_of_week,
                    'franja_inicio'  => trim($parts[0] ?? ''),
                    'franja_fin'     => trim($parts[1] ?? ''),
                    'slot_duration'  => $s->slot_duration,
                ];
            });

        return response()->json(['data' => $schedules], 200);
    }

    public function store(Request $request, CreateScheduleAction $action): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $db = DB::connection('pgsql_admin');
        $profile = $db->table('doctor_profiles')
            ->where('user_id', $user->id)
            ->first();

        if (!$profile) {
            return response()->json(['message' => 'No tienes perfil de médico.'], 403);
        }

        $validated = $request->validate([
            'day_of_week'   => ['required', 'integer', 'between:0,6'],
            'inicio'        => ['required', 'date_format:H:i:s'],
            'fin'           => ['required', 'date_format:H:i:s', 'after:inicio'],
            'slot_duration' => ['nullable', 'integer', 'between:10,120'],
        ]);

        try {
            $schedule = $action->handle([
                'doctor_profile_id' => $profile->id,
                'day_of_week'       => $validated['day_of_week'],
                'inicio'            => $validated['inicio'],
                'fin'               => $validated['fin'],
                'slot_duration'     => $validated['slot_duration'] ?? 30,
            ]);

            return response()->json(['data' => $schedule, 'message' => 'Horario creado.'], 201);
        } catch (ScheduleCollisionException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    public function destroy(string $id, DeleteScheduleAction $action): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $db = DB::connection('pgsql_admin');
        $profile = $db->table('doctor_profiles')
            ->where('user_id', $user->id)
            ->first();

        if (!$profile) {
            return response()->json(['message' => 'No tienes perfil de médico.'], 403);
        }

        // Verify the schedule belongs to this doctor
        $schedule = $db->table('schedules')
            ->where('id', $id)
            ->where('doctor_profile_id', $profile->id)
            ->whereNull('deleted_at')
            ->first();

        if (!$schedule) {
            return response()->json(['message' => 'Horario no encontrado.'], 404);
        }

        $action->handle($id, $profile->id, $user->id);

        return response()->json(['message' => 'Horario eliminado.'], 200);
    }
}
