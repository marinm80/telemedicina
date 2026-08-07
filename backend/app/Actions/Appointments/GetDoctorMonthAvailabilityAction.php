<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 */

namespace App\Actions\Appointments;

use Illuminate\Support\Facades\DB;

final readonly class GetDoctorMonthAvailabilityAction
{
    /**
     * Estado por día para un mes completo, con el mismo criterio que
     * GetDoctorAvailabilityAction usa por slot: un día está "full" si
     * citas y/o bloqueos de agenda cubren el 100% de sus slots — sin
     * distinguir la causa, tal como lo pidió el negocio (un día bloqueado
     * a mano se ve igual que un día 100% reservado).
     *
     * Una sola consulta agregada en vez de N llamadas día-por-día a
     * GetDoctorAvailabilityAction — mismo cálculo DST-seguro, extendido
     * con generate_series sobre el rango de fechas.
     *
     * @return array{doctor_id: string, month: string, timezone: string, days: array<string, string>}
     */
    public function handle(string $doctorId, string $month): array
    {
        $directory = DB::table('v_doctor_directory')
            ->where('user_id', $doctorId)
            ->first();

        if (!$directory) {
            throw new \App\Exceptions\DoctorNotFoundException('Médico no encontrado.');
        }

        $timezone = $directory->timezone;
        $monthStart = "{$month}-01";

        $rows = DB::select("
            WITH days AS (
                SELECT generate_series(
                    :month_start::date,
                    (:month_start::date + interval '1 month' - interval '1 day')::date,
                    '1 day'
                )::date AS day
            ),
            slot_instants AS (
                SELECT DISTINCT
                    d.day,
                    slot_start AT TIME ZONE :tz AS slot_start_utc,
                    (slot_start + (s.slot_duration || ' minutes')::interval) AT TIME ZONE :tz AS slot_end_utc
                FROM days d
                INNER JOIN schedules s ON s.day_of_week = EXTRACT(DOW FROM d.day)
                INNER JOIN doctor_profiles dp ON dp.id = s.doctor_profile_id
                CROSS JOIN LATERAL generate_series(
                    d.day + lower(s.franja),
                    d.day + upper(s.franja) - (s.slot_duration || ' minutes')::interval,
                    (s.slot_duration || ' minutes')::interval
                ) AS slot_start
                WHERE dp.user_id = :doctor_id
            ),
            slots AS (
                SELECT day, slot_start_utc, slot_end_utc
                FROM slot_instants
                WHERE slot_start_utc < slot_end_utc
            )
            SELECT
                s.day,
                COUNT(*) AS total_slots,
                COUNT(*) FILTER (
                    WHERE NOT EXISTS (
                        SELECT 1 FROM v_schedule_blocks_availability b
                        WHERE b.doctor_profile_id = :dp_id
                          AND b.blocked_date = s.day
                          AND ((s.day + lower(b.franja)) AT TIME ZONE :tz) < s.slot_end_utc
                          AND ((s.day + upper(b.franja)) AT TIME ZONE :tz) > s.slot_start_utc
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM appointments a
                        WHERE a.doctor_id = :doctor_id
                          AND a.status <> 'cancelled'
                          AND a.franja && tstzrange(s.slot_start_utc, s.slot_end_utc)
                    )
                ) AS available_slots
            FROM slots s
            GROUP BY s.day
            ORDER BY s.day
        ", [
            'month_start' => $monthStart,
            'tz'          => $timezone,
            'doctor_id'   => $doctorId,
            'dp_id'       => $directory->doctor_profile_id,
        ]);

        $days = [];
        foreach ($rows as $row) {
            $days[$row->day] = ((int) $row->available_slots > 0) ? 'available' : 'full';
        }

        return [
            'doctor_id' => $doctorId,
            'month'     => $month,
            'timezone'  => $timezone,
            'days'      => $days,
        ];
    }
}
