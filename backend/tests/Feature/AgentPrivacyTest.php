<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * RF-10: Privacidad del agente administrativo.
 * El agente SÍ accede a datos de contacto (patient_profiles).
 * El agente NO accede a datos clínicos (allergies, conditions, medications, notes).
 *
 * Regla de fixtures:
 *  - Fixture-1: pgsql_migration  → estado preexistente que ningún rol ejecuta
 *  - Fixture-2: app_runtime + contexto → acción declarada en el PRD
 *  - Punto 5: limpiar app.current_user_id antes de aserciones
 */

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\DoctorProfile;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AgentPrivacyTest extends TestCase
{
    private User $patient;
    private User $doctor;
    private User $agent;
    private Role $patientRole;
    private Role $doctorRole;
    private Role $agentRole;
    private string $appointmentId;

    protected function setUp(): void
    {
        parent::setUp();

        $mc = DB::connection('pgsql_migration');

        // Roles (Fixture-1: pgsql_migration — roles table only grants SELECT to app_runtime)
        $this->patientRole = Role::where('name', 'patient')->first();
        if (!$this->patientRole) {
            $mc->table('roles')->insert(['id' => Str::uuid()->toString(), 'name' => 'patient', 'description' => 'Paciente', 'created_at' => now(), 'updated_at' => now()]);
            $this->patientRole = Role::where('name', 'patient')->first();
        }
        $this->doctorRole = Role::where('name', 'doctor')->first();
        if (!$this->doctorRole) {
            $mc->table('roles')->insert(['id' => Str::uuid()->toString(), 'name' => 'doctor', 'description' => 'Médico', 'created_at' => now(), 'updated_at' => now()]);
            $this->doctorRole = Role::where('name', 'doctor')->first();
        }
        $this->agentRole = Role::where('name', 'agent')->first();
        if (!$this->agentRole) {
            $mc->table('roles')->insert(['id' => Str::uuid()->toString(), 'name' => 'agent', 'description' => 'Agente', 'created_at' => now(), 'updated_at' => now()]);
            $this->agentRole = Role::where('name', 'agent')->first();
        }

        // Usuarios
        $this->patient = User::factory()->create();
        $mc->table('user_roles')->insert(['user_id' => $this->patient->id, 'role_id' => $this->patientRole->id]);

        $this->doctor = User::factory()->create();
        $mc->table('user_roles')->insert(['user_id' => $this->doctor->id, 'role_id' => $this->doctorRole->id]);

        $this->agent = User::factory()->create();
        $mc->table('user_roles')->insert(['user_id' => $this->agent->id, 'role_id' => $this->agentRole->id]);

        // Perfil médico: fixture preexistente (Fixture-1: pgsql_migration — doctor_profiles has RLS)
        $mc->table('doctor_profiles')->insert([
            'id'               => Str::uuid()->toString(),
            'user_id'          => $this->doctor->id,
            'license_number'   => 'LIC-' . Str::random(5),
            'university'       => 'Universidad Nacional',
            'years_experience' => 5,
            'description'      => 'Médico test',
            'consultation_fee' => 50.00,
            'status'           => 'approved',
            'approved_at'      => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // PatientProfile: acción del agente (DECISIONES_ALCANCE:71 — "el agente crea pacientes")
        // Fixture-2: corre como agent con contexto RLS
        $conn = DB::connection('pgsql');
        $conn->statement("SET app.current_user_id = '{$this->agent->id}'");
        $conn->statement("SET app.current_user_role = 'agent'");

        DB::connection('pgsql')->table('patient_profiles')->insert([
            'id'            => Str::uuid()->toString(),
            'user_id'       => $this->patient->id,
            'phone'         => '123456789',
            'date_of_birth' => '1990-01-01',
            'gender'        => 'other',
            'address'       => 'Test Address 123',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Limpiar contexto de agente antes de seguir
        $conn->statement("SET app.current_user_id = ''");
        $conn->statement("SET app.current_user_role = ''");

        // Horario: fixture preexistente (Fixture-1: pgsql_migration — schedules has RLS)
        $this->doctor->load('doctorProfile');
        $mc->table('schedules')->insert([
            'id'                => Str::uuid()->toString(),
            'doctor_profile_id' => $this->doctor->doctorProfile->id,
            'day_of_week'       => 1,
            'franja'            => '[09:00:00, 12:00:00)',
            'slot_duration'     => 30,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // Cita confirmada: fixture preexistente (estado del sistema, no acción del agente)
        $this->appointmentId = Str::uuid()->toString();
        $mc->table('appointments')->insert([
            'id'                       => $this->appointmentId,
            'patient_id'               => $this->patient->id,
            'doctor_id'                => $this->doctor->id,
            'franja'                   => '[2026-08-10 09:00:00+00, 2026-08-10 09:30:00+00)',
            'status'                   => 'confirmed',
            'idempotency_key'          => Str::uuid()->toString(),
            'idempotency_payload_hash' => hash('sha256', 'fixture'),
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);
    }

    protected function tearDown(): void
    {
        // Limpiar contexto RLS de la conexión default
        DB::connection('pgsql')->statement("SELECT set_config('app.current_user_id', '', false)");
        DB::connection('pgsql')->statement("SELECT set_config('app.current_user_role', '', false)");

        parent::tearDown();
    }

    /**
     * Crea una conexión PDO directa como app_runtime con contexto RLS.
     */
    private function createRlsPdo(string $userId, string $role): \PDO
    {
        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new \PDO($dsn, $config['username'], $config['password']);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $pdo->exec("SET app.current_user_id = '{$userId}'");
        $pdo->exec("SET app.current_user_role = '{$role}'");

        return $pdo;
    }

    // ====================================================================
    // SECCIÓN 1: EL AGENTE SÍ LEE DATOS DE CONTACTO
    // ====================================================================

    /**
     * El agente PUEDE leer patient_profiles (datos de contacto, no clínicos).
     * DECISIONES_ALCANCE:71 — el agente gestiona "datos de contacto".
     */
    public function test_agent_puede_leer_patient_profiles(): void
    {
        $pdo = $this->createRlsPdo($this->agent->id, 'agent');

        $stmt = $pdo->query("SELECT count(*) FROM patient_profiles");
        $count = (int) $stmt->fetchColumn();

        $this->assertGreaterThan(0, $count, 'El agente debe poder leer patient_profiles');
    }

    /**
     * El agente reserva una cita y la respuesta contiene SOLO campos de agenda.
     */
    public function test_agent_books_appointment_response_contains_only_agenda_fields(): void
    {
        $this->actingAs($this->agent);

        $response = $this->withHeader('X-Idempotency-Key', Str::uuid()->toString())
            ->postJson('/api/appointments', [
                'patient_id'    => $this->patient->id,
                'doctor_id'     => $this->doctor->id,
                'franja_inicio' => '2026-08-17 09:00:00',
                'franja_fin'    => '2026-08-17 09:30:00',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'patient_id', 'doctor_id', 'franja', 'status', 'created_at', 'updated_at']
            ]);

        $responseData = $response->json('data');
        $this->assertCount(7, $responseData);

        $forbiddenKeys = ['symptoms', 'diagnosis', 'notes', 'allergies', 'medications',
                          'conditions', 'blood_type', 'content_hash'];
        foreach ($forbiddenKeys as $key) {
            $this->assertArrayNotHasKey($key, $responseData);
        }
    }

    // ====================================================================
    // SECCIÓN 2: EL AGENTE NO LEE DATOS CLÍNICOS
    // ====================================================================

    /**
     * El agente NO puede leer patient_allergies.
     */
    public function test_agent_no_lee_patient_allergies_via_rls(): void
    {
        // Insertar alergia como fixture preexistente
        $mc = DB::connection('pgsql_migration');
        $patientProfileId = $mc->table('patient_profiles')
            ->where('user_id', $this->patient->id)->value('id');

        $mc->table('patient_allergies')->insert([
            'id'                 => Str::uuid()->toString(),
            'patient_profile_id' => $patientProfileId,
            'substance'          => 'Penicilina',
            'type'               => 'medicamento',
            'text_severity'      => 'severe',
            'reaction'           => 'Anafilaxia',
            'declarada_por'      => $this->patient->id,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $pdo = $this->createRlsPdo($this->agent->id, 'agent');

        $stmt = $pdo->query("SELECT count(*) FROM patient_allergies");
        $count = (int) $stmt->fetchColumn();

        $this->assertEquals(0, $count, 'El agente NO debe leer patient_allergies');
    }

    /**
     * El agente NO puede leer patient_conditions.
     */
    public function test_agent_no_lee_patient_conditions_via_rls(): void
    {
        $mc = DB::connection('pgsql_migration');
        $patientProfileId = $mc->table('patient_profiles')
            ->where('user_id', $this->patient->id)->value('id');

        $mc->table('patient_conditions')->insert([
            'id'                 => Str::uuid()->toString(),
            'patient_profile_id' => $patientProfileId,
            'condition'          => 'Diabetes tipo 2',
            'desde'              => '2020-01-01',
            'status'             => 'activa',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $pdo = $this->createRlsPdo($this->agent->id, 'agent');

        $stmt = $pdo->query("SELECT count(*) FROM patient_conditions");
        $count = (int) $stmt->fetchColumn();

        $this->assertEquals(0, $count, 'El agente NO debe leer patient_conditions');
    }

    /**
     * El agente NO puede leer patient_medications.
     */
    public function test_agent_no_lee_patient_medications_via_rls(): void
    {
        $mc = DB::connection('pgsql_migration');
        $patientProfileId = $mc->table('patient_profiles')
            ->where('user_id', $this->patient->id)->value('id');

        $mc->table('patient_medications')->insert([
            'id'                 => Str::uuid()->toString(),
            'patient_profile_id' => $patientProfileId,
            'name'               => 'Metformina',
            'dosis'              => '500mg',
            'frecuencia'         => 'cada 12 horas',
            'desde'              => '2021-06-01',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $pdo = $this->createRlsPdo($this->agent->id, 'agent');

        $stmt = $pdo->query("SELECT count(*) FROM patient_medications");
        $count = (int) $stmt->fetchColumn();

        $this->assertEquals(0, $count, 'El agente NO debe leer patient_medications');
    }

    /**
     * El agente NO puede leer consultation_notes.
     */
    public function test_agent_no_lee_consultation_notes_via_rls(): void
    {
        $mc = DB::connection('pgsql_migration');
        $consultationId = Str::uuid()->toString();
        $mc->table('consultations')->insert([
            'id'             => $consultationId,
            'appointment_id' => $this->appointmentId,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
        $mc->table('consultation_notes')->insert([
            'id'              => Str::uuid()->toString(),
            'consultation_id' => $consultationId,
            'symptoms'        => 'test',
            'objective'       => 'test',
            'analysis'        => 'test',
            'plan'            => 'test',
            'status'          => 'signed',
        ]);

        $pdo = $this->createRlsPdo($this->agent->id, 'agent');

        $stmt = $pdo->query("SELECT count(*) FROM consultation_notes");
        $count = (int) $stmt->fetchColumn();

        $this->assertEquals(0, $count, 'El agente NO debe leer consultation_notes');
    }
}
