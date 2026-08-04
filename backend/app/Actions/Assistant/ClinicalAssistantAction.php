<?php
declare(strict_types=1);

namespace App\Actions\Assistant;

use App\Exceptions\AssistantDisabledDuringConsultationException;
use App\Models\Appointment;
use App\Models\Consultation;
use Illuminate\Support\Facades\DB;

final readonly class ClinicalAssistantAction
{
    /**
     * Procesar consulta del asistente clínico en el dashboard del paciente (RF-24).
     *
     * @param  string  $patientId
     * @param  string  $query
     * @return array<string, mixed>
     *
     * @throws \App\Exceptions\AssistantDisabledDuringConsultationException
     */
    public function handle(string $patientId, string $query): array
    {
        // Regla 3: Verificar si el paciente tiene una consulta médica activa (in_progress)
        $hasActiveConsultation = Consultation::whereHas('appointment', function ($q) use ($patientId) {
            $q->where('patient_id', $patientId);
        })
        ->whereNull('ended_at')
        ->exists();

        if (!$hasActiveConsultation) {
            $hasActiveConsultation = Appointment::where('patient_id', $patientId)
                ->where('status', 'in_progress')
                ->exists();
        }

        if ($hasActiveConsultation) {
            throw new AssistantDisabledDuringConsultationException(
                'El asistente clínico está deshabilitado durante una consulta médica en curso.'
            );
        }

        return [
            'reply'   => 'Hola, soy tu Asistente Clínico. ¿En qué puedo orientarte hoy sobre tu salud o citas?',
            'status'  => 'active',
            'patient_id' => $patientId,
        ];
    }
}
