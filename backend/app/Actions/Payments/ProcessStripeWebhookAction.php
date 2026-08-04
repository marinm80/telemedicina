<?php
declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\ProcessedStripeEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ProcessStripeWebhookAction
{
    /**
     * Procesar un evento de webhook de Stripe con protección idempotente de duplicados.
     *
     * @param  array{id: string, type: string, data: array{object: array<string, mixed>}}  $eventData
     * @return array{status: string, event_id: string, appointment_id?: string}
     */
    public function handle(array $eventData): array
    {
        $eventId   = $eventData['id'] ?? '';
        $eventType = $eventData['type'] ?? '';
        $object    = $eventData['data']['object'] ?? [];

        if (empty($eventId)) {
            return ['status' => 'ignored', 'event_id' => ''];
        }

        // Establecer contexto administrativo para la transacción de webhook de sistema
        DB::statement("SET app.current_user_role = 'admin'");

        // 1. Verificación de Idempotencia: Si ya fue procesado, retornar 200 OK sin ejecutar escrituras
        $alreadyProcessed = ProcessedStripeEvent::where('event_id', $eventId)->exists();
        if ($alreadyProcessed) {
            return ['status' => 'already_processed', 'event_id' => $eventId];
        }

        // 2. Procesar evento bajo contexto de sistema/admin para RLS
        return DB::transaction(function () use ($eventId, $eventType, $object): array {
            // Registrar idempotencia del evento
            ProcessedStripeEvent::create([
                'event_id'     => $eventId,
                'processed_at' => now(),
            ]);

            $appointmentId = null;

            if ($eventType === 'payment_intent.succeeded') {
                $paymentIntentId = $object['id'] ?? '';
                $amount          = ((float) ($object['amount'] ?? 0)) / 100.00;
                $metadata        = $object['metadata'] ?? [];
                $appointmentId   = $metadata['appointment_id'] ?? null;

                if ($appointmentId) {
                    $appointment = Appointment::find($appointmentId);
                    if ($appointment) {
                        $appointment->update(['status' => 'confirmed']);
                    }

                    Payment::updateOrCreate(
                        ['appointment_id' => $appointmentId],
                        [
                            'id'                       => Str::uuid()->toString(),
                            'stripe_payment_intent_id' => $paymentIntentId,
                            'amount'                   => $amount > 0 ? $amount : 50.00,
                            'currency'                 => 'USD',
                            'status'                   => 'completed',
                            'paid_at'                  => now(),
                        ]
                    );
                }
            } elseif ($eventType === 'charge.refunded') {
                $paymentIntentId = $object['payment_intent'] ?? '';
                $metadata        = $object['metadata'] ?? [];
                $appointmentId   = $metadata['appointment_id'] ?? null;

                $payment = null;
                if ($appointmentId) {
                    $payment = Payment::where('appointment_id', $appointmentId)->first();
                } elseif ($paymentIntentId) {
                    $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();
                }

                if ($payment) {
                    $payment->update([
                        'status'      => 'refunded',
                        'refunded_at' => now(),
                    ]);
                }
            }

            return [
                'status'         => 'success',
                'event_id'       => $eventId,
                'appointment_id' => $appointmentId,
            ];
        });
    }
}
