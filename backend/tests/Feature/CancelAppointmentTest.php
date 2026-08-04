<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CancelAppointmentTest extends TestCase
{
    private User $patient;
    private User $doctor;
    private User $otherPatient;
    private Role $patientRole;
    private Role $doctorRole;

    protected function setUp(): void
    {
        parent::setUp();

        $mc = DB::connection('pgsql_migration');
        $this->patientRole = Role::where('name', 'patient')->first();
        $this->doctorRole = Role::where('name', 'doctor')->first();

        $this->patient = User::factory()->create(['timezone' => 'UTC']);
        $mc->table('user_roles')->insert(['user_id' => $this->patient->id, 'role_id' => $this->patientRole->id]);

        $this->otherPatient = User::factory()->create(['timezone' => 'UTC']);
        $mc->table('user_roles')->insert(['user_id' => $this->otherPatient->id, 'role_id' => $this->patientRole->id]);

        $this->doctor = User::factory()->create(['timezone' => 'UTC']);
        $mc->table('user_roles')->insert(['user_id' => $this->doctor->id, 'role_id' => $this->doctorRole->id]);

        $mc->table('doctor_profiles')->insert([
            'id'               => Str::uuid()->toString(),
            'user_id'          => $this->doctor->id,
            'license_number'   => 'LIC-CANCEL-' . Str::random(4),
            'university'       => 'Universidad Central',
            'years_experience' => 10,
            'description'      => 'Médico general',
            'consultation_fee' => 100.00,
            'status'           => 'approved',
            'approved_at'      => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function test_medico_cancela_cita_reembolso_100_siempre(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();

        // Cita programada dentro de 2 horas (menos de 24h)
        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => sprintf('[%s, %s)', now()->addHours(2)->toIso8601String(), now()->addHours(3)->toIso8601String()),
            'status'     => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->doctor);

        $response = $this->postJson("/api/appointments/{$appointmentId}/cancel", [
            'reason' => 'Emergencia quirúrgica',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id'                => $appointmentId,
                    'status'            => 'cancelled',
                    'cancelled_by'      => $this->doctor->id,
                    'refund_percentage' => 100,
                    'refund_status'     => 'full_refund',
                ]
            ]);

        $this->assertDatabaseHas('appointments', [
            'id'           => $appointmentId,
            'status'       => 'cancelled',
            'cancelled_by' => $this->doctor->id,
        ]);
    }

    public function test_paciente_cancela_cita_con_mas_de_24h_reembolso_100(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();

        // Cita programada dentro de 48 horas (más de 24h)
        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => sprintf('[%s, %s)', now()->addHours(48)->toIso8601String(), now()->addHours(49)->toIso8601String()),
            'status'     => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->patient);

        $response = $this->postJson("/api/appointments/{$appointmentId}/cancel", [
            'reason' => 'Cambio de planes',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id'                => $appointmentId,
                    'status'            => 'cancelled',
                    'cancelled_by'      => $this->patient->id,
                    'refund_percentage' => 100,
                    'refund_status'     => 'full_refund',
                ]
            ]);
    }

    public function test_paciente_cancela_cita_con_menos_de_24h_sin_reembolso(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();

        // Cita programada dentro de 5 horas (menos de 24h)
        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => sprintf('[%s, %s)', now()->addHours(5)->toIso8601String(), now()->addHours(6)->toIso8601String()),
            'status'     => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->patient);

        $response = $this->postJson("/api/appointments/{$appointmentId}/cancel", [
            'reason' => 'Cancelación tardía',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id'                => $appointmentId,
                    'status'            => 'cancelled',
                    'cancelled_by'      => $this->patient->id,
                    'refund_percentage' => 0,
                    'refund_status'     => 'no_refund',
                ]
            ]);
    }

    public function test_tercero_no_autorizado_no_puede_cancelar_cita_404_por_rls(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => sprintf('[%s, %s)', now()->addHours(48)->toIso8601String(), now()->addHours(49)->toIso8601String()),
            'status'     => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Intento de cancelación por otro paciente (RLS devuelve 0 filas -> 404)
        $this->actingAs($this->otherPatient);

        $response = $this->postJson("/api/appointments/{$appointmentId}/cancel", [
            'reason' => 'Intento malicioso',
        ]);

        $response->assertStatus(404)
            ->assertJson(['error_code' => 'APPOINTMENT_NOT_FOUND']);
    }

    public function test_cancelar_cita_ya_cancelada_retorna_409(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'           => $appointmentId,
            'patient_id'   => $this->patient->id,
            'doctor_id'    => $this->doctor->id,
            'franja'       => sprintf('[%s, %s)', now()->addHours(48)->toIso8601String(), now()->addHours(49)->toIso8601String()),
            'status'       => 'cancelled',
            'cancelled_by' => $this->patient->id,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $this->actingAs($this->patient);

        $response = $this->postJson("/api/appointments/{$appointmentId}/cancel");

        $response->assertStatus(409)
            ->assertJson(['error_code' => 'INVALID_APPOINTMENT_STATUS']);
    }
}
