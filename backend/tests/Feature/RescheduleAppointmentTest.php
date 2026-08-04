<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RescheduleAppointmentTest extends TestCase
{
    private User $patient;
    private User $doctor;
    private User $agent;
    private User $otherPatient;
    private Role $patientRole;
    private Role $doctorRole;
    private Role $agentRole;

    protected function setUp(): void
    {
        parent::setUp();

        $mc = DB::connection('pgsql_migration');
        $this->patientRole = Role::where('name', 'patient')->first();
        $this->doctorRole  = Role::where('name', 'doctor')->first();
        $this->agentRole   = Role::where('name', 'agent')->first();

        $this->patient = User::factory()->create(['timezone' => 'UTC']);
        $mc->table('user_roles')->insert(['user_id' => $this->patient->id, 'role_id' => $this->patientRole->id]);

        $this->otherPatient = User::factory()->create(['timezone' => 'UTC']);
        $mc->table('user_roles')->insert(['user_id' => $this->otherPatient->id, 'role_id' => $this->patientRole->id]);

        $this->doctor = User::factory()->create(['timezone' => 'UTC']);
        $mc->table('user_roles')->insert(['user_id' => $this->doctor->id, 'role_id' => $this->doctorRole->id]);

        $this->agent = User::factory()->create(['timezone' => 'UTC']);
        $mc->table('user_roles')->insert(['user_id' => $this->agent->id, 'role_id' => $this->agentRole->id]);

        $mc->table('doctor_profiles')->insert([
            'id'               => Str::uuid()->toString(),
            'user_id'          => $this->doctor->id,
            'license_number'   => 'LIC-RESCHED-' . Str::random(4),
            'university'       => 'Universidad Central',
            'years_experience' => 10,
            'description'      => 'Médico de prueba',
            'consultation_fee' => 100.00,
            'status'           => 'approved',
            'approved_at'      => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function test_paciente_o_agente_solicita_reprogramacion_exito(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-10 08:00:00+00, 2026-08-10 08:30:00+00)',
            'status'     => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->patient);

        $response = $this->postJson("/api/appointments/{$appointmentId}/reschedule-request", [
            'nueva_franja_inicio' => '2026-08-11T09:00:00Z',
            'nueva_franja_fin'    => '2026-08-11T09:30:00Z',
            'motivo'              => 'Conflicto laboral',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'appointment_id' => $appointmentId,
                'status'         => 'pending',
                'reason'         => 'Conflicto laboral',
            ]);

        $this->assertDatabaseHas('reschedule_requests', [
            'appointment_id' => $appointmentId,
            'status'         => 'pending',
        ]);
    }

    public function test_solicitud_reprogramacion_duplicada_retorna_409(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-10 08:00:00+00, 2026-08-10 08:30:00+00)',
            'status'     => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->patient);

        $payload = [
            'nueva_franja_inicio' => '2026-08-11T09:00:00Z',
            'nueva_franja_fin'    => '2026-08-11T09:30:00Z',
        ];

        $this->postJson("/api/appointments/{$appointmentId}/reschedule-request", $payload)->assertStatus(201);
        $resDuplicate = $this->postJson("/api/appointments/{$appointmentId}/reschedule-request", $payload);

        $resDuplicate->assertStatus(409)
            ->assertJson(['error_code' => 'RESCHEDULE_ALREADY_PENDING']);
    }

    public function test_medico_aprueba_reprogramacion_exito_transaccion_unica(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();
        $requestId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-10 08:00:00+00, 2026-08-10 08:30:00+00)',
            'status'     => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mc->table('reschedule_requests')->insert([
            'id'               => $requestId,
            'appointment_id'   => $appointmentId,
            'doctor_id'        => $this->doctor->id,
            'requested_by'     => $this->patient->id,
            'requested_franja' => '[2026-08-11 09:00:00+00, 2026-08-11 09:30:00+00)',
            'status'           => 'pending',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->actingAs($this->doctor);

        $response = $this->putJson("/api/appointments/{$appointmentId}/reschedule-approve");

        $response->assertStatus(200)
            ->assertJson([
                'reschedule_request' => [
                    'id'          => $requestId,
                    'status'      => 'approved',
                    'resolved_by' => $this->doctor->id,
                ],
                'cita_original_cancelada' => [
                    'id'     => $appointmentId,
                    'status' => 'cancelled',
                ],
                'nueva_cita_confirmada' => [
                    'patient_id' => $this->patient->id,
                    'doctor_id'  => $this->doctor->id,
                    'status'     => 'confirmed',
                ]
            ]);

        $this->assertDatabaseHas('appointments', [
            'id'     => $appointmentId,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('appointments', [
            'rescheduled_from' => $appointmentId,
            'status'           => 'confirmed',
        ]);
    }

    public function test_medico_rechaza_reprogramacion_exito(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-10 08:00:00+00, 2026-08-10 08:30:00+00)',
            'status'     => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mc->table('reschedule_requests')->insert([
            'id'               => Str::uuid()->toString(),
            'appointment_id'   => $appointmentId,
            'doctor_id'        => $this->doctor->id,
            'requested_by'     => $this->patient->id,
            'requested_franja' => '[2026-08-11 09:00:00+00, 2026-08-11 09:30:00+00)',
            'status'           => 'pending',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->actingAs($this->doctor);

        $response = $this->putJson("/api/appointments/{$appointmentId}/reschedule-reject", [
            'motivo_rechazo' => 'Sin disponibilidad',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'           => 'rejected',
                'rejection_reason' => 'Sin disponibilidad',
            ]);

        // La cita original permanece intacta
        $this->assertDatabaseHas('appointments', [
            'id'     => $appointmentId,
            'status' => 'confirmed',
        ]);
    }

    public function test_agente_no_puede_aprobar_reprogramacion_403(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-10 08:00:00+00, 2026-08-10 08:30:00+00)',
            'status'     => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mc->table('reschedule_requests')->insert([
            'id'               => Str::uuid()->toString(),
            'appointment_id'   => $appointmentId,
            'doctor_id'        => $this->doctor->id,
            'requested_by'     => $this->patient->id,
            'requested_franja' => '[2026-08-11 09:00:00+00, 2026-08-11 09:30:00+00)',
            'status'           => 'pending',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->actingAs($this->agent);

        $response = $this->putJson("/api/appointments/{$appointmentId}/reschedule-approve");

        $response->assertStatus(403)
            ->assertJson(['error_code' => 'AGENT_CANNOT_APPROVE_RESCHEDULE']);
    }

    public function test_falla_reprogramacion_si_nuevo_slot_se_ocupa_en_el_intertanto_rollback_409(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-10 08:00:00+00, 2026-08-10 08:30:00+00)',
            'status'     => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mc->table('reschedule_requests')->insert([
            'id'               => Str::uuid()->toString(),
            'appointment_id'   => $appointmentId,
            'doctor_id'        => $this->doctor->id,
            'requested_by'     => $this->patient->id,
            'requested_franja' => '[2026-08-11 09:00:00+00, 2026-08-11 09:30:00+00)',
            'status'           => 'pending',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // Cita intermitente reservada por otro paciente en ese mismo slot nuevo
        $mc->table('appointments')->insert([
            'id'         => Str::uuid()->toString(),
            'patient_id' => $this->otherPatient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-11 09:00:00+00, 2026-08-11 09:30:00+00)',
            'status'     => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->doctor);

        $response = $this->putJson("/api/appointments/{$appointmentId}/reschedule-approve");

        $response->assertStatus(409)
            ->assertJson(['error_code' => 'SLOT_ALREADY_BOOKED']);

        // Rollback completo: la cita original sigue confirmada
        $this->assertDatabaseHas('appointments', [
            'id'     => $appointmentId,
            'status' => 'confirmed',
        ]);
    }
}
