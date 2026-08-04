<?php
declare(strict_types=1);

namespace App\Actions\Schedules;

use App\Exceptions\ScheduleNotFoundException;
use App\Models\ScheduleBlock;

final readonly class DeleteScheduleBlockAction
{
    /**
     * Borrar un bloqueo de fecha/franja de un médico.
     *
     * @param  string  $blockId
     * @param  string  $doctorProfileId
     * @return array{deleted: bool}
     *
     * @throws \App\Exceptions\ScheduleNotFoundException
     */
    public function handle(string $blockId, string $doctorProfileId): array
    {
        $block = ScheduleBlock::where('id', $blockId)
            ->where('doctor_profile_id', $doctorProfileId)
            ->first();

        if (!$block) {
            throw new ScheduleNotFoundException('Bloqueo de agenda no encontrado.');
        }

        $block->delete();

        return ['deleted' => true];
    }
}
