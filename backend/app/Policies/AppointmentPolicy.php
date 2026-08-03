<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 */

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class AppointmentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can create appointments.
     *
     * @param  \App\Models\User  $user
     * @param  string  $patientId
     * @return bool
     */
    public function create(User $user, string $patientId): bool
    {
        // Si es agente, puede agendar para cualquier paciente
        if ($user->role === 'agent' || $user->role === 'admin') {
            return true;
        }

        // Si es paciente, sólo puede agendar para sí mismo
        if ($user->role === 'patient') {
            return $user->id === $patientId;
        }

        return false;
    }

    /**
     * Determine whether the user can view an appointment.
     */
    public function view(User $user, $appointment): bool
    {
        if ($user->role === 'admin' || $user->role === 'agent') {
            return true;
        }

        return $user->id === $appointment->patient_id || $user->id === $appointment->doctor_id;
    }
}
