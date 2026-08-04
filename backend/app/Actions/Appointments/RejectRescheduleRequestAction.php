<?php
declare(strict_types=1);

namespace App\Actions\Appointments;

use App\Exceptions\AppointmentNotFoundException;
use App\Exceptions\RescheduleRequestNotFoundException;
use App\Exceptions\UnauthorizedCancellationException;
use App\Models\Appointment;
use App\Models\RescheduleRequest;
use Illuminate\Support\Facades\DB;

final readonly class RejectRescheduleRequestAction
{
    /**
     * Rechazar la solicitud de reprogramación de una cita médica.
     *
     * @param  string  $appointmentId
     * @param  string  $doctorId
     * @param  string  $userRole
     * @param  string|null  $reason
     * @return \App\Models\RescheduleRequest
     *
     * @throws \App\Exceptions\AppointmentNotFoundException
     * @throws \App\Exceptions\RescheduleRequestNotFoundException
     * @throws \App\Exceptions\UnauthorizedCancellationException
     */
    public function handle(string $appointmentId, string $doctorId, string $userRole, ?string $reason = null): RescheduleRequest
    {
        $appointment = Appointment::find($appointmentId);

        if (!$appointment) {
            throw new AppointmentNotFoundException('Cita médica no encontrada.');
        }

        // Solo el médico asignado o un administrador puede rechazar
        $isDoctor = ($appointment->doctor_id === $doctorId);
        $isAdmin  = ($userRole === 'admin');

        if (!$isDoctor && !$isAdmin) {
            throw new UnauthorizedCancellationException('No tiene permisos para rechazar la reprogramación de esta cita.');
        }

        $rescheduleRequest = RescheduleRequest::where('appointment_id', $appointmentId)
            ->where('status', 'pending')
            ->first();

        if (!$rescheduleRequest) {
            throw new RescheduleRequestNotFoundException('No existe una solicitud de reprogramación pendiente para esta cita.');
        }

        return DB::transaction(function () use ($rescheduleRequest, $doctorId, $reason): RescheduleRequest {
            $rescheduleRequest->update([
                'status'           => 'rejected',
                'rejection_reason' => $reason ?? 'Rechazado por el médico tratante',
                'resolved_by'      => $doctorId,
                'resolved_at'      => now(),
            ]);

            return $rescheduleRequest->fresh();
        });
    }
}
