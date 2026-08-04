<?php
declare(strict_types=1);

namespace App\Actions\Clinical;

use App\Exceptions\ConsultationNotFoundException;
use App\Exceptions\NoteNotFoundException;
use App\Exceptions\UnauthorizedCancellationException;
use App\Models\Consultation;
use App\Models\ConsultationNote;
use Illuminate\Support\Facades\DB;

final readonly class SignConsultationNoteAction
{
    /**
     * Firmar electrónicamente la nota clínica SOAP (RF-16).
     *
     * @param  string  $consultationId
     * @param  string  $doctorId
     * @param  string  $userRole
     * @param  string|null  $ipAddress
     * @param  string|null  $userAgent
     * @return \App\Models\ConsultationNote
     *
     * @throws \App\Exceptions\ConsultationNotFoundException
     * @throws \App\Exceptions\NoteNotFoundException
     * @throws \App\Exceptions\UnauthorizedCancellationException
     */
    public function handle(
        string $consultationId,
        string $doctorId,
        string $userRole,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): ConsultationNote {
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

        // Solo el médico asignado o admin puede firmar
        $isDoctor = ($appointment->doctor_id === $doctorId);
        $isAdmin  = ($userRole === 'admin');

        if (!$isDoctor && !$isAdmin) {
            throw new UnauthorizedCancellationException('No tiene permisos para firmar la nota clínica de esta consulta.');
        }

        $note = ConsultationNote::where('consultation_id', $consultation->id)->first();
        if (!$note) {
            throw new NoteNotFoundException('No existe una nota clínica para firmar en esta consulta.');
        }

        if ($note->status === 'signed') {
            return $note;
        }

        $hashContent = sprintf(
            "symptoms=%s|objective=%s|analysis=%s|plan=%s|consultation=%s|doctor=%s",
            $note->symptoms,
            $note->objective,
            $note->analysis,
            $note->plan,
            $consultation->id,
            $doctorId
        );

        $contentHash = hash('sha256', $hashContent);

        return DB::transaction(function () use ($note, $doctorId, $contentHash, $ipAddress, $userAgent): ConsultationNote {
            $note->update([
                'status'            => 'signed',
                'content_hash'      => $contentHash,
                'signed_by'         => $doctorId,
                'signed_at'         => now(),
                'signed_ip'         => $ipAddress,
                'signed_user_agent' => $userAgent,
            ]);

            // RF-18: Encolar generación asíncrona de PDF y QR clínico
            \App\Jobs\GenerateClinicalNotePdfJob::dispatch($note->id);

            $note->status            = 'signed';
            $note->content_hash      = $contentHash;
            $note->signed_by         = $doctorId;
            $note->signed_at         = now();
            $note->signed_ip         = $ipAddress;
            $note->signed_user_agent = $userAgent;

            return $note;
        });
    }
}
