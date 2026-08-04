<?php
declare(strict_types=1);

namespace App\Actions\Clinical;

use App\Exceptions\ConsultationNotFoundException;
use App\Exceptions\InvalidAppointmentStatusException;
use App\Exceptions\NoteNotFoundException;
use App\Exceptions\UnauthorizedCancellationException;
use App\Jobs\GenerateClinicalNotePdfJob;
use App\Models\Consultation;
use App\Models\ConsultationNote;
use Illuminate\Support\Facades\DB;

final readonly class AcknowledgeConsultationNoteAction
{
    /**
     * Firmar el acuse de recibo de una nota clínica por el paciente (RF-19).
     *
     * @param  string  $consultationId
     * @param  string  $userId
     * @param  string  $userRole
     * @return \App\Models\ConsultationNote
     *
     * @throws \App\Exceptions\ConsultationNotFoundException
     * @throws \App\Exceptions\NoteNotFoundException
     * @throws \App\Exceptions\UnauthorizedCancellationException
     * @throws \App\Exceptions\InvalidAppointmentStatusException
     */
    public function handle(string $consultationId, string $userId, string $userRole): ConsultationNote
    {
        $consultation = Consultation::with('appointment')->find($consultationId);
        if (!$consultation) {
            $consultation = Consultation::where('appointment_id', $consultationId)->first();
        }

        if (!$consultation) {
            throw new ConsultationNotFoundException('Consulta médica no encontrada.');
        }

        $appointment = $consultation->appointment;
        if (!$appointment) {
            throw new ConsultationNotFoundException('Cita médica no encontrada.');
        }

        // Permisos: Solo el paciente dueño de la cita o admin puede firmar el acuse
        $isPatient = ($appointment->patient_id === $userId);
        $isAdmin   = ($userRole === 'admin');

        if (!$isPatient && !$isAdmin) {
            throw new UnauthorizedCancellationException('No tiene permisos para firmar el acuse de recibo de esta consulta.');
        }

        // Contexto de sistema para inspeccionar estado de nota bajo RLS
        DB::statement("SET app.current_user_role = 'admin'");

        $note = ConsultationNote::where('consultation_id', $consultation->id)->first();
        if (!$note) {
            throw new NoteNotFoundException('No existe nota clínica registrada para esta consulta.');
        }

        // Si la nota está en borrador (draft), responde 422 Unprocessable Entity por PRD RF-19
        if ($note->status !== 'signed') {
            throw new InvalidAppointmentStatusException('No es posible firmar el acuse de recibo de una nota clínica que se encuentra en borrador.');
        }

        return DB::transaction(function () use ($note): ConsultationNote {
            if (!$note->acknowledged_at) {
                $note->update([
                    'acknowledged_at' => now(),
                ]);

                // Regenerar el PDF asíncronamente con la constancia de acuse incorporada
                GenerateClinicalNotePdfJob::dispatch($note->id);
            }

            return $note->fresh();
        });
    }
}
