<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AssistantTest extends TestCase
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

        // Perfil de médico aprobado en v_doctor_directory
        $mc->table('doctor_profiles')->insert([
            'id'                => Str::uuid()->toString(),
            'user_id'           => $this->doctor->id,
            'license_number'    => 'MED-998877',
            'university'        => 'Universidad Central',
            'years_experience'  => 10,
            'description'       => 'Especialista en Cardiología y salud cardiovascular.',
            'consultation_fee'  => 50.00,
            'status'            => 'approved',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    public function test_visitante_landing_consulta_especialidades_recibe_respuesta_dinamica_y_guiado(): void
    {
        $response = $this->postJson('/api/assistant/public', [
            'query' => '¿Qué especialidades tienen?',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'suggested_action' => 'register_or_login',
            ]);

        $this->assertStringContainsString('especialidades', mb_strtolower((string) $response->json('reply')));
        $this->assertStringContainsString('registrarte o iniciar sesión', mb_strtolower((string) $response->json('reply')));
    }

    public function test_visitante_landing_consulta_horario_atencion(): void
    {
        $response = $this->postJson('/api/assistant/public', [
            'query' => '¿Cuál es el horario de atención?',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'suggested_action' => 'register_or_login',
            ]);

        $this->assertStringContainsString('24 horas', (string) $response->json('reply'));
        $this->assertStringContainsString('08:00 a 20:00', (string) $response->json('reply'));
    }

    public function test_visitante_landing_consulta_direccion_ubicacion(): void
    {
        $response = $this->postJson('/api/assistant/public', [
            'query' => '¿Dónde están ubicados?',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'suggested_action' => 'register_or_login',
            ]);

        $this->assertStringContainsString('Andrés Bello', (string) $response->json('reply'));
        $this->assertStringContainsString('100% online', (string) $response->json('reply'));
    }

    public function test_visitante_landing_consulta_como_agendar(): void
    {
        $response = $this->postJson('/api/assistant/public', [
            'query' => '¿Cómo agendar una cita?',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'suggested_action' => 'register_or_login',
            ]);

        $this->assertStringContainsString('Regístrate o inicia sesión', (string) $response->json('reply'));
    }

    public function test_visitante_landing_interactua_con_asistente_informativo_lectura_pura_sin_escritura(): void
    {
        $response = $this->postJson('/api/assistant/public', [
            'query'     => '¿Hay cardiólogos disponibles hoy?',
            'specialty' => 'Cardiología',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'suggested_action' => 'register_or_login',
            ]);

        $this->assertNotEmpty($response->json('doctors'));
        $this->assertStringContainsString('Cardiología', (string) $response->json('reply'));
        $this->assertStringContainsString('regístrate o inicia sesión', (string) $response->json('reply'));
    }

    public function test_asistente_clinico_rechaza_interaccion_si_hay_consulta_en_curso_409_conflict(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();
        $consultationId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-09-03 10:00:00+00, 2026-09-03 10:30:00+00)',
            'status'     => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mc->table('consultations')->insert([
            'id'             => $consultationId,
            'appointment_id' => $appointmentId,
            'started_at'     => now(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->actingAs($this->patient);

        $response = $this->postJson('/api/assistant/clinical', [
            'query' => 'Tengo una duda sobre mi síntoma.',
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'error_code' => 'ASSISTANT_DISABLED_DURING_CONSULTATION',
            ]);
    }

    public function test_asistente_clinico_permite_interaccion_cuando_no_hay_consulta_activa(): void
    {
        $this->actingAs($this->patient);

        $response = $this->postJson('/api/assistant/clinical', [
            'query' => '¿Cuál es mi próximo control?',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'     => 'active',
                'patient_id' => $this->patient->id,
            ]);
    }

    public function test_intento_de_escribir_nota_soap_por_paciente_es_bloqueado_por_rls(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();
        $consultationId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-09-03 12:00:00+00, 2026-09-03 12:30:00+00)',
            'status'     => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mc->table('consultations')->insert([
            'id'             => $consultationId,
            'appointment_id' => $appointmentId,
            'started_at'     => now(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->actingAs($this->patient);

        $this->expectException(QueryException::class);

        // Intento directo de inserción en consultation_notes bajo la sesión del paciente
        DB::table('consultation_notes')->insert([
            'id'              => Str::uuid()->toString(),
            'consultation_id' => $consultationId,
            'symptoms'        => 'Intento ilegal de nota por paciente',
            'objective'       => 'Objetivo',
            'analysis'        => 'Análisis',
            'plan'            => 'Plan',
            'status'          => 'draft',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }
}
