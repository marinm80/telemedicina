<?php
declare(strict_types=1);

namespace App\Actions\Clinical;

use App\Events\NewConsultationMessage;
use App\Exceptions\ConsultationNotFoundException;
use App\Exceptions\InvalidAppointmentStatusException;
use App\Exceptions\UnauthorizedCancellationException;
use App\Models\Consultation;
use App\Models\ConsultationMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class SendConsultationMessageAction
{
    /**
     * Enviar y persistir un mensaje en el chat clínico de una consulta en tiempo real.
     *
     * @param  string  $consultationId
     * @param  string  $senderId
     * @param  string  $userRole
     * @param  string  $content
     * @return \App\Models\ConsultationMessage
     *
     * @throws \App\Exceptions\ConsultationNotFoundException
     * @throws \App\Exceptions\UnauthorizedCancellationException
     * @throws \App\Exceptions\InvalidAppointmentStatusException
     */
    public function handle(string $consultationId, string $senderId, string $userRole, string $content): ConsultationMessage
    {
        // El agente NUNCA puede enviar mensajes ni unirse a la consulta médica (RF-14 & RLS)
        if ($userRole === 'agent') {
            throw new UnauthorizedCancellationException('El agente administrativo no tiene acceso al chat clínico de la consulta.');
        }

        $consultation = Consultation::with('appointment')->find($consultationId);

        // Si la ID recibida es la de la cita, buscar la consulta enlazada o asegurar su existencia
        if (!$consultation) {
            $consultation = Consultation::where('appointment_id', $consultationId)->first();
        }

        if (!$consultation) {
            throw new ConsultationNotFoundException('Consulta médica no encontrada.');
        }

        $appointment = $consultation->appointment;
        if (!$appointment) {
            throw new ConsultationNotFoundException('Cita médica no encontrada para esta consulta.');
        }

        // Permisos: solo el paciente o el médico de la consulta
        $isPatient = ($appointment->patient_id === $senderId);
        $isDoctor  = ($appointment->doctor_id === $senderId);
        $isAdmin   = ($userRole === 'admin');

        if (!$isPatient && !$isDoctor && !$isAdmin) {
            throw new UnauthorizedCancellationException('No tiene permisos para interactuar en el chat clínico de esta consulta.');
        }

        // Si la cita está cancelada, no se permiten nuevos mensajes
        if ($appointment->status === 'cancelled') {
            throw new InvalidAppointmentStatusException('No es posible enviar mensajes en una consulta de cita cancelada.');
        }

        return DB::transaction(function () use ($consultation, $senderId, $content): ConsultationMessage {
            $message = ConsultationMessage::create([
                'id'              => Str::uuid()->toString(),
                'consultation_id' => $consultation->id,
                'sender_id'       => $senderId,
                'content'         => $content,
                'created_at'      => now(),
            ]);

            // Transmitir en vivo vía Reverb WebSockets
            event(new NewConsultationMessage($message));

            return $message;
        });
    }
}
