<?php
declare(strict_types=1);

namespace App\Actions\Appointments;

use App\Exceptions\AppointmentNotFoundException;
use App\Exceptions\InvalidAppointmentStatusException;
use App\Exceptions\UnauthorizedCancellationException;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;

final readonly class CancelAppointmentAction
{
    /**
     * Procesar la cancelación de una cita médica y calcular la política de reembolso.
     *
     * @param  string  $appointmentId
     * @param  string  $userId
     * @param  string  $userRole
     * @param  string|null  $reason
     * @return array{appointment: \App\Models\Appointment, refund_percentage: int, refund_status: string}
     *
     * @throws \App\Exceptions\AppointmentNotFoundException
     * @throws \App\Exceptions\UnauthorizedCancellationException
     * @throws \App\Exceptions\InvalidAppointmentStatusException
     */
    public function handle(string $appointmentId, string $userId, string $userRole, ?string $reason = null): array
    {
        $appointment = Appointment::find($appointmentId);

        if (!$appointment) {
            throw new AppointmentNotFoundException('Cita médica no encontrada.');
        }

        // Verificar pertenencia (solo el paciente dueño, el médico asignado o un admin pueden cancelar)
        $isPatient = ($appointment->patient_id === $userId);
        $isDoctor  = ($appointment->doctor_id === $userId);
        $isAdmin   = ($userRole === 'admin');

        if (!$isPatient && !$isDoctor && !$isAdmin) {
            throw new UnauthorizedCancellationException('No tiene permisos para cancelar esta cita.');
        }

        // No se puede cancelar una cita que ya fue cancelada o completada
        if (in_array($appointment->status, ['cancelled', 'completed'], true)) {
            throw new InvalidAppointmentStatusException('La cita ya fue procesada, completada o cancelada previamente.');
        }

        // Determinar política de reembolso
        // Si cancela el médico o admin: 100% reembolso SIEMPRE (sin ventana temporal)
        // Si cancela el paciente: 100% reembolso solo si faltan > 24 horas antes del inicio
        $refundPercentage = 100;
        $refundStatus = 'full_refund';

        if ($isPatient && !$isAdmin) {
            $hoursUntilStart = DB::selectOne("
                SELECT EXTRACT(EPOCH FROM (lower(franja) - NOW())) / 3600 AS hours_diff
                FROM appointments
                WHERE id = ?::uuid
            ", [$appointmentId]);

            $diffInHours = (float) ($hoursUntilStart->hours_diff ?? 0);

            if ($diffInHours <= 24.0) {
                $refundPercentage = 0;
                $refundStatus = 'no_refund';
            }
        }

        // Transacción atómica de cancelación
        DB::transaction(function () use ($appointment, $userId, $reason, $refundPercentage, $refundStatus): void {
            $appointment->update([
                'status'              => 'cancelled',
                'cancelled_by'        => $userId,
                'cancellation_reason' => $reason ?? 'Cancelado por el usuario',
                'cancelled_at'        => now(),
                'refund_status'       => $refundStatus,
            ]);
        });

        return [
            'appointment'       => $appointment->fresh(),
            'refund_percentage' => $refundPercentage,
            'refund_status'     => $refundStatus,
        ];
    }
}
