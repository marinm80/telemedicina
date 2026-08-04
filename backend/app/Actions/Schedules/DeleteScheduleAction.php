<?php
declare(strict_types=1);

namespace App\Actions\Schedules;

use App\Exceptions\ScheduleNotFoundException;
use App\Models\Appointment;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;

final readonly class DeleteScheduleAction
{
    /**
     * Borrar una franja recurrente de horario y retornar las citas futuras que requieren atención.
     *
     * @param  string  $scheduleId
     * @param  string  $doctorProfileId
     * @param  string  $doctorUserId
     * @return array{deleted: bool, affected_appointments_count: int, affected_appointments: array<int, mixed>}
     *
     * @throws \App\Exceptions\ScheduleNotFoundException
     */
    public function handle(string $scheduleId, string $doctorProfileId, string $doctorUserId): array
    {
        $schedule = Schedule::where('id', $scheduleId)
            ->where('doctor_profile_id', $doctorProfileId)
            ->first();

        if (!$schedule) {
            throw new ScheduleNotFoundException('Franja horaria no encontrada.');
        }

        // Buscar citas futuras confirmadas o pendientes asociadas al médico
        $affected = DB::select("
            SELECT a.id, a.patient_id, a.franja, a.status
            FROM appointments a
            WHERE a.doctor_id = ?::uuid
              AND a.status IN ('pending', 'confirmed')
              AND lower(a.franja) >= NOW()
              AND EXTRACT(ISODOW FROM lower(a.franja)) = ?
        ", [$doctorUserId, $schedule->day_of_week]);

        $schedule->delete();

        return [
            'deleted'                     => true,
            'affected_appointments_count' => count($affected),
            'affected_appointments'       => $affected,
        ];
    }
}
