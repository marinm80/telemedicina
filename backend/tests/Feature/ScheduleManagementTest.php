<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ScheduleManagementTest extends TestCase
{
    private User $doctor;
    private Role $doctorRole;
    private string $doctorProfileId;

    protected function setUp(): void
    {
        parent::setUp();

        $mc = DB::connection('pgsql_migration');
        $this->doctorRole = Role::where('name', 'doctor')->first();

        $this->doctor = User::factory()->create(['timezone' => 'UTC']);
        $mc->table('user_roles')->insert(['user_id' => $this->doctor->id, 'role_id' => $this->doctorRole->id]);

        $this->doctorProfileId = Str::uuid()->toString();
        $mc->table('doctor_profiles')->insert([
            'id'               => $this->doctorProfileId,
            'user_id'          => $this->doctor->id,
            'license_number'   => 'LIC-TEST-' . Str::random(4),
            'university'       => 'Universidad Central',
            'years_experience' => 8,
            'description'      => 'Médico de prueba',
            'consultation_fee' => 60.00,
            'status'           => 'approved',
            'approved_at'      => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
        $this->doctor->load('doctorProfile');
    }

    public function test_crear_franja_recurrente_exito(): void
    {
        $this->actingAs($this->doctor);

        $response = $this->postJson('/api/schedules', [
            'day_of_week'   => 2,
            'inicio'        => '09:00:00',
            'fin'           => '13:00:00',
            'slot_duration' => 30,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'doctor_profile_id', 'day_of_week', 'franja']]);

        $this->assertDatabaseHas('schedules', [
            'doctor_profile_id' => $this->doctorProfileId,
            'day_of_week'       => 2,
        ]);
    }

    public function test_crear_franja_recurrente_duplicada_retorna_409(): void
    {
        $this->actingAs($this->doctor);

        $payload = [
            'day_of_week'   => 3,
            'inicio'        => '10:00:00',
            'fin'           => '14:00:00',
            'slot_duration' => 30,
        ];

        $this->postJson('/api/schedules', $payload)->assertStatus(201);
        $resDuplicate = $this->postJson('/api/schedules', $payload);

        $resDuplicate->assertStatus(409)
            ->assertJson(['error_code' => 'SCHEDULE_ALREADY_EXISTS']);
    }

    public function test_borrar_franja_recurrente_con_citas_futuras_retorna_afectadas(): void
    {
        $this->actingAs($this->doctor);
        $mc = DB::connection('pgsql_migration');

        // Crear schedule
        $scheduleId = Str::uuid()->toString();
        $mc->table('schedules')->insert([
            'id'                => $scheduleId,
            'doctor_profile_id' => $this->doctorProfileId,
            'day_of_week'       => 1, // Lunes
            'franja'            => '[08:00:00, 12:00:00)',
            'slot_duration'     => 30,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // Crear paciente y cita futura confirmada
        $paciente = User::factory()->create();
        $mc->table('user_roles')->insert(['user_id' => $paciente->id, 'role_id' => Role::where('name', 'patient')->first()->id]);

        $appointmentId = Str::uuid()->toString();
        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $paciente->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-10 09:00:00+00, 2026-08-10 09:30:00+00)', // Lunes 10 de Agosto 2026
            'status'     => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->deleteJson("/api/schedules/{$scheduleId}");

        $response->assertStatus(200)
            ->assertJson([
                'deleted'                     => true,
                'affected_appointments_count' => 1,
            ]);

        $this->assertSoftDeleted('schedules', ['id' => $scheduleId]);
    }

    public function test_crear_bloqueo_agenda_exito(): void
    {
        $this->actingAs($this->doctor);

        $response = $this->postJson('/api/schedule-blocks', [
            'blocked_date' => '2026-08-15',
            'inicio'       => '14:00:00',
            'fin'          => '17:00:00',
            'reason'       => 'Congreso Médico',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'doctor_profile_id', 'blocked_date', 'reason']]);

        $this->assertDatabaseHas('schedule_blocks', [
            'doctor_profile_id' => $this->doctorProfileId,
            'blocked_date'      => '2026-08-15',
            'reason'            => 'Congreso Médico',
        ]);
    }

    public function test_crear_bloqueo_agenda_solapado_con_cita_retorna_409_por_trigger(): void
    {
        $this->actingAs($this->doctor);
        $mc = DB::connection('pgsql_migration');

        // Paciente y cita confirmada
        $paciente = User::factory()->create();
        $mc->table('user_roles')->insert(['user_id' => $paciente->id, 'role_id' => Role::where('name', 'patient')->first()->id]);

        $mc->table('appointments')->insert([
            'id'         => Str::uuid()->toString(),
            'patient_id' => $paciente->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-20 15:00:00+00, 2026-08-20 15:30:00+00)',
            'status'     => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Intentar crear bloqueo que solapa con esa cita
        $response = $this->postJson('/api/schedule-blocks', [
            'blocked_date' => '2026-08-20',
            'inicio'       => '14:00:00',
            'fin'          => '16:00:00',
            'reason'       => 'Personal',
        ]);

        $response->assertStatus(409)
            ->assertJson(['error_code' => 'APPOINTMENT_OVERLAP_CONFLICT']);
    }

    public function test_borrar_bloqueo_agenda_exito(): void
    {
        $this->actingAs($this->doctor);
        $mc = DB::connection('pgsql_migration');

        $blockId = Str::uuid()->toString();
        $mc->table('schedule_blocks')->insert([
            'id'                => $blockId,
            'doctor_profile_id' => $this->doctorProfileId,
            'blocked_date'      => '2026-08-25',
            'franja'            => '[09:00:00, 11:00:00)',
            'reason'            => 'Vacaciones',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $response = $this->deleteJson("/api/schedule-blocks/{$blockId}");

        $response->assertStatus(200)
            ->assertJson(['deleted' => true]);

        $this->assertDatabaseMissing('schedule_blocks', ['id' => $blockId]);
    }
}
