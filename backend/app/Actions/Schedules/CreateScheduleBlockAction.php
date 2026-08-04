<?php
declare(strict_types=1);

namespace App\Actions\Schedules;

use App\Exceptions\ScheduleCollisionException;
use App\Models\ScheduleBlock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class CreateScheduleBlockAction
{
    /**
     * Crear un bloqueo de fecha/franja específica para un médico.
     *
     * @param  array{doctor_profile_id: string, blocked_date: string, inicio: string, fin: string, reason: string}  $data
     * @return \App\Models\ScheduleBlock
     *
     * @throws \App\Exceptions\ScheduleCollisionException
     */
    public function handle(array $data): ScheduleBlock
    {
        $franjaRange = sprintf('[%s, %s)', $data['inicio'], $data['fin']);

        try {
            return DB::transaction(function () use ($data, $franjaRange): ScheduleBlock {
                return ScheduleBlock::create([
                    'doctor_profile_id' => $data['doctor_profile_id'],
                    'blocked_date'      => $data['blocked_date'],
                    'franja'            => $franjaRange,
                    'reason'            => $data['reason'],
                ]);
            });
        } catch (QueryException $e) {
            $code = $e->getCode();
            // P0002 = trigger trg_prevent_schedule_block_appointment_overlap; 23P01/23505 = DB constraints
            if ($code === 'P0002' || $code === '23P01' || $code === '23505') {
                throw new ScheduleCollisionException('No se puede crear el bloqueo: entra en conflicto con citas activas o bloqueos existentes.');
            }
            throw $e;
        }
    }
}
