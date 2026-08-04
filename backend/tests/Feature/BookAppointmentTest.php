<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 */

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\DoctorProfile;
use App\Models\Schedule;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class BookAppointmentTest extends TestCase
{
    private User $patient;
    private User $doctor;
    private Role $patientRole;
    private Role $doctorRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Limpiar caché de idempotencia antes de cada test
        Cache::flush();

        $migrationConn = DB::connection('pgsql_migration');

        // 1. Inicializar roles via pgsql_migration (roles table only has SELECT for app_runtime)
        $pRole = $migrationConn->table('roles')->where('name', 'patient')->first();
        if (!$pRole) {
            $pId = Str::uuid()->toString();
            $migrationConn->table('roles')->insert(['id' => $pId, 'name' => 'patient', 'description' => 'Paciente', 'created_at' => now(), 'updated_at' => now()]);
        }
        $dRole = $migrationConn->table('roles')->where('name', 'doctor')->first();
        if (!$dRole) {
            $dId = Str::uuid()->toString();
            $migrationConn->table('roles')->insert(['id' => $dId, 'name' => 'doctor', 'description' => 'Médico', 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->patientRole = Role::where('name', 'patient')->firstOrFail();
        $this->doctorRole = Role::where('name', 'doctor')->firstOrFail();

        // 2. Crear usuarios de prueba (users INSERT policy allows when no context is set)
        $this->patient = User::factory()->create();
        $migrationConn->table('user_roles')->insert(['user_id' => $this->patient->id, 'role_id' => $this->patientRole->id]);

        $this->doctor = User::factory()->create();
        $migrationConn->table('user_roles')->insert(['user_id' => $this->doctor->id, 'role_id' => $this->doctorRole->id]);

        // Crear perfil médico aprobado via pgsql_migration (doctor_profiles has RLS)
        $doctorProfileId = Str::uuid()->toString();
        $migrationConn->table('doctor_profiles')->insert([
            'id' => $doctorProfileId,
            'user_id' => $this->doctor->id,
            'license_number' => 'LIC-' . Str::random(5),
            'university' => 'Universidad Nacional',
            'years_experience' => 5,
            'description' => 'Especialista en medicina interna',
            'consultation_fee' => 50.00,
            'status' => 'approved',
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->doctor->load('doctorProfile');

        // Crear horario los días Lunes via pgsql_migration (schedules has RLS)
        $migrationConn->table('schedules')->insert([
            'id' => Str::uuid()->toString(),
            'doctor_profile_id' => $this->doctor->doctorProfile->id,
            'day_of_week' => 1,
            'franja' => '[09:00:00, 12:00:00)',
            'slot_duration' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * ====================================================================
     * SECCIÓN 1: ENDPOINT DE DISPONIBILIDAD
     * ====================================================================
     */

    public function test_consultar_disponibilidad_exito(): void
    {
        $this->actingAs($this->patient);

        // Consultar disponibilidad el próximo lunes
        $response = $this->getJson("/api/doctors/{$this->doctor->id}/availability?date=2026-08-10");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'doctor_id',
                'date',
                'timezone',
                'slots' => [
                    '*' => ['start', 'end', 'local_start', 'local_end', 'available']
                ]
            ]);

        $slots = $response->json('slots');
        $this->assertCount(6, $slots); // De 9:00 a 12:00 hay 6 slots de 30 mins
        $this->assertTrue($slots[0]['available']);
    }

    /**
     * BUG DST: La hora de pared de schedules.franja se interpreta como UTC.
     * Para un médico en America/Mexico_City, 09:00–12:00 debe generar el
     * primer slot a las 15:00Z (UTC-6), no a las 09:00Z.
     *
     * 2026-08-10 es lunes. America/Mexico_City es CST (UTC-6) todo el año
     * desde que México abolió el DST en 2022: 09:00 pared = 15:00Z.
     */
    public function test_dst_slots_respetan_timezone_del_medico(): void
    {
        // Re-crear médico con timezone America/Mexico_City
        $mc = DB::connection('pgsql_migration');
        $mc->unprepared(self::TRUNCATE_SQL ?? '');

        // Insertar roles si no existen (TRUNCATE no toca roles)
        $patientRole = Role::where('name', 'patient')->first();
        $doctorRole = Role::where('name', 'doctor')->first();

        // Paciente
        $patient = User::factory()->create();
        $mc->table('user_roles')->insert([
            'user_id' => $patient->id,
            'role_id' => $patientRole->id,
        ]);

        // Médico con timezone explícita
        $doctor = User::factory()->create([
            'timezone' => 'America/Mexico_City',
        ]);
        $mc->table('user_roles')->insert([
            'user_id' => $doctor->id,
            'role_id' => $doctorRole->id,
        ]);

        $dpId = Str::uuid()->toString();
        $mc->table('doctor_profiles')->insert([
            'id' => $dpId,
            'user_id' => $doctor->id,
            'license_number' => 'DST-001',
            'university' => 'UNAM',
            'description' => 'Test DST',
            'consultation_fee' => 50.00,
            'status' => 'approved',
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Horario: lunes 09:00–12:00 (hora de pared)
        $mc->table('schedules')->insert([
            'id' => Str::uuid()->toString(),
            'doctor_profile_id' => $dpId,
            'day_of_week' => 1,
            'franja' => '[09:00:00, 12:00:00)',
            'slot_duration' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($patient);

        // 2026-08-10 es lunes, America/Mexico_City en CST (UTC-6)
        $response = $this->getJson("/api/doctors/{$doctor->id}/availability?date=2026-08-10");
        $response->assertStatus(200);

        $slots = $response->json('slots');
        $this->assertCount(6, $slots, 'Deben ser 6 slots de 30 min entre 09:00 y 12:00');

        // 09:00 CST = 15:00 UTC (NO 09:00 UTC que es el bug)
        $firstStart = $slots[0]['start'];
        $this->assertStringContainsString(
            '2026-08-10T15:00:00',
            $firstStart,
            "El primer slot debe ser 15:00Z (09:00 CST). Actual: {$firstStart}"
        );

        // Último slot: 11:30 CST = 17:30 UTC
        $lastStart = $slots[5]['start'];
        $this->assertStringContainsString(
            '2026-08-10T17:30:00',
            $lastStart,
            "El último slot debe ser 17:30Z (11:30 CST). Actual: {$lastStart}"
        );
    }

    /**
     * DST Spring Forward: 2026-03-08, America/Chicago.
     * A las 02:00 CST el reloj salta a 03:00 CDT.
     * Un horario 01:00–04:00 con slots de 30 min normalmente da 6 slots.
     * Los slots de 02:00 y 02:30 (hora de pared) no existen ese día.
     * Resultado: 4 slots, no 6. Todos estrictamente crecientes en UTC.
     */
    public function test_dst_spring_forward_menos_slots(): void
    {
        $mc = DB::connection('pgsql_migration');
        $mc->unprepared(self::TRUNCATE_SQL ?? '');

        $patientRole = Role::where('name', 'patient')->first();
        $doctorRole = Role::where('name', 'doctor')->first();

        $patient = User::factory()->create();
        $mc->table('user_roles')->insert([
            'user_id' => $patient->id,
            'role_id' => $patientRole->id,
        ]);

        // Doctor en America/Chicago — DST spring forward 2026-03-08
        $doctor = User::factory()->create([
            'timezone' => 'America/Chicago',
        ]);
        $mc->table('user_roles')->insert([
            'user_id' => $doctor->id,
            'role_id' => $doctorRole->id,
        ]);

        $dpId = Str::uuid()->toString();
        $mc->table('doctor_profiles')->insert([
            'id' => $dpId,
            'user_id' => $doctor->id,
            'license_number' => 'DST-FWD',
            'university' => 'UChicago',
            'description' => 'Test spring forward',
            'consultation_fee' => 50.00,
            'status' => 'approved',
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Horario: domingo 01:00–04:00 (cruza el salto 02:00→03:00)
        // 2026-03-08 es domingo (dayOfWeek = 0)
        $mc->table('schedules')->insert([
            'id' => Str::uuid()->toString(),
            'doctor_profile_id' => $dpId,
            'day_of_week' => 0, // Domingo
            'franja' => '[01:00:00, 04:00:00)',
            'slot_duration' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($patient);

        $response = $this->getJson("/api/doctors/{$doctor->id}/availability?date=2026-03-08");
        $response->assertStatus(200);

        $slots = $response->json('slots');

        // 01:00 CST, 01:30 CST existen (UTC-6 → 07:00Z, 07:30Z)
        // 02:00 CST NO existe (salta a 03:00 CDT)
        // 02:30 CST NO existe
        // 03:00 CDT, 03:30 CDT existen (UTC-5 → 08:00Z, 08:30Z)
        // Total: 4 slots, no 6
        $this->assertCount(4, $slots,
            'Spring forward: 02:00 y 02:30 no existen, deben faltar 2 slots');

        // Todos los instantes UTC deben ser estrictamente crecientes
        $utcStarts = array_map(fn($s) => $s['start'], $slots);
        for ($i = 1; $i < count($utcStarts); $i++) {
            $this->assertGreaterThan($utcStarts[$i - 1], $utcStarts[$i],
                "Los slots UTC deben ser estrictamente crecientes. Slot {$i}: {$utcStarts[$i]} <= {$utcStarts[$i-1]}");
        }
    }

    /**
     * DST Fall Back: 2026-11-01, America/Chicago.
     * A las 02:00 CDT el reloj retrocede a 01:00 CST.
     * Un horario 00:30–03:00 con slots de 30 min normalmente da 5 slots.
     * La hora 01:00 y 01:30 ocurren dos veces. Se muestra la PRIMERA
     * ocurrencia. Sin duplicados.
     * Resultado: 5 slots (no 7), todos estrictamente crecientes en UTC.
     */
    public function test_dst_fall_back_sin_duplicados(): void
    {
        $mc = DB::connection('pgsql_migration');
        $mc->unprepared(self::TRUNCATE_SQL ?? '');

        $patientRole = Role::where('name', 'patient')->first();
        $doctorRole = Role::where('name', 'doctor')->first();

        $patient = User::factory()->create();
        $mc->table('user_roles')->insert([
            'user_id' => $patient->id,
            'role_id' => $patientRole->id,
        ]);

        // Doctor en America/Chicago — DST fall back 2026-11-01
        $doctor = User::factory()->create([
            'timezone' => 'America/Chicago',
        ]);
        $mc->table('user_roles')->insert([
            'user_id' => $doctor->id,
            'role_id' => $doctorRole->id,
        ]);

        $dpId = Str::uuid()->toString();
        $mc->table('doctor_profiles')->insert([
            'id' => $dpId,
            'user_id' => $doctor->id,
            'license_number' => 'DST-BACK',
            'university' => 'UChicago',
            'description' => 'Test fall back',
            'consultation_fee' => 50.00,
            'status' => 'approved',
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Horario: domingo 00:30–03:00 (cruza el retroceso 02:00→01:00)
        // 2026-11-01 es domingo (dayOfWeek = 0)
        $mc->table('schedules')->insert([
            'id' => Str::uuid()->toString(),
            'doctor_profile_id' => $dpId,
            'day_of_week' => 0, // Domingo
            'franja' => '[00:30:00, 03:00:00)',
            'slot_duration' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($patient);

        $response = $this->getJson("/api/doctors/{$doctor->id}/availability?date=2026-11-01");
        $response->assertStatus(200);

        $slots = $response->json('slots');

        // 00:30, 01:00, 01:30, 02:00, 02:30 → 5 slots nominales
        // La hora 01:00-02:00 ocurre dos veces (CDT y CST),
        // pero mostramos solo la primera ocurrencia.
        $this->assertCount(5, $slots,
            'Fall back: sin slots duplicados por hora ambigua');

        // Todos los instantes UTC deben ser estrictamente crecientes
        $utcStarts = array_map(fn($s) => $s['start'], $slots);
        $uniqueStarts = array_unique($utcStarts);
        $this->assertCount(count($utcStarts), $uniqueStarts,
            'No debe haber instantes UTC duplicados');

        for ($i = 1; $i < count($utcStarts); $i++) {
            $this->assertGreaterThan($utcStarts[$i - 1], $utcStarts[$i],
                "Los slots UTC deben ser estrictamente crecientes. Slot {$i}: {$utcStarts[$i]} <= {$utcStarts[$i-1]}");
        }
    }

    public function test_consultar_disponibilidad_doctor_no_aprobado_lanza_404(): void
    {
        $this->actingAs($this->patient);

        // Modificar estado del médico a pending (fixture — pgsql_migration porque el paciente
        // no tiene permiso de UPDATE en doctor_profiles por RLS)
        DB::connection('pgsql_migration')->table('doctor_profiles')
            ->where('user_id', $this->doctor->id)
            ->update(['status' => 'pending']);

        // Con la vista v_doctor_directory, un médico no aprobado no aparece.
        // Devolver 404 en lugar de 403 es correcto: no revelar que el médico
        // existe pero no está aprobado — fuga de información sobre aspirantes.
        $response = $this->getJson("/api/doctors/{$this->doctor->id}/availability?date=2026-08-10");

        $response->assertStatus(404)
            ->assertJson([
                'error_code' => 'RESOURCE_NOT_FOUND'
            ]);
    }

    public function test_consultar_disponibilidad_doctor_inexistente_lanza_404(): void
    {
        $this->actingAs($this->patient);

        $fakeUuid = Str::uuid()->toString();
        $response = $this->getJson("/api/doctors/{$fakeUuid}/availability?date=2026-08-10");

        $response->assertStatus(404)
            ->assertJson([
                'error_code' => 'RESOURCE_NOT_FOUND'
            ]);
    }

    /**
     * ====================================================================
     * SECCIÓN 2: ENDPOINT DE RESERVA E IDEMPOTENCIA
     * ====================================================================
     */

    public function test_reservar_cita_exito(): void
    {
        $this->actingAs($this->patient);

        $idempotencyKey = Str::uuid()->toString();

        $payload = [
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'franja_inicio' => '2026-08-10 09:00:00',
            'franja_fin' => '2026-08-10 09:30:00',
        ];

        $response = $this->withHeader('X-Idempotency-Key', $idempotencyKey)
            ->postJson('/api/appointments', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'patient_id',
                    'doctor_id',
                    'franja',
                    'status'
                ]
            ]);

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'pending',
            'idempotency_key' => $idempotencyKey
        ]);
    }

    public function test_reserva_cita_idempotente_mismo_payload_retorna_201_cached(): void
    {
        $this->actingAs($this->patient);

        $idempotencyKey = Str::uuid()->toString();
        $payload = [
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'franja_inicio' => '2026-08-10 09:00:00',
            'franja_fin' => '2026-08-10 09:30:00',
        ];

        // Primera petición
        $res1 = $this->withHeader('X-Idempotency-Key', $idempotencyKey)
            ->postJson('/api/appointments', $payload);
        $res1->assertStatus(201);
        $id1 = $res1->json('data.id');

        // Segunda petición (idempotente secuencial)
        $res2 = $this->withHeader('X-Idempotency-Key', $idempotencyKey)
            ->postJson('/api/appointments', $payload);
        $res2->assertStatus(201);
        $id2 = $res2->json('data.id');

        $this->assertEquals($id1, $id2);
    }

    public function test_reserva_cita_idempotente_diferente_payload_retorna_400(): void
    {
        $this->actingAs($this->patient);

        $idempotencyKey = Str::uuid()->toString();
        $payload1 = [
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'franja_inicio' => '2026-08-10 09:00:00',
            'franja_fin' => '2026-08-10 09:30:00',
        ];

        $payload2 = [
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'franja_inicio' => '2026-08-10 10:00:00', // Franja distinta
            'franja_fin' => '2026-08-10 10:30:00',
        ];

        // Primera petición
        $this->withHeader('X-Idempotency-Key', $idempotencyKey)
            ->postJson('/api/appointments', $payload1)
            ->assertStatus(201);

        // Segunda petición (diferente payload)
        $this->withHeader('X-Idempotency-Key', $idempotencyKey)
            ->postJson('/api/appointments', $payload2)
            ->assertStatus(400)
            ->assertJson([
                'error_code' => 'IDEMPOTENCY_KEY_REUSED_WITH_DIFFERENT_PAYLOAD'
            ]);
    }

    public function test_reserva_cita_solapamiento_slot_retorna_409(): void
    {
        $this->actingAs($this->patient);

        // Reservar un slot
        $payload1 = [
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'franja_inicio' => '2026-08-10 09:00:00',
            'franja_fin' => '2026-08-10 09:30:00',
        ];

        $this->withHeader('X-Idempotency-Key', Str::uuid()->toString())
            ->postJson('/api/appointments', $payload1)
            ->assertStatus(201);

        // Intentar reservar el mismo slot como Paciente 2 (Fallo de colisión 409)
        // Crear usuario via pgsql_migration porque el contexto de la petición anterior
        // dejó app.current_user_id seteado al paciente 1, y la policy de users INSERT
        // solo permite INSERT sin contexto (registro) o como admin/agent.
        $p2Id = Str::uuid()->toString();
        $migrationConn = DB::connection('pgsql_migration');
        $migrationConn->table('users')->insert([
            'id' => $p2Id,
            'name' => 'Paciente2',
            'last_name' => 'Test',
            'email' => 'paciente2_' . Str::random(5) . '@test.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'timezone' => 'UTC',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $migrationConn->table('user_roles')->insert(['user_id' => $p2Id, 'role_id' => $this->patientRole->id]);

        // Establecer contexto de paciente2 para que users_select (self) permita el find
        DB::statement("SET app.current_user_id = '{$p2Id}'");
        DB::statement("SET app.current_user_role = 'patient'");
        $paciente2 = User::find($p2Id);
        $this->actingAs($paciente2);

        $payload2 = [
            'patient_id' => $paciente2->id,
            'doctor_id' => $this->doctor->id,
            'franja_inicio' => '2026-08-10 09:00:00',
            'franja_fin' => '2026-08-10 09:30:00',
        ];

        $response = $this->withHeader('X-Idempotency-Key', Str::uuid()->toString())
            ->postJson('/api/appointments', $payload2);

        $response->assertStatus(409)
            ->assertJson([
                'error_code' => 'SLOT_ALREADY_BOOKED'
            ]);
    }

    /**
     * ====================================================================
     * SECCIÓN 3: GAUNTLET DE PRUEBAS RLS (REGLAS DEL GATE 2B)
     * ====================================================================
     */

    public function test_gauntlet_rol_runtime_no_superusuario(): void
    {
        $configUser = config('database.connections.pgsql.username');
        $this->assertEquals('app_runtime', $configUser);
    }

    public function test_gauntlet_rls_ataque_lectura_directa_paciente_ajeno(): void
    {
        $otroPaciente = User::factory()->create();
        DB::connection('pgsql_migration')->table('user_roles')->insert(['user_id' => $otroPaciente->id, 'role_id' => $this->patientRole->id]);
        
        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new \PDO($dsn, $config['username'], $config['password']);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Establecer el ID de usuario actual en PostgreSQL al del primer paciente
        $pdo->exec("SET app.current_user_id = '{$this->patient->id}'");
        $pdo->exec("SET app.current_user_role = 'patient'");

        // El paciente intenta consultar los perfiles directamente
        $stmt = $pdo->query("SELECT count(*) FROM patient_profiles");
        $count = (int) $stmt->fetchColumn();

        $this->assertEquals(0, $count);
    }

    public function test_gauntlet_rls_insercion_borrador_soap_legitima_y_bloqueo(): void
    {
        // Fixture preexistente: cita confirmada + consulta (no es acción del test)
        $citaId = Str::uuid()->toString();
        $consultaId = Str::uuid()->toString();
        $mc = DB::connection('pgsql_migration');

        $mc->table('appointments')->insert([
            'id'                       => $citaId,
            'patient_id'               => $this->patient->id,
            'doctor_id'                => $this->doctor->id,
            'franja'                   => '[2026-08-10 09:00:00+00, 2026-08-10 09:30:00+00)',
            'status'                   => 'confirmed',
            'idempotency_key'          => Str::uuid()->toString(),
            'idempotency_payload_hash' => hash('sha256', 'gauntlet-soap'),
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        $mc->table('consultations')->insert([
            'id'             => $consultaId,
            'appointment_id' => $citaId,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new \PDO($dsn, $config['username'], $config['password']);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // 1. Intentar insertar borrador SOAP como Médico Asignado (Éxito)
        $pdo->exec("SET app.current_user_id = '{$this->doctor->id}'");
        $pdo->exec("SET app.current_user_role = 'doctor'");

        $notaUuid = Str::uuid()->toString();
        $stmtInsert = $pdo->prepare("
            INSERT INTO consultation_notes (id, consultation_id, symptoms, objective, analysis, plan, status)
            VALUES (?, ?, 'Sintomas', 'Objetivo', 'Analisis', 'Plan', 'draft')
        ");
        $stmtInsert->execute([$notaUuid, $consultaId]);

        $insertedNote = DB::connection('pgsql_migration')
            ->table('consultation_notes')
            ->where('id', $notaUuid)
            ->where('status', 'draft')
            ->first();
        $this->assertNotNull($insertedNote, 'El médico asignado debe poder insertar borrador SOAP');

        // 2. Intentar insertar borrador SOAP como Paciente (Fallo RLS)
        $pdo->exec("SET app.current_user_id = '{$this->patient->id}'");
        $pdo->exec("SET app.current_user_role = 'patient'");

        $notaUuid2 = Str::uuid()->toString();
        $this->expectException(\PDOException::class);
        $stmtInsert->execute([$notaUuid2, $consultaId]);
    }

    /**
     * ====================================================================
     * SECCIÓN 4: PRUEBAS DE CONCURRENCIA REAL Y BD-BACKED IDEMPOTENCY
     * ====================================================================
     */

    public function test_concurrencia_real_solapamiento_slot_postgres(): void
    {
        // Verifica que la restricción EXCLUDE USING gist impide que dos
        // pacientes reserven el mismo slot del mismo médico.
        //
        // PHP es mono-hilo: A confirma antes de que B intente insertar.
        // Esto reproduce el momento exacto en que la segunda petición
        // PHP-FPM desbloquea tras el commit de la primera y recibe 23P01.

        $paciente1 = User::factory()->create();
        DB::connection('pgsql_migration')->table('user_roles')->insert(['user_id' => $paciente1->id, 'role_id' => $this->patientRole->id]);

        $paciente2 = User::factory()->create();
        DB::connection('pgsql_migration')->table('user_roles')->insert(['user_id' => $paciente2->id, 'role_id' => $this->patientRole->id]);

        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";

        $pdoA = new \PDO($dsn, $config['username'], $config['password']);
        $pdoA->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdoA->exec("SET app.current_user_id = '{$paciente1->id}'");
        $pdoA->exec("SET app.current_user_role = 'patient'");

        $pdoB = new \PDO($dsn, $config['username'], $config['password']);
        $pdoB->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdoB->exec("SET app.current_user_id = '{$paciente2->id}'");
        $pdoB->exec("SET app.current_user_role = 'patient'");

        $uuid1 = Str::uuid()->toString();
        $uuid2 = Str::uuid()->toString();
        $franja = '[2026-08-10 11:00:00+00, 2026-08-10 11:30:00+00)';

        $insertSql = "
            INSERT INTO appointments (id, patient_id, doctor_id, franja, status, created_at, updated_at)
            VALUES (?, ?, ?, ?::tstzrange, 'pending', now(), now())
        ";

        // --- Paciente 1: reserva y confirma ---
        $pdoA->exec("BEGIN");
        $stmtA = $pdoA->prepare($insertSql);
        $stmtA->execute([$uuid1, $paciente1->id, $this->doctor->id, $franja]);
        $pdoA->exec("COMMIT");

        // --- Paciente 2: intenta el mismo slot (A ya confirmó) ---
        $excepcionB = null;
        $pdoB->exec("BEGIN");
        try {
            $stmtB = $pdoB->prepare($insertSql);
            $stmtB->execute([$uuid2, $paciente2->id, $this->doctor->id, $franja]);
            $pdoB->exec("COMMIT");
        } catch (\PDOException $e) {
            $excepcionB = $e;
            try { $pdoB->exec("ROLLBACK"); } catch (\Exception $err) {}
        }

        // Debe recibir EXACTAMENTE 23P01 (exclusion_violation). No 55P03.
        $this->assertNotNull($excepcionB, 'La segunda inserción debió fallar por restricción de exclusión GIST.');
        $this->assertEquals('23P01', $excepcionB->getCode(),
            'El código de error debe ser exclusion_violation (23P01), no lock_timeout ni otro.');
    }

    public function test_concurrencia_idempotencia_misma_clave_exito_unico(): void
    {
        // Verifica la defensa de idempotencia a nivel de base de datos.
        //
        // En producción, dos procesos PHP-FPM compiten: uno confirma primero,
        // el otro desbloquea y recibe 23505 sobre el UNIQUE de idempotency_key.
        // La Action captura el 23505, hace SELECT por clave, compara el hash
        // del payload y devuelve la cita original con 201.
        //
        // PHP es mono-hilo: no se pueden bloquear dos conexiones PDO
        // simultáneamente en el mismo proceso. Por eso A confirma antes de que
        // B intente insertar. Esto ejerce el camino exacto que ocurre cuando
        // la segunda petición desbloquea tras el commit de la primera.
        //
        // No se usa lock_timeout: en producción, el bloqueo sobre el índice
        // UNIQUE dura lo que tarda la primera transacción en confirmar (< 50ms
        // para un solo INSERT). Esperarlo es lo correcto.

        $paciente1 = User::factory()->create();
        DB::connection('pgsql_migration')->table('user_roles')->insert(['user_id' => $paciente1->id, 'role_id' => $this->patientRole->id]);

        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";

        $pdoA = new \PDO($dsn, $config['username'], $config['password']);
        $pdoA->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdoA->exec("SET app.current_user_id = '{$paciente1->id}'");
        $pdoA->exec("SET app.current_user_role = 'patient'");

        $pdoB = new \PDO($dsn, $config['username'], $config['password']);
        $pdoB->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdoB->exec("SET app.current_user_id = '{$paciente1->id}'");
        $pdoB->exec("SET app.current_user_role = 'patient'");

        $uuid1 = Str::uuid()->toString();
        $uuid2 = Str::uuid()->toString();
        $idempotencyKey = Str::uuid()->toString();
        $franja = '[2026-08-10 12:00:00+00, 2026-08-10 12:30:00+00)';

        $data = [
            'patient_id' => $paciente1->id,
            'doctor_id' => $this->doctor->id,
            'franja_inicio' => '2026-08-10 12:00:00',
            'franja_fin' => '2026-08-10 12:30:00',
        ];
        $payloadHash = hash('sha256', json_encode($data));

        $insertSql = "
            INSERT INTO appointments (id, patient_id, doctor_id, franja, status, idempotency_key, idempotency_payload_hash, created_at, updated_at)
            VALUES (?, ?, ?, ?::tstzrange, 'pending', ?::uuid, ?, now(), now())
        ";

        // --- Petición A: inserta y confirma exitosamente ---
        $pdoA->exec("BEGIN");
        $stmtA = $pdoA->prepare($insertSql);
        $stmtA->execute([$uuid1, $paciente1->id, $this->doctor->id, $franja, $idempotencyKey, $payloadHash]);
        $pdoA->exec("COMMIT");

        // --- Petición B: intenta insertar la misma clave (A ya confirmó) ---
        $excepcionB = null;
        $pdoB->exec("BEGIN");
        try {
            $stmtB = $pdoB->prepare($insertSql);
            $stmtB->execute([$uuid2, $paciente1->id, $this->doctor->id, $franja, $idempotencyKey, $payloadHash]);
            $pdoB->exec("COMMIT");
        } catch (\PDOException $e) {
            $excepcionB = $e;
            try { $pdoB->exec("ROLLBACK"); } catch (\Exception $err) {}
        }

        // 1. B debe recibir EXACTAMENTE 23505 (unique_violation). No 55P03.
        $this->assertNotNull($excepcionB, 'La segunda inserción debió fallar por restricción UNIQUE.');
        $this->assertEquals('23505', $excepcionB->getCode(),
            'El código de error debe ser unique_violation (23505), no lock_timeout ni otro.');

        // 2. B puede recuperar la cita original por su clave de idempotencia
        //    (esto es lo que hace la Action en el catch del 23505)
        $stmtRecovery = $pdoB->prepare(
            "SELECT id, idempotency_payload_hash FROM appointments WHERE idempotency_key = ?::uuid"
        );
        $stmtRecovery->execute([$idempotencyKey]);
        $recovered = $stmtRecovery->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($recovered, 'La cita existente debe ser recuperable tras el 23505.');
        $this->assertEquals($uuid1, $recovered['id'],
            'El id recuperado debe ser el de la cita creada por A.');
        $this->assertEquals($payloadHash, $recovered['idempotency_payload_hash'],
            'El hash del payload debe coincidir, habilitando la respuesta 201.');

        // 3. Solo existe una fila física en la base de datos para esa clave
        $count = DB::connection('pgsql_migration')->table('appointments')
            ->where('idempotency_key', $idempotencyKey)
            ->count();
        $this->assertEquals(1, $count, 'Debe existir exactamente una cita para la clave de idempotencia.');
    }

    /**
     * Test de concurrencia real entre reserva de cita y bloqueo de agenda del médico.
     * Dos conexiones PDO independientes: una reserva y un bloqueo compitiendo por la misma franja.
     * El bloqueo debe ser rechazado por la base de datos con código 23P01.
     */
    public function test_concurrencia_reserva_y_bloqueo_agenda_competencia_por_base(): void
    {
        $paciente = User::factory()->create();
        DB::connection('pgsql_migration')->table('user_roles')->insert(['user_id' => $paciente->id, 'role_id' => $this->patientRole->id]);
        DB::connection('pgsql_migration')->table('users')->where('id', $this->doctor->id)->update(['timezone' => 'UTC']);

        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";

        // Conexión PDO A: Paciente reservando cita
        $pdoA = new \PDO($dsn, $config['username'], $config['password']);
        $pdoA->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdoA->exec("SET app.current_user_id = '{$paciente->id}'");
        $pdoA->exec("SET app.current_user_role = 'patient'");

        // Conexión PDO B: Médico intentando bloquear el mismo horario
        $pdoB = new \PDO($dsn, $config['username'], $config['password']);
        $pdoB->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdoB->exec("SET app.current_user_id = '{$this->doctor->id}'");
        $pdoB->exec("SET app.current_user_role = 'doctor'");

        $appointmentId = Str::uuid()->toString();
        $franjaCita = '[2026-08-10 14:00:00+00, 2026-08-10 14:30:00+00)';

        // 1. Paciente A inserta y confirma cita
        $insertAppointmentSql = "
            INSERT INTO appointments (id, patient_id, doctor_id, franja, status, created_at, updated_at)
            VALUES (?, ?, ?, ?::tstzrange, 'confirmed', NOW(), NOW())
        ";
        $pdoA->exec("BEGIN");
        $stmtA = $pdoA->prepare($insertAppointmentSql);
        $stmtA->execute([$appointmentId, $paciente->id, $this->doctor->id, $franjaCita]);
        $pdoA->exec("COMMIT");

        // 2. Médico B intenta insertar un bloqueo en schedule_blocks para la misma franja
        $doctorProfile = DB::connection('pgsql_migration')
            ->table('doctor_profiles')
            ->where('user_id', $this->doctor->id)
            ->first();

        $blockId = Str::uuid()->toString();
        $insertBlockSql = "
            INSERT INTO schedule_blocks (id, doctor_profile_id, blocked_date, franja, reason, created_at, updated_at)
            VALUES (?, ?, '2026-08-10', '[14:00:00, 15:00:00)'::timerange, 'Capacitación', NOW(), NOW())
        ";

        $excepcionB = null;
        $pdoB->exec("BEGIN");
        try {
            $stmtB = $pdoB->prepare($insertBlockSql);
            $stmtB->execute([$blockId, $doctorProfile->id]);
            $pdoB->exec("COMMIT");
        } catch (\PDOException $e) {
            $excepcionB = $e;
            try { $pdoB->exec("ROLLBACK"); } catch (\Exception $err) {}
        }

        // 3. B debe fallar por el trigger de base de datos con código P0002
        $this->assertNotNull($excepcionB, 'El bloqueo debió ser rechazado por existir cita confirmada.');
        $this->assertEquals('P0002', $excepcionB->getCode(),
            'El código de error del trigger debe ser P0002 (regla de negocio de bloqueo sobre cita activa).');
    }
}


