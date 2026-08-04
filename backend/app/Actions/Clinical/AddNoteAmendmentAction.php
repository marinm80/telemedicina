<?php
declare(strict_types=1);

namespace App\Actions\Clinical;

use App\Exceptions\ConsultationNotFoundException;
use App\Exceptions\InvalidAppointmentStatusException;
use App\Exceptions\NoteNotFoundException;
use App\Exceptions\UnauthorizedCancellationException;
use App\Models\Consultation;
use App\Models\ConsultationNote;
use App\Models\NoteAmendment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class AddNoteAmendmentAction
{
    /**
     * Añadir una enmienda médica a una nota clínica firmada (RF-17).
     *
     * @param  string  $consultationId
     * @param  string  $authorId
     * @param  string  $userRole
     * @param  string  $reason
     * @param  string  $content
     * @return \App\Models\NoteAmendment
     *
     * @throws \App\Exceptions\ConsultationNotFoundException
     * @throws \App\Exceptions\NoteNotFoundException
     * @throws \App\Exceptions\UnauthorizedCancellationException
     * @throws \App\Exceptions\InvalidAppointmentStatusException
     */
    public function handle(
        string $consultationId,
        string $authorId,
        string $userRole,
        string $reason,
        string $content
    ): NoteAmendment {
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

        // Permisos: Solo el médico de la consulta o un admin pueden emitir enmiendas
        $isDoctor = ($appointment->doctor_id === $authorId);
        $isAdmin  = ($userRole === 'admin');

        if (!$isDoctor && !$isAdmin) {
            throw new UnauthorizedCancellationException('No tiene permisos para enmendar la nota clínica de esta consulta.');
        }

        $note = ConsultationNote::where('consultation_id', $consultation->id)->first();
        if (!$note) {
            throw new NoteNotFoundException('No existe una nota clínica registrada para esta consulta.');
        }

        if ($note->status !== 'signed') {
            throw new InvalidAppointmentStatusException('Solo es posible añadir enmiendas clínicas a notas firmadas electrónicamente.');
        }

        return DB::transaction(function () use ($note, $authorId, $reason, $content): NoteAmendment {
            return NoteAmendment::create([
                'id'                   => Str::uuid()->toString(),
                'consultation_note_id' => $note->id,
                'author_id'            => $authorId,
                'reason'               => $reason,
                'content'              => $content,
                'created_at'           => now(),
            ]);
        });
    }
}
