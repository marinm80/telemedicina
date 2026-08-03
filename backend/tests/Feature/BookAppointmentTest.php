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

        // Limpiar tablas manualmente para evitar contaminación entre pruebas sin ocultar datos a PDO concurrentes
        $migrationConn = DB::connection('pgsql_migration');
        $migrationConn->table('note_amendments')->delete();
        $migrationConn->table('consultation_notes')->delete();
        $migrationConn->table('consultations')->delete();
        $migrationConn->table('appointments')->delete();
        $migrationConn->table('schedules')->delete();
        $migrationConn->table('doctor_specialties')->delete();
        $migrationConn->table('doctor_profiles')->delete();
        $migrationConn->table('patient_profiles')->delete();
        $migrationConn->table('user_roles')->delete();
        $migrationConn->table('users')->delete();

        // 1. Inicializar roles en la base de datos si no existen
        $this->patientRole = Role::firstOrCreate(['name' => 'patient'], ['description' => 'Paciente']);
        $this->doctorRole = Role::firstOrCreate(['name' => 'doctor'], ['description' => 'Médico']);

        // 2. Crear usuarios de prueba
        $this->patient = User::factory()->create();
        $this->patient->roles()->attach($this->patientRole);

        $this->doctor = User::factory()->create();
        $this->doctor->roles()->attach($this->doctorRole);

        // Crear perfil médico aprobado
        DoctorProfile::create([
            'user_id' => $this->doctor->id,
            'license_number' => 'LIC-' . Str::random(5),
            'university' => 'Universidad Nacional',
            'years_experience' => 5,
            'description' => 'Especialista en medicina interna',
            'consultation_fee' => 50.00,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        // Crear horario los días Lunes (Lunes en Carbon es 1)
        Schedule::create([
            'doctor_profile_id' => $this->doctor->doctorProfile->id,
            'day_of_week' => 1,
            'franja' => '[09:00:00, 12:00:00)',
            'slot_duration' => 30,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        // Limpiar todas las tablas después de los tests
        $migrationConn = DB::connection('pgsql_migration');
        $migrationConn->table('note_amendments')->delete();
        $migrationConn->table('consultation_notes')->delete();
        $migrationConn->table('consultations')->delete();
        $migrationConn->table('appointments')->delete();
        $migrationConn->table('schedules')->delete();
        $migrationConn->table('doctor_specialties')->delete();
        $migrationConn->table('doctor_profiles')->delete();
        $migrationConn->table('patient_profiles')->delete();
        $migrationConn->table('user_roles')->delete();
        $migrationConn->table('users')->delete();

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

    public function test_consultar_disponibilidad_doctor_no_aprobado_lanza_403(): void
    {
        $this->actingAs($this->patient);

        // Modificar estado del médico a pending
        $this->doctor->doctorProfile->update(['status' => 'pending']);

        $response = $this->getJson("/api/doctors/{$this->doctor->id}/availability?date=2026-08-10");

        $response->assertStatus(403)
            ->assertJson([
                'error_code' => 'DOCTOR_NOT_APPROVED'
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
        $paciente2 = User::factory()->create();
        $paciente2->roles()->attach($this->patientRole);
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
        $otroPaciente->roles()->attach($this->patientRole);
        
        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new \PDO($dsn, 'app_runtime', 'secure_runtime_pass');
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
        $cita = Appointment::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'franja' => '[2026-08-10 09:00:00+00, 2026-08-10 09:30:00+00)',
            'status' => 'confirmed'
        ]);

        $consulta = DB::table('consultations')->insertGetId([
            'id' => Str::uuid(),
            'appointment_id' => $cita->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new \PDO($dsn, 'app_runtime', 'secure_runtime_pass');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // 1. Intentar insertar borrador SOAP como Médico Asignado (Éxito)
        $pdo->exec("SET app.current_user_id = '{$this->doctor->id}'");
        $pdo->exec("SET app.current_user_role = 'doctor'");

        $notaUuid = Str::uuid()->toString();
        $stmtInsert = $pdo->prepare("
            INSERT INTO consultation_notes (id, consultation_id, symptoms, objective, analysis, plan, status)
            VALUES (?, ?, 'Sintomas', 'Objetivo', 'Analisis', 'Plan', 'draft')
        ");
        $stmtInsert->execute([$notaUuid, $consulta]);

        $this->assertDatabaseHas('consultation_notes', ['id' => $notaUuid, 'status' => 'draft']);

        // 2. Intentar insertar borrador SOAP como Paciente (Fallo RLS)
        $pdo->exec("SET app.current_user_id = '{$this->patient->id}'");
        $pdo->exec("SET app.current_user_role = 'patient'");

        $notaUuid2 = Str::uuid()->toString();
        $this->expectException(\PDOException::class);
        $stmtInsert->execute([$notaUuid2, $consulta]);
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
        $paciente1->roles()->attach($this->patientRole);

        $paciente2 = User::factory()->create();
        $paciente2->roles()->attach($this->patientRole);

        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";

        $pdoA = new \PDO($dsn, 'app_runtime', 'secure_runtime_pass');
        $pdoA->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdoA->exec("SET app.current_user_id = '{$paciente1->id}'");
        $pdoA->exec("SET app.current_user_role = 'patient'");

        $pdoB = new \PDO($dsn, 'app_runtime', 'secure_runtime_pass');
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
        $paciente1->roles()->attach($this->patientRole);

        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";

        $pdoA = new \PDO($dsn, 'app_runtime', 'secure_runtime_pass');
        $pdoA->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdoA->exec("SET app.current_user_id = '{$paciente1->id}'");
        $pdoA->exec("SET app.current_user_role = 'patient'");

        $pdoB = new \PDO($dsn, 'app_runtime', 'secure_runtime_pass');
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
}

