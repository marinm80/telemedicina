<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Schedules\CreateScheduleAction;
use App\Actions\Schedules\DeleteScheduleAction;
use App\Exceptions\ScheduleCollisionException;
use App\Exceptions\ScheduleNotFoundException;
use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class AdminScheduleController extends Controller
{
    /**
     * List ALL schedules for ALL doctors (grouped by doctor)
     */
    public function index(Request $request): JsonResponse
    {
        $schedules = DB::table('schedules')
            ->join('doctor_profiles', 'schedules.doctor_profile_id', '=', 'doctor_profiles.id')
            ->join('users', 'doctor_profiles.user_id', '=', 'users.id')
            ->whereNull('schedules.deleted_at')
            ->select(
                'schedules.id',
                'schedules.doctor_profile_id',
                'schedules.day_of_week',
                'schedules.franja',
                'schedules.slot_duration',
                'users.name as doctor_name',
                'users.last_name as doctor_last_name'
            )
            ->get();

        // Group by doctor
        $grouped = $schedules->groupBy('doctor_profile_id')->map(function ($items) {
            $first = $items->first();
            return [
                'doctor_profile_id' => $first->doctor_profile_id,
                'doctor_name'       => $first->doctor_name . ' ' . $first->doctor_last_name,
                'schedules'         => $items->map(function ($item) {
                    return [
                        'id'            => $item->id,
                        'day_of_week'   => $item->day_of_week,
                        'franja'        => $item->franja,
                        'slot_duration' => $item->slot_duration,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json(['data' => $grouped], 200);
    }

    /**
     * Create a schedule for ANY doctor
     */
    public function store(Request $request, CreateScheduleAction $action): JsonResponse
    {
        $validated = $request->validate([
            'doctor_profile_id' => ['required', 'uuid', 'exists:doctor_profiles,id'],
            'day_of_week'       => ['required', 'integer', 'between:1,7'],
            'inicio'            => ['required', 'date_format:H:i:s'],
            'fin'               => ['required', 'date_format:H:i:s', 'after:inicio'],
            'slot_duration'     => ['nullable', 'integer', 'min:10', 'max:120'],
        ]);

        try {
            $schedule = $action->handle([
                'doctor_profile_id' => $validated['doctor_profile_id'],
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
     * Delete a schedule for ANY doctor
     */
    public function destroy(string $id, DeleteScheduleAction $action): JsonResponse
    {
        $schedule = Schedule::find($id);

        if (!$schedule) {
            return response()->json([
                'error_code' => 'SCHEDULE_NOT_FOUND',
                'message'    => 'Franja horaria no encontrada.'
            ], 404);
        }

        try {
            // As requested, passing Auth::id() as userId
            $result = $action->handle($id, $schedule->doctor_profile_id, (string) Auth::id());
            return response()->json($result, 200);
        } catch (ScheduleNotFoundException $e) {
            return response()->json([
                'error_code' => 'SCHEDULE_NOT_FOUND',
                'message'    => $e->getMessage(),
            ], 404);
        }
    }
}
