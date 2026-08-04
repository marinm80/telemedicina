<?php
declare(strict_types=1);

namespace App\Actions\Appointments;

use App\Exceptions\AppointmentNotFoundException;
use App\Exceptions\RescheduleCollisionException;
use App\Exceptions\RescheduleRequestNotFoundException;
use App\Exceptions\UnauthorizedCancellationException;
use App\Models\Appointment;
use App\Models\RescheduleRequest;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ApproveRescheduleRequestAction
{
    /**
     * Aprobar la solicitud de reprogramación en una sola transacción atómica ACID.
     *
     * @param  string  $appointmentId
     * @param  string  $doctorId
     * @param  string  $userRole
     * @return array{reschedule_request: \App\Models\RescheduleRequest, original_appointment: \App\Models\Appointment, new_appointment: \App\Models\Appointment}
     *
     * @throws \App\Exceptions\AppointmentNotFoundException
     * @throws \App\Exceptions\RescheduleRequestNotFoundException
     * @throws \App\Exceptions\UnauthorizedCancellationException
     * @throws \App\Exceptions\RescheduleCollisionException
     */
    public function handle(string $appointmentId, string $doctorId, string $userRole): array
    {
        $appointment = Appointment::find($appointmentId);

        if (!$appointment) {
            throw new AppointmentNotFoundException('Cita médica no encontrada.');
        }

        // Solo el médico asignado a la cita o un administrador puede aprobar
        $isDoctor = ($appointment->doctor_id === $doctorId);
        $isAdmin  = ($userRole === 'admin');

        if (!$isDoctor && !$isAdmin) {
            throw new UnauthorizedCancellationException('No tiene permisos para aprobar la reprogramación de esta cita.');
        }

        $rescheduleRequest = RescheduleRequest::where('appointment_id', $appointmentId)
            ->where('status', 'pending')
            ->first();

        if (!$rescheduleRequest) {
            throw new RescheduleRequestNotFoundException('No existe una solicitud de reprogramación pendiente para esta cita.');
        }

        try {
            return DB::transaction(function () use ($appointment, $rescheduleRequest, $doctorId): array {
                // 1. Marcar solicitud como aprobada
                $rescheduleRequest->update([
                    'status'      => 'approved',
                    'resolved_by' => $doctorId,
                    'resolved_at' => now(),
                ]);

                // 2. Cancelar cita original
                $appointment->update([
                    'status'              => 'cancelled',
                    'cancelled_by'        => $doctorId,
                    'cancellation_reason' => sprintf('Reprogramada a la cita %s', $rescheduleRequest->id),
                    'cancelled_at'        => now(),
                    'refund_status'       => 'rescheduled',
                ]);

                // 3. Crear nueva cita confirmada
                $newAppointment = Appointment::create([
                    'id'               => Str::uuid()->toString(),
                    'patient_id'       => $appointment->patient_id,
                    'doctor_id'        => $appointment->doctor_id,
                    'franja'           => $rescheduleRequest->requested_franja,
                    'status'           => 'confirmed',
                    'rescheduled_from' => $appointment->id,
                ]);

                return [
                    'reschedule_request'   => $rescheduleRequest->fresh(),
                    'original_appointment' => $appointment->fresh(),
                    'new_appointment'      => $newAppointment,
                ];
            });
        } catch (QueryException $e) {
            $code = $e->getCode();
            if ($code === '23P01' || $code === '23505') {
                throw new RescheduleCollisionException('El slot solicitado ya está ocupado por otra cita.');
            }
            throw $e;
        }
    }
}
