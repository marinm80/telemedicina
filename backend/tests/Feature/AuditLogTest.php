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
use PDO;

/**
 * RF-20: Pruebas de auditoría inmutable.
 *
 * Verifica que los triggers de PostgreSQL registran toda escritura en audit_logs
 * sin importar el origen (Eloquent, SQL directo, worker, migraciones).
 */
final class AuditLogTest extends TestCase
{
    private User $patient;
    private User $doctor;
    private Role $patientRole;
    private Role $doctorRole;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        // Limpiar tablas manualmente (superusuario, sin RLS)
        $mc = DB::connection('pgsql_migration');
        $mc->table('audit_logs')->delete();
        $mc->table('note_amendments')->delete();
        $mc->table('consultation_notes')->delete();
        $mc->table('consultations')->delete();
        $mc->table('appointments')->delete();
        $mc->table('schedules')->delete();
        $mc->table('doctor_specialties')->delete();
        $mc->table('doctor_profiles')->delete();
        $mc->table('patient_profiles')->delete();
        $mc->table('user_roles')->delete();
        $mc->table('audit_logs')->delete();
        $mc->table('users')->delete();

        // Roles — roles table only has SELECT for app_runtime, use $mc for INSERT
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

        $this->patient = User::factory()->create();
        // user_roles has RLS — bypass with $mc
        $mc->table('user_roles')->insert(['user_id' => $this->patient->id, 'role_id' => $this->patientRole->id]);

        $this->doctor = User::factory()->create();
        $mc->table('user_roles')->insert(['user_id' => $this->doctor->id, 'role_id' => $this->doctorRole->id]);

        // doctor_profiles has RLS — bypass with $mc
        $doctorProfileId = Str::uuid()->toString();
        $mc->table('doctor_profiles')->insert([
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

        // schedules has RLS — bypass with $mc
        $mc->table('schedules')->insert([
            'id' => Str::uuid()->toString(),
            'doctor_profile_id' => $doctorProfileId,
            'day_of_week' => 1,
            'franja' => '[09:00:00, 12:00:00)',
            'slot_duration' => 30,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Limpiar audit_logs generados por las inserciones del setUp
        // para que cada test empiece con audit_logs vacío
        $mc->table('audit_logs')->delete();
    }

    protected function tearDown(): void
    {
        $mc = DB::connection('pgsql_migration');
        $mc->table('audit_logs')->delete();
        $mc->table('note_amendments')->delete();
        $mc->table('consultation_notes')->delete();
        $mc->table('consultations')->delete();
        $mc->table('appointments')->delete();
        $mc->table('schedules')->delete();
        $mc->table('doctor_specialties')->delete();
        $mc->table('doctor_profiles')->delete();
        $mc->table('patient_profiles')->delete();
        $mc->table('user_roles')->delete();
        $mc->table('audit_logs')->delete();
        $mc->table('users')->delete();

        parent::tearDown();
    }

    // ====================================================================
    // SECCIÓN 1: TRIGGER REGISTRA INSERT
    // ====================================================================

    public function test_insertar_cita_genera_audit_log_con_insert(): void
    {
        $this->actingAs($this->patient);

        // Reservar una cita (genera INSERT en appointments)
        $response = $this->postJson('/api/appointments', [
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'franja_inicio' => '2026-08-10T09:00:00Z',
            'franja_fin' => '2026-08-10T09:30:00Z',
        ], [
            'X-Idempotency-Key' => Str::uuid()->toString(),
        ]);

        $response->assertStatus(201);

        $appointmentId = $response->json('data.id');

        // Verificar audit_log con conexión de superusuario (sin RLS)
        $auditLog = DB::connection('pgsql_migration')
            ->table('audit_logs')
            ->where('table_name', 'appointments')
            ->where('record_id', $appointmentId)
            ->where('action', 'INSERT')
            ->first();

        $this->assertNotNull($auditLog, 'Debe existir un registro de auditoría para el INSERT de la cita');
        $this->assertEquals($this->patient->id, $auditLog->user_id, 'El actor debe ser el paciente autenticado');
        $this->assertEquals('app_runtime', $auditLog->actor_pg, 'El rol PostgreSQL debe ser app_runtime');

        $newValues = json_decode($auditLog->new_values, true);
        $this->assertEquals($this->patient->id, $newValues['patient_id']);
        $this->assertEquals('pending', $newValues['status']);
        $this->assertNull($auditLog->old_values, 'Un INSERT no tiene old_values');
    }

    // ====================================================================
    // SECCIÓN 2: TRIGGER REGISTRA UPDATE CON OLD Y NEW
    // ====================================================================

    public function test_actualizar_cita_genera_audit_log_con_update(): void
    {
        // Crear cita directamente con superusuario
        $appointmentId = Str::uuid()->toString();
        DB::connection('pgsql_migration')->table('appointments')->insert([
            'id' => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'franja' => '[2026-08-10 09:00:00+00, 2026-08-10 09:30:00+00)',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Limpiar los audit_logs del INSERT anterior (queremos solo el UPDATE)
        DB::connection('pgsql_migration')->table('audit_logs')->delete();

        // Actualizar estado con contexto HTTP (simula confirmación de pago)
        $this->actingAs($this->patient);
        DB::statement("SET app.current_user_id = '{$this->patient->id}'");
        DB::statement("SET app.current_user_role = 'patient'");

        DB::table('appointments')
            ->where('id', $appointmentId)
            ->update(['status' => 'confirmed', 'updated_at' => now()]);

        $auditLog = DB::connection('pgsql_migration')
            ->table('audit_logs')
            ->where('table_name', 'appointments')
            ->where('record_id', $appointmentId)
            ->where('action', 'UPDATE')
            ->first();

        $this->assertNotNull($auditLog, 'Debe existir un registro de auditoría para el UPDATE');

        $oldValues = json_decode($auditLog->old_values, true);
        $newValues = json_decode($auditLog->new_values, true);

        $this->assertEquals('pending', $oldValues['status'], 'old_values debe contener el estado anterior');
        $this->assertEquals('confirmed', $newValues['status'], 'new_values debe contener el estado nuevo');
    }

    // ====================================================================
    // SECCIÓN 3: TRIGGER REGISTRA ACTOR DE SISTEMA CUANDO NO HAY CONTEXTO HTTP
    // ====================================================================

    public function test_escritura_sin_contexto_http_registra_current_user(): void
    {
        // Simular escritura del worker: usar pgsql_worker sin establecer app.current_user_id
        $workerPdo = new PDO(
            sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                config('database.connections.pgsql_worker.host'),
                config('database.connections.pgsql_worker.port'),
                config('database.connections.pgsql_worker.database')
            ),
            config('database.connections.pgsql_worker.username'),
            config('database.connections.pgsql_worker.password'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // NO establecemos app.current_user_id — simula un job de Horizon sin sesión HTTP
        $appointmentId = Str::uuid()->toString();

        // Insertar cita directamente con superusuario (para luego actualizarla con worker)
        DB::connection('pgsql_migration')->table('appointments')->insert([
            'id' => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'franja' => '[2026-08-10 10:00:00+00, 2026-08-10 10:30:00+00)',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('pgsql_migration')->table('audit_logs')->delete();

        // Worker actualiza sin contexto HTTP
        $stmt = $workerPdo->prepare("UPDATE appointments SET status = 'confirmed', updated_at = now() WHERE id = :id");
        $stmt->execute(['id' => $appointmentId]);

        $auditLog = DB::connection('pgsql_migration')
            ->table('audit_logs')
            ->where('table_name', 'appointments')
            ->where('record_id', $appointmentId)
            ->where('action', 'UPDATE')
            ->first();

        $this->assertNotNull($auditLog, 'Debe existir un registro de auditoría para escritura del worker');
        $this->assertNull($auditLog->user_id, 'user_id debe ser NULL cuando no hay contexto HTTP');
        $this->assertEquals('app_worker', $auditLog->actor_pg, 'actor_pg debe ser app_worker');
    }

    // ====================================================================
    // SECCIÓN 4: app_runtime NO PUEDE DESACTIVAR TRIGGERS
    // ====================================================================

    public function test_app_runtime_no_puede_desactivar_triggers(): void
    {
        $runtimePdo = new PDO(
            sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                config('database.connections.pgsql.host'),
                config('database.connections.pgsql.port'),
                config('database.connections.pgsql.database')
            ),
            config('database.connections.pgsql.username'),
            config('database.connections.pgsql.password'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $exceptionThrown = false;
        $errorMessage = '';

        try {
            $runtimePdo->exec('ALTER TABLE appointments DISABLE TRIGGER trg_audit_appointments');
        } catch (\PDOException $e) {
            $exceptionThrown = true;
            $errorMessage = $e->getMessage();
        }

        $this->assertTrue($exceptionThrown, 'app_runtime debe lanzar excepción al intentar DISABLE TRIGGER');
        $this->assertStringContainsString(
            'must be owner',
            $errorMessage,
            'El error debe indicar que no es propietario de la tabla'
        );
    }

    // ====================================================================
    // SECCIÓN 5: OPTIMIZACIÓN — NO REGISTRA SI LA FILA NO CAMBIÓ
    // ====================================================================

    public function test_update_sin_cambio_no_genera_audit_log(): void
    {
        // Crear cita con superusuario
        $appointmentId = Str::uuid()->toString();
        DB::connection('pgsql_migration')->table('appointments')->insert([
            'id' => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'franja' => '[2026-08-10 11:00:00+00, 2026-08-10 11:30:00+00)',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('pgsql_migration')->table('audit_logs')->delete();

        // UPDATE que no cambia nada (mismo status)
        $this->actingAs($this->patient);
        DB::statement("SET app.current_user_id = '{$this->patient->id}'");
        DB::statement("SET app.current_user_role = 'patient'");

        DB::table('appointments')
            ->where('id', $appointmentId)
            ->update(['status' => 'pending']);

        $auditCount = DB::connection('pgsql_migration')
            ->table('audit_logs')
            ->where('table_name', 'appointments')
            ->where('record_id', $appointmentId)
            ->count();

        $this->assertEquals(0, $auditCount, 'Un UPDATE sin cambio real no debe generar audit log');
    }

    // ====================================================================
    // SECCIÓN 6: GAUNTLET — app_runtime NO puede fabricar audit_logs
    // ====================================================================

    /**
     * Hallazgo 4a: INSERT directo en audit_logs como app_runtime → permission denied.
     */
    public function test_app_runtime_no_puede_insertar_en_audit_logs(): void
    {
        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new \PDO($dsn, $config['username'], $config['password']);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $pdo->exec("SET app.current_user_id = '{$this->patient->id}'");
        $pdo->exec("SET app.current_user_role = 'patient'");

        $this->expectException(\PDOException::class);
        $this->expectExceptionMessageMatches('/permission denied/');

        $pdo->exec("
            INSERT INTO audit_logs (table_name, record_id, action, actor_pg, new_values)
            VALUES ('fake_table', '" . Str::uuid() . "', 'INSERT', 'app_runtime', '{}'::jsonb)
        ");
    }

    /**
     * Hallazgo 4b: Una reserva normal SÍ genera audit_log vía trigger.
     * Esta prueba hace segura la 4a: el REVOKE no rompió la auditoría.
     */
    public function test_reserva_normal_genera_audit_log_via_trigger(): void
    {
        $this->actingAs($this->patient);
        DB::connection('pgsql_migration')->table('audit_logs')->delete();

        $response = $this->withHeader('X-Idempotency-Key', Str::uuid()->toString())
            ->postJson('/api/appointments', [
                'patient_id'    => $this->patient->id,
                'doctor_id'     => $this->doctor->id,
                'franja_inicio' => '2026-08-10T11:00:00Z',
                'franja_fin'    => '2026-08-10T11:30:00Z',
            ]);

        $response->assertStatus(201);

        $appointmentId = $response->json('data.id');

        $auditLog = DB::connection('pgsql_migration')
            ->table('audit_logs')
            ->where('table_name', 'appointments')
            ->where('record_id', $appointmentId)
            ->where('action', 'INSERT')
            ->first();

        $this->assertNotNull($auditLog, 'La reserva debe generar audit_log vía trigger SECURITY DEFINER');
        $this->assertEquals('app_runtime', $auditLog->actor_pg, 'El trigger debe registrar app_runtime');
    }
}
