<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Schedules\CreateScheduleAction;
use App\Actions\Schedules\CreateScheduleBlockAction;
use App\Actions\Schedules\DeleteScheduleAction;
use App\Actions\Schedules\DeleteScheduleBlockAction;
use App\Exceptions\ScheduleCollisionException;
use App\Exceptions\ScheduleNotFoundException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class ScheduleController extends Controller
{
    /**
     * Crear franja recurrente de horario para el médico autenticado.
     */
    public function storeSchedule(Request $request, CreateScheduleAction $action): JsonResponse
    {
        $user = Auth::user();
        $doctorProfile = $user->doctorProfile;

        if (!$doctorProfile) {
            return response()->json([
                'error_code' => 'NOT_A_DOCTOR',
                'message'    => 'Solo los médicos pueden gestionar su agenda.'
            ], 403);
        }

        $validated = $request->validate([
            'day_of_week'   => ['required', 'integer', 'between:1,7'],
            'inicio'        => ['required', 'date_format:H:i:s'],
            'fin'           => ['required', 'date_format:H:i:s', 'after:inicio'],
            'slot_duration' => ['nullable', 'integer', 'min:10', 'max:120'],
        ]);

        try {
            $schedule = $action->handle([
                'doctor_profile_id' => $doctorProfile->id,
                'day_of_week'       => $validated['day_of_week'],
                'inicio'            => $validated['inicio'],
                'fin'               => $validated['fin'],
                'slot_duration'     => $validated['slot_duration'] ?? 30,
            ]);

            return response()->json(['data' => $schedule], 201);
        } catch (ScheduleCollisionException $e) {
            return response()->json([
                'error_code' => 'SCHEDULE_ALREADY_EXISTS',
                'message'    => $e->getMessage(),
            ], 409);
        }
    }

    /**
     * Eliminar franja recurrente.
     */
    public function destroySchedule(string $id, DeleteScheduleAction $action): JsonResponse
    {
        $user = Auth::user();
        $doctorProfile = $user->doctorProfile;

        if (!$doctorProfile) {
            return response()->json([
                'error_code' => 'NOT_A_DOCTOR',
                'message'    => 'Solo los médicos pueden gestionar su agenda.'
            ], 403);
        }

        try {
            $result = $action->handle($id, $doctorProfile->id, $user->id);
            return response()->json($result, 200);
        } catch (ScheduleNotFoundException $e) {
            return response()->json([
                'error_code' => 'SCHEDULE_NOT_FOUND',
                'message'    => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Crear bloqueo puntual de fecha/franja.
     */
    public function storeBlock(Request $request, CreateScheduleBlockAction $action): JsonResponse
    {
        $user = Auth::user();
        $doctorProfile = $user->doctorProfile;

        if (!$doctorProfile) {
            return response()->json([
                'error_code' => 'NOT_A_DOCTOR',
                'message'    => 'Solo los médicos pueden gestionar su agenda.'
            ], 403);
        }

        $validated = $request->validate([
            'blocked_date' => ['required', 'date_format:Y-m-d'],
            'inicio'       => ['required', 'date_format:H:i:s'],
            'fin'          => ['required', 'date_format:H:i:s', 'after:inicio'],
            'reason'       => ['required', 'string', 'max:255'],
        ]);

        try {
            $block = $action->handle([
                'doctor_profile_id' => $doctorProfile->id,
                'blocked_date'      => $validated['blocked_date'],
                'inicio'            => $validated['inicio'],
                'fin'               => $validated['fin'],
                'reason'            => $validated['reason'],
            ]);

            return response()->json(['data' => $block], 201);
        } catch (ScheduleCollisionException $e) {
            return response()->json([
                'error_code' => 'APPOINTMENT_OVERLAP_CONFLICT',
                'message'    => $e->getMessage(),
            ], 409);
        }
    }

    /**
     * Eliminar bloqueo puntual.
     */
    public function destroyBlock(string $id, DeleteScheduleBlockAction $action): JsonResponse
    {
        $user = Auth::user();
        $doctorProfile = $user->doctorProfile;

        if (!$doctorProfile) {
            return response()->json([
                'error_code' => 'NOT_A_DOCTOR',
                'message'    => 'Solo los médicos pueden gestionar su agenda.'
            ], 403);
        }

        try {
            $result = $action->handle($id, $doctorProfile->id);
            return response()->json($result, 200);
        } catch (ScheduleNotFoundException $e) {
            return response()->json([
                'error_code' => 'BLOCK_NOT_FOUND',
                'message'    => $e->getMessage(),
            ], 404);
        }
    }
}
