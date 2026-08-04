<?php
declare(strict_types=1);

namespace App\Actions\Clinical;

use App\Exceptions\ConsultationNotFoundException;
use App\Exceptions\NoteAlreadySignedException;
use App\Exceptions\UnauthorizedCancellationException;
use App\Models\Consultation;
use App\Models\ConsultationNote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class SaveConsultationNoteDraftAction
{
    /**
     * Guardar o actualizar el borrador de la nota médica SOAP (RF-15).
     *
     * @param  string  $consultationId
     * @param  string  $doctorId
     * @param  string  $userRole
     * @param  array{symptoms: string, objective: string, analysis: string, plan: string}  $data
     * @return \App\Models\ConsultationNote
     *
     * @throws \App\Exceptions\ConsultationNotFoundException
     * @throws \App\Exceptions\UnauthorizedCancellationException
     * @throws \App\Exceptions\NoteAlreadySignedException
     */
    public function handle(string $consultationId, string $doctorId, string $userRole, array $data): ConsultationNote
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
            throw new ConsultationNotFoundException('Cita médica asociada no encontrada.');
        }

        // Permisos: solo el médico tratante de la consulta o un administrador
        $isDoctor = ($appointment->doctor_id === $doctorId);
        $isAdmin  = ($userRole === 'admin');

        if (!$isDoctor && !$isAdmin) {
            throw new UnauthorizedCancellationException('No tiene permisos para redactar notas clínicas en esta consulta.');
        }

        $existingNote = ConsultationNote::where('consultation_id', $consultation->id)->first();

        // Inmutabilidad: Si la nota ya está firmada, no se permite editar
        if ($existingNote && $existingNote->status === 'signed') {
            throw new NoteAlreadySignedException('La nota clínica ya ha sido firmada electrónicamente y es inmutable. Utilice una enmienda.');
        }

        return DB::transaction(function () use ($consultation, $existingNote, $data): ConsultationNote {
            if ($existingNote) {
                $existingNote->update([
                    'symptoms'  => $data['symptoms'],
                    'objective' => $data['objective'],
                    'analysis'  => $data['analysis'],
                    'plan'      => $data['plan'],
                    'status'    => 'draft',
                ]);
                return $existingNote->fresh();
            }

            return ConsultationNote::create([
                'id'              => Str::uuid()->toString(),
                'consultation_id' => $consultation->id,
                'symptoms'        => $data['symptoms'],
                'objective'       => $data['objective'],
                'analysis'        => $data['analysis'],
                'plan'            => $data['plan'],
                'status'          => 'draft',
                'pdf_status'      => 'pdf_pendiente',
            ]);
        });
    }
}
