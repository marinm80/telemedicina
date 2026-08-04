<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 */

namespace App\Actions\Appointments;

use App\Models\Appointment;
use Illuminate\Support\Facades\DB;

final readonly class GetDoctorAvailabilityAction
{
    /**
     * Handle the doctor availability calculation.
     *
     * La conversión hora-de-pared → instante UTC se hace en PostgreSQL con
     * AT TIME ZONE, que usa la base IANA y resuelve DST correctamente:
     *
     *   (fecha::date + hora_local) AT TIME ZONE zona → timestamptz
     *
     * Las decisiones de DST están implementadas por DISTINCT + ORDER BY:
     *   - Hora inexistente (spring forward): AT TIME ZONE la mapea al mismo
     *     instante que la hora siguiente → DISTINCT la elimina → menos slots.
     *   - Hora ambigua (fall back): AT TIME ZONE elige una ocurrencia
     *     determinista → resultado creciente → sin duplicados.
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

        $timezone = $directory->timezone;

        // 2. Generar slots con aritmética de zona en PostgreSQL.
        //    La query:
        //    a) Expande cada schedule en slots de slot_duration minutos.
        //    b) Convierte cada hora de pared a timestamptz con AT TIME ZONE.
        //    c) DISTINCT elimina horas inexistentes (spring forward).
        //    d) ORDER BY garantiza secuencia estrictamente creciente.
        $slots = DB::select("
            WITH slot_instants AS (
                SELECT DISTINCT
                    slot_start AT TIME ZONE :tz  AS slot_start_utc,
                    (slot_start + (s.slot_duration || ' minutes')::interval) AT TIME ZONE :tz AS slot_end_utc
                FROM schedules s
                INNER JOIN doctor_profiles dp ON dp.id = s.doctor_profile_id
                CROSS JOIN LATERAL generate_series(
                    :date::date + lower(s.franja),
                    :date::date + upper(s.franja) - (s.slot_duration || ' minutes')::interval,
                    (s.slot_duration || ' minutes')::interval
                ) AS slot_start
                WHERE dp.user_id = :doctor_id
                  AND s.day_of_week = EXTRACT(DOW FROM :date::date)
            )
            SELECT
                slot_start_utc,
                slot_end_utc
            FROM slot_instants
            WHERE slot_start_utc < slot_end_utc
            ORDER BY slot_start_utc
        ", [
            'date' => $date,
            'tz' => $timezone,
            'doctor_id' => $doctorId,
        ]);

        // 3. Obtener bloqueos de agenda para la fecha via vista pública.
        //    v_schedule_blocks_availability excluye 'reason' (dato sensible).
        //    Conversión de hora de pared a timestamptz también en SQL.
        $blocks = DB::select("
            SELECT
                ((:date::date + lower(b.franja)) AT TIME ZONE :tz) AS block_start_utc,
                ((:date::date + upper(b.franja)) AT TIME ZONE :tz) AS block_end_utc
            FROM v_schedule_blocks_availability b
            WHERE b.doctor_profile_id = :dp_id
              AND b.blocked_date = :date
        ", [
            'date' => $date,
            'tz' => $timezone,
            'dp_id' => $directory->doctor_profile_id,
        ]);

        // 4. Obtener citas activas para la fecha (confirmadas o pendientes)
        $rangoDia = sprintf('[%s 00:00:00+00, %s 23:59:59+00)', $date, $date);
        $appointments = Appointment::query()
            ->where('doctor_id', $doctorId)
            ->where('status', '<>', 'cancelled')
            ->whereRaw('franja && ?::tstzrange', [$rangoDia])
            ->get();

        $result = [];

        foreach ($slots as $slot) {
            $slotStartUtc = $slot->slot_start_utc;
            $slotEndUtc = $slot->slot_end_utc;

            $isAvailable = true;

            // A. Verificar bloqueos de agenda
            foreach ($blocks as $block) {
                if ($slotStartUtc < $block->block_end_utc && $slotEndUtc > $block->block_start_utc) {
                    $isAvailable = false;
                    break;
                }
            }

            // B. Verificar citas ocupadas
            if ($isAvailable) {
                foreach ($appointments as $app) {
                    $appRaw = trim($app->franja, '()[]');
                    $appParts = explode(',', $appRaw);
                    if (count($appParts) === 2) {
                        $appStart = trim($appParts[0], '" ');
                        $appEnd = trim($appParts[1], '" ');

                        if ($slotStartUtc < $appEnd && $slotEndUtc > $appStart) {
                            $isAvailable = false;
                            break;
                        }
                    }
                }
            }

            // Formatear instantes como ISO 8601
            $startCarbon = \Illuminate\Support\Carbon::parse($slotStartUtc);
            $endCarbon = \Illuminate\Support\Carbon::parse($slotEndUtc);

            $result[] = [
                'start'       => $startCarbon->toIso8601String(),
                'end'         => $endCarbon->toIso8601String(),
                'local_start' => $startCarbon->setTimezone($timezone)->format('h:i A'),
                'local_end'   => $endCarbon->setTimezone($timezone)->format('h:i A'),
                'available'   => $isAvailable,
            ];
        }

        return [
            'doctor_id' => $doctorId,
            'date'      => $date,
            'timezone'  => $timezone,
            'slots'     => $result,
        ];
    }
}
