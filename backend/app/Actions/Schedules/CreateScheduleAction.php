<?php
declare(strict_types=1);

namespace App\Actions\Schedules;

use App\Exceptions\ScheduleCollisionException;
use App\Models\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class CreateScheduleAction
{
    /**
     * Crear una nueva franja recurrente de horario para un médico.
     *
     * @param  array{doctor_profile_id: string, day_of_week: int, inicio: string, fin: string, slot_duration: int}  $data
     * @return \App\Models\Schedule
     *
     * @throws \App\Exceptions\ScheduleCollisionException
     */
    public function handle(array $data): Schedule
    {
        $franjaRange = sprintf('[%s, %s)', $data['inicio'], $data['fin']);

        try {
            return DB::transaction(function () use ($data, $franjaRange): Schedule {
                return Schedule::create([
                    'doctor_profile_id' => $data['doctor_profile_id'],
                    'day_of_week'       => $data['day_of_week'],
                    'franja'            => $franjaRange,
                    'slot_duration'     => $data['slot_duration'] ?? 30,
                ]);
            });
        } catch (QueryException $e) {
            $code = $e->getCode();
            if ($code === '23505' || $code === '23P01') {
                throw new ScheduleCollisionException('La franja recurrente entra en conflicto con una existente.');
            }
            throw $e;
        }
    }
}
