<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 */

namespace App\Actions\Appointments;

use App\Models\Schedule;
use App\Models\Appointment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class GetDoctorAvailabilityAction
{
    /**
     * Handle the doctor availability calculation.
     *
     * @return array{doctor_id: string, date: string, timezone: string, slots: array}
     */
    public function handle(string $doctorId, string $date): array
    {
        // 1. Validar existencia del médico y su estado via vista pública.
        //    v_doctor_directory filtra status='approved' AND is_active=true
        //    con security_barrier=true. Un paciente no necesita SELECT en users
        //    ni doctor_profiles para buscar médicos — la vista ES la frontera.
        $directory = DB::table('v_doctor_directory')
            ->where('user_id', $doctorId)
            ->first();

        if (!$directory) {
            // No revelar si el médico existe pero no está aprobado — eso es
            // fuga de información. Si no está en el directorio público, 404.
            throw new \App\Exceptions\DoctorNotFoundException('Médico no encontrado.');
        }

        $carbonDate = Carbon::parse($date);
        $dayOfWeek = $carbonDate->dayOfWeek; // 0 (Domingo) a 6 (Sábado)

        // 2. Obtener horarios recurrentes del médico para ese día
        $schedules = Schedule::query()
            ->where('doctor_profile_id', $directory->doctor_profile_id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->get();

        // 3. Obtener bloqueos de agenda para la fecha via vista pública.
        //    v_schedule_blocks_availability excluye 'reason' (dato sensible:
        //    motivo del bloqueo del médico) y filtra doctor aprobado.
        //    Usar la vista en vez de GRANT de columna evita que el próximo
        //    ScheduleBlock::all() explote en ejecución.
        $blocks = DB::table('v_schedule_blocks_availability')
            ->where('doctor_profile_id', $directory->doctor_profile_id)
            ->where('blocked_date', $date)
            ->get();

        // 4. Obtener citas activas para la fecha (confirmadas o pendientes)
        // PostgreSQL almacena tstzrange, así que comparamos si se intersectan con el día
        $rangoDia = sprintf('[%s 00:00:00+00, %s 23:59:59+00)', $date, $date);
        $appointments = Appointment::query()
            ->where('doctor_id', $doctorId)
            ->where('status', '<>', 'cancelled')
            ->whereRaw('franja && ?::tstzrange', [$rangoDia])
            ->get();

        $slots = [];

        foreach ($schedules as $schedule) {
            // Parsear el timerange de PostgreSQL
            $franjaRaw = trim($schedule->franja, '()[]');
            $parts = explode(',', $franjaRaw);
            if (count($parts) !== 2) {
                continue;
            }

            $inicioTime = Carbon::createFromFormat('H:i:s', trim($parts[0], '" '));
            $finTime = Carbon::createFromFormat('H:i:s', trim($parts[1], '" '));
            $duracion = $schedule->slot_duration; // Ej: 30 minutos

            $currentSlotInicio = Carbon::parse($date . ' ' . $inicioTime->toTimeString(), 'UTC');
            $limitSlotFin = Carbon::parse($date . ' ' . $finTime->toTimeString(), 'UTC');

            while ($currentSlotInicio->lt($limitSlotFin)) {
                $currentSlotFin = $currentSlotInicio->copy()->addMinutes($duracion);
                
                $slotStartStr = $currentSlotInicio->toIso8601String();
                $slotEndStr = $currentSlotFin->toIso8601String();

                $isAvailable = true;

                // A. Verificar bloqueos de agenda
                foreach ($blocks as $block) {
                    $blockRaw = trim($block->franja, '()[]');
                    $blockParts = explode(',', $blockRaw);
                    if (count($blockParts) === 2) {
                        $blockStart = Carbon::parse($date . ' ' . trim($blockParts[0], '" '), 'UTC');
                        $blockEnd = Carbon::parse($date . ' ' . trim($blockParts[1], '" '), 'UTC');

                        if ($currentSlotInicio->lt($blockEnd) && $currentSlotFin->gt($blockStart)) {
                            $isAvailable = false;
                            break;
                        }
                    }
                }

                // B. Verificar citas ocupadas
                if ($isAvailable) {
                    foreach ($appointments as $app) {
                        $appRaw = trim($app->franja, '()[]');
                        $appParts = explode(',', $appRaw);
                        if (count($appParts) === 2) {
                            $appStart = Carbon::parse(trim($appParts[0], '" '));
                            $appEnd = Carbon::parse(trim($appParts[1], '" '));

                            if ($currentSlotInicio->lt($appEnd) && $currentSlotFin->gt($appStart)) {
                                $isAvailable = false;
                                break;
                            }
                        }
                    }
                }

                $slots[] = [
                    'start'       => $slotStartStr,
                    'end'         => $slotEndStr,
                    'local_start' => $currentSlotInicio->setTimezone($directory->timezone)->format('h:i A'),
                    'local_end'   => $currentSlotFin->setTimezone($directory->timezone)->format('h:i A'),
                    'available'   => $isAvailable,
                ];

                $currentSlotInicio = $currentSlotFin;
            }
        }

        return [
            'doctor_id' => $doctorId,
            'date'      => $date,
            'timezone'  => $directory->timezone,
            'slots'     => $slots,
        ];
    }
}
