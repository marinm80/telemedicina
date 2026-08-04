<?php
declare(strict_types=1);

namespace App\Actions\Appointments;

use App\Exceptions\AppointmentNotFoundException;
use App\Exceptions\InvalidAppointmentStatusException;
use App\Exceptions\RescheduleCollisionException;
use App\Exceptions\UnauthorizedCancellationException;
use App\Models\Appointment;
use App\Models\RescheduleRequest;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateRescheduleRequestAction
{
    /**
     * Crear una solicitud de reprogramación para una cita médica.
     *
     * @param  string  $appointmentId
     * @param  string  $requestedById
     * @param  string  $userRole
     * @param  string  $nuevaFranjaInicio
     * @param  string  $nuevaFranjaFin
     * @param  string|null  $reason
     * @return \App\Models\RescheduleRequest
     *
     * @throws \App\Exceptions\AppointmentNotFoundException
     * @throws \App\Exceptions\UnauthorizedCancellationException
     * @throws \App\Exceptions\InvalidAppointmentStatusException
     * @throws \App\Exceptions\RescheduleCollisionException
     */
    public function handle(
        string $appointmentId,
        string $requestedById,
        string $userRole,
        string $nuevaFranjaInicio,
        string $nuevaFranjaFin,
        ?string $reason = null
    ): RescheduleRequest {
        $appointment = Appointment::find($appointmentId);

        if (!$appointment) {
            throw new AppointmentNotFoundException('Cita médica no encontrada.');
        }

        // Permisos: solo el paciente dueño o un agente/admin pueden solicitar reprogramación
        $isPatient = ($appointment->patient_id === $requestedById);
        $isAgent   = in_array($userRole, ['agent', 'admin'], true);

        if (!$isPatient && !$isAgent) {
            throw new UnauthorizedCancellationException('No tiene permisos para solicitar la reprogramación de esta cita.');
        }

        // No se puede reprogramar una cita cancelada o completada
        if (in_array($appointment->status, ['cancelled', 'completed'], true)) {
            throw new InvalidAppointmentStatusException('No se puede reprogramar una cita cancelada o completada.');
        }

        $franjaRange = sprintf('[%s, %s)', $nuevaFranjaInicio, $nuevaFranjaFin);

        try {
            return DB::transaction(function () use ($appointment, $requestedById, $franjaRange, $reason): RescheduleRequest {
                return RescheduleRequest::create([
                    'id'               => Str::uuid()->toString(),
                    'appointment_id'   => $appointment->id,
                    'doctor_id'        => $appointment->doctor_id,
                    'requested_by'     => $requestedById,
                    'requested_franja' => $franjaRange,
                    'reason'           => $reason,
                    'status'           => 'pending',
                ]);
            });
        } catch (QueryException $e) {
            $code = $e->getCode();
            if ($code === '23505') {
                throw new RescheduleCollisionException('Ya existe una solicitud de reprogramación pendiente para esta cita.');
            }
            if ($code === '23P01') {
                throw new RescheduleCollisionException('El slot solicitado entra en conflicto con otra cita u otra solicitud pendiente.');
            }
            throw $e;
        }
    }
}
