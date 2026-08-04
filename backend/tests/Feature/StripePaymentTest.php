<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class StripePaymentTest extends TestCase
{
    private User $patient;
    private User $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $mc = DB::connection('pgsql_migration');
        $patientRole = Role::where('name', 'patient')->first();
        $doctorRole  = Role::where('name', 'doctor')->first();

        $this->patient = User::factory()->create(['timezone' => 'UTC']);
        $mc->table('user_roles')->insert(['user_id' => $this->patient->id, 'role_id' => $patientRole->id]);

        $this->doctor = User::factory()->create(['timezone' => 'UTC']);
        $mc->table('user_roles')->insert(['user_id' => $this->doctor->id, 'role_id' => $doctorRole->id]);
    }

    public function test_webhook_procesa_confirmacion_pago_por_primera_vez(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-15 10:00:00+00, 2026-08-15 10:30:00+00)',
            'status'     => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $eventId = 'evt_test_' . Str::random(10);
        $paymentIntentId = 'pi_test_' . Str::random(10);

        $payload = [
            'id'   => $eventId,
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id'       => $paymentIntentId,
                    'amount'   => 5000,
                    'currency' => 'usd',
                    'metadata' => [
                        'appointment_id' => $appointmentId,
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/stripe', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'status'   => 'success',
                'event_id' => $eventId,
            ]);

        // Cita debe pasar a confirmed
        $this->assertDatabaseHas('appointments', [
            'id'     => $appointmentId,
            'status' => 'confirmed',
        ]);

        // Registro de pago completado
        $this->assertDatabaseHas('payments', [
            'appointment_id'           => $appointmentId,
            'stripe_payment_intent_id' => $paymentIntentId,
            'status'                   => 'completed',
        ]);

        // Registro de evento procesado
        $this->assertDatabaseHas('processed_stripe_events', [
            'event_id' => $eventId,
        ]);
    }

    public function test_webhook_recibe_evento_duplicado_retorna_200_cached_sin_reprocesar(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-15 11:00:00+00, 2026-08-15 11:30:00+00)',
            'status'     => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $eventId = 'evt_duplicate_' . Str::random(10);
        $payload = [
            'id'   => $eventId,
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id'       => 'pi_dup_' . Str::random(5),
                    'amount'   => 6000,
                    'metadata' => ['appointment_id' => $appointmentId],
                ],
            ],
        ];

        // Primera llamada
        $this->postJson('/api/webhooks/stripe', $payload)->assertStatus(200);

        // Segunda llamada duplicada
        $resDuplicate = $this->postJson('/api/webhooks/stripe', $payload);

        $resDuplicate->assertStatus(200)
            ->assertJson([
                'status'   => 'already_processed',
                'event_id' => $eventId,
            ]);
    }

    public function test_webhook_procesa_reembolso_charge_refunded(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();
        $paymentIntentId = 'pi_refund_' . Str::random(8);

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-15 12:00:00+00, 2026-08-15 12:30:00+00)',
            'status'     => 'cancelled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mc->table('payments')->insert([
            'id'                       => Str::uuid()->toString(),
            'appointment_id'           => $appointmentId,
            'stripe_payment_intent_id' => $paymentIntentId,
            'amount'                   => 75.00,
            'currency'                 => 'USD',
            'status'                   => 'completed',
            'paid_at'                  => now(),
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        $payload = [
            'id'   => 'evt_refund_' . Str::random(8),
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'payment_intent' => $paymentIntentId,
                    'metadata'       => ['appointment_id' => $appointmentId],
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/stripe', $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('payments', [
            'appointment_id' => $appointmentId,
            'status'         => 'refunded',
        ]);
    }
}
