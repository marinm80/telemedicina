<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 */

namespace App\Actions\Appointments;

use App\Models\Appointment;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Exceptions\IdempotencyCollisionException;
use App\Exceptions\SlotCollisionException;

final readonly class BookAppointmentAction
{
    /**
     * Handle the booking logic of an appointment with database-backed idempotency.
     *
     * @param  array{patient_id: string, doctor_id: string, franja_inicio: string, franja_fin: string}  $data
     * @param  string  $idempotencyKey
     * @return \App\Models\Appointment
     *
     * @throws \App\Exceptions\IdempotencyCollisionException
     * @throws \App\Exceptions\SlotCollisionException
     */
    public function handle(array $data, string $idempotencyKey): Appointment
    {
        $payloadHash = hash('sha256', json_encode($data));

        // 1. Lectura por delante (optimización de camino feliz secuencial)
        $existing = Appointment::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            if ($existing->idempotency_payload_hash === $payloadHash) {
                return $existing;
            }
            throw new IdempotencyCollisionException('La clave de idempotencia se reutilizó con datos diferentes.');
        }

        // 2. Intento de Inserción Atómica (Defensa de última línea contra carrera concurrente)
        try {
            return DB::transaction(function () use ($data, $idempotencyKey, $payloadHash): Appointment {
                $franjaRange = sprintf('[%s, %s)', $data['franja_inicio'], $data['franja_fin']);

                return Appointment::create([
                    'patient_id'               => $data['patient_id'],
                    'doctor_id'                => $data['doctor_id'],
                    'franja'                   => $franjaRange,
                    'status'                   => 'pending',
                    'idempotency_key'          => $idempotencyKey,
                    'idempotency_payload_hash' => $payloadHash,
                ]);
            });
        } catch (QueryException $e) {
            $errorCode = $e->getCode();

            // Código 23505: Violación de clave única (idempotencia paralela por doble clic concurrente)
            if ($errorCode === '23505' || str_contains($e->getMessage(), 'appointments_idempotency_key_key')) {
                $concurrente = Appointment::where('idempotency_key', $idempotencyKey)->first();
                if ($concurrente && $concurrente->idempotency_payload_hash === $payloadHash) {
                    return $concurrente;
                }
                throw new IdempotencyCollisionException('La clave de idempotencia se reutilizó con datos diferentes.');
            }

            // Código 23P01: Violación de restricción de exclusión (slot ocupado por un tercero)
            if ($errorCode === '23P01' || str_contains($e->getMessage(), 'appointments_sin_solapamiento')) {
                throw new SlotCollisionException('El slot horario seleccionado ya está reservado.');
            }

            throw $e;
        }
    }
}
