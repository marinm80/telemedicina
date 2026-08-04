<?php
declare(strict_types=1);

namespace App\Actions\Clinical;

use App\Exceptions\AppointmentNotFoundException;
use App\Exceptions\InvalidAppointmentStatusException;
use App\Exceptions\PreConsultationAlreadyExistsException;
use App\Exceptions\UnauthorizedCancellationException;
use App\Models\Appointment;
use App\Models\PreConsultationForm;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class SubmitPreConsultationAction
{
    /**
     * Procesar y guardar el cuestionario pre-consulta médica.
     *
     * @param  string  $appointmentId
     * @param  string  $userId
     * @param  string  $userRole
     * @param  string  $motivo
     * @param  string  $sintomas
     * @param  array<string, mixed>|null  $formData
     * @return \App\Models\PreConsultationForm
     *
     * @throws \App\Exceptions\AppointmentNotFoundException
     * @throws \App\Exceptions\UnauthorizedCancellationException
     * @throws \App\Exceptions\InvalidAppointmentStatusException
     * @throws \App\Exceptions\PreConsultationAlreadyExistsException
     */
    public function handle(
        string $appointmentId,
        string $userId,
        string $userRole,
        string $motivo,
        string $sintomas,
        ?array $formData = null
    ): PreConsultationForm {
        $appointment = Appointment::find($appointmentId);

        if (!$appointment) {
            throw new AppointmentNotFoundException('Cita médica no encontrada.');
        }

        // Permisos: Paciente dueño de la cita, agente o admin
        $isPatient = ($appointment->patient_id === $userId);
        $isStaff   = in_array($userRole, ['agent', 'admin'], true);

        if (!$isPatient && !$isStaff) {
            throw new UnauthorizedCancellationException('No tiene permisos para enviar el cuestionario pre-consulta de esta cita.');
        }

        // Error al enviar cuestionario para cita cancelada o completada
        if (in_array($appointment->status, ['cancelled', 'completed'], true)) {
            throw new InvalidAppointmentStatusException('No es posible enviar el cuestionario pre-consulta para una cita cancelada o completada.');
        }

        if (PreConsultationForm::where('appointment_id', $appointmentId)->exists()) {
            throw new PreConsultationAlreadyExistsException('Esta cita ya cuenta con un cuestionario pre-consulta registrado.');
        }

        try {
            return DB::transaction(function () use ($appointmentId, $motivo, $sintomas, $formData): PreConsultationForm {
                return PreConsultationForm::create([
                    'id'             => Str::uuid()->toString(),
                    'appointment_id' => $appointmentId,
                    'motivo'         => $motivo,
                    'sintomas'       => $sintomas,
                    'form_data'      => $formData,
                ]);
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                throw new PreConsultationAlreadyExistsException('Esta cita ya cuenta con un cuestionario pre-consulta registrado.');
            }
            throw $e;
        }
    }
}
