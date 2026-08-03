<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * BARRERA R7: toda tabla con GRANT de escritura (INSERT/UPDATE/DELETE)
 * a app_runtime o app_worker DEBE tener RLS habilitado.
 *
 * Esta prueba consulta information_schema cruzado con pg_class y falla
 * si cualquier tabla viola la regla. Cubre tablas futuras.
 */

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

final class RlsCoverageTest extends TestCase
{
    /**
     * Excepciones aprobadas: tablas que tienen DML grants sin RLS.
     * Cada entrada necesita un comentario con el motivo.
     * VACÍA: no hay excepciones aprobadas.
     */
    private const APPROVED_EXCEPTIONS = [
        // Ninguna excepción aprobada.
    ];

    /**
     * Excepciones aprobadas: políticas cuyo qual o with_check es permisivo
     * (literalmente 'true' o contiene 'OR true' en nivel superior).
     *
     * REGLA: una entrada es válida SOLO si ninguna expresión de política puede
     * codificar la intención. "Directorio público" no es motivo suficiente.
     * Cada entrada NOMBRA LAS COLUMNAS que la justifican: si la necesidad es
     * "necesito franja pero no reason", la respuesta es vista o GRANT de columna,
     * no excepción.
     *
     * Formato: 'tabla.nombre_policy' => 'motivo con columnas nombradas'
     */
    private const APPROVED_PERMISSIVE_POLICIES = [
        // VACÍA: no hay excepciones aprobadas todavía.
    ];

    /**
     * Barrera R7: toda tabla con GRANT de INSERT/UPDATE/DELETE
     * a app_runtime o app_worker debe tener relrowsecurity = true.
     */
    public function test_barrera_toda_tabla_con_dml_grant_tiene_rls(): void
    {
        $mc = DB::connection('pgsql_migration');

        $violations = $mc->select("
            SELECT DISTINCT g.table_name
            FROM information_schema.role_table_grants g
            JOIN pg_class c ON c.relname = g.table_name
            JOIN pg_namespace n ON n.oid = c.relnamespace AND n.nspname = g.table_schema
            WHERE g.table_schema = 'public'
              AND g.grantee IN ('app_runtime', 'app_worker')
              AND g.privilege_type IN ('INSERT', 'UPDATE', 'DELETE')
              AND c.relrowsecurity = false
            ORDER BY g.table_name
        ");

        $violationNames = array_map(fn ($row) => $row->table_name, $violations);

        // Filtrar excepciones aprobadas
        $violationNames = array_diff($violationNames, self::APPROVED_EXCEPTIONS);

        $this->assertEmpty(
            $violationNames,
            "Tablas con GRANT de INSERT/UPDATE/DELETE sin RLS habilitado:\n"
            . implode("\n", array_map(fn ($t) => "  - {$t}", $violationNames))
            . "\n\nCada tabla con privilegio de escritura DEBE tener RLS."
            . "\nSi hay una excepción legítima, agréguela a APPROVED_EXCEPTIONS con motivo."
        );
    }

    /**
     * Barrera R7b: ninguna política RLS puede ser permisiva (USING(true) o
     * contener 'OR true' en nivel superior) sin estar en la lista de excepciones.
     *
     * Una política que dice USING (true) tiene RLS habilitado pero no protege nada.
     * Es peor que no tener RLS porque APARENTA ser una regla.
     *
     * Consulta pg_policies.qual y pg_policies.with_check buscando:
     *   - Literalmente 'true' (la política entera es true)
     *   - Contiene ' OR true)' en el nivel superior (el OR true anula las condiciones)
     */
    public function test_barrera_politicas_permisivas_sin_excepcion(): void
    {
        $mc = DB::connection('pgsql_migration');

        // pg_policies almacena las expresiones parseadas con nodeToString.
        // 'true' literal aparece como 'true' en qual/with_check.
        // 'X OR true' aparece con 'true' como disyunción terminal.
        $permissive = $mc->select("
            SELECT
                schemaname,
                tablename,
                policyname,
                cmd,
                CASE
                    WHEN lower(qual) = 'true' THEN 'qual = true'
                    WHEN qual ~ '(?i)\\mOR\\s+true\\M' THEN 'qual contiene OR true'
                    WHEN qual ~ '(?i)\\mOR\\s*\\(\\s*true\\s*\\)' THEN 'qual contiene OR (true)'
                    WHEN qual ~ '(?i)(\\m1\\s*=\\s*1\\M)' THEN 'qual contiene tautología 1=1'
                    ELSE NULL
                END AS qual_issue,
                CASE
                    WHEN lower(with_check) = 'true' THEN 'with_check = true'
                    WHEN with_check ~ '(?i)\\mOR\\s+true\\M' THEN 'with_check contiene OR true'
                    WHEN with_check ~ '(?i)\\mOR\\s*\\(\\s*true\\s*\\)' THEN 'with_check contiene OR (true)'
                    WHEN with_check ~ '(?i)(\\m1\\s*=\\s*1\\M)' THEN 'with_check contiene tautología 1=1'
                    ELSE NULL
                END AS check_issue
            FROM pg_policies
            WHERE schemaname = 'public'
              AND (
                  lower(qual) = 'true'
                  OR qual ~ '(?i)\\mOR\\s+true\\M'
                  OR qual ~ '(?i)\\mOR\\s*\\(\\s*true\\s*\\)'
                  OR qual ~ '(?i)(\\m1\\s*=\\s*1\\M)'
                  OR lower(with_check) = 'true'
                  OR with_check ~ '(?i)\\mOR\\s+true\\M'
                  OR with_check ~ '(?i)\\mOR\\s*\\(\\s*true\\s*\\)'
                  OR with_check ~ '(?i)(\\m1\\s*=\\s*1\\M)'
              )
            ORDER BY tablename, policyname
        ");

        // Filtrar excepciones aprobadas
        $violations = [];
        foreach ($permissive as $row) {
            $key = "{$row->tablename}.{$row->policyname}";
            if (!array_key_exists($key, self::APPROVED_PERMISSIVE_POLICIES)) {
                $issue = $row->qual_issue ?? $row->check_issue ?? 'permisiva';
                $violations[] = "  - {$key} ({$row->cmd}): {$issue}";
            }
        }

        $this->assertEmpty(
            $violations,
            "Políticas RLS permisivas sin excepción aprobada:\n"
            . implode("\n", $violations)
            . "\n\nUna política USING(true) o con OR true no protege nada."
            . "\nSi hay una excepción legítima, agréguela a APPROVED_PERMISSIVE_POLICIES"
            . "\nnombrando las columnas que la justifican. Si la necesidad es"
            . "\n'necesito col_X pero no col_Y', la respuesta es vista o GRANT de columna."
        );
    }

    /**
     * Barrera positiva: audit_logs SELECT solo visible para admin
     * y el propio user_id.
     */
    public function test_audit_logs_select_solo_admin_y_propio_usuario(): void
    {
        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";

        // Crear un audit_log de referencia vía superusuario
        $mc = DB::connection('pgsql_migration');
        $mc->table('audit_logs')->delete();

        // Necesitamos un usuario para el test
        $user = \App\Models\User::factory()->create();
        $otherUser = \App\Models\User::factory()->create();

        // Insertar audit_log para $user vía superusuario
        $mc->table('audit_logs')->insert([
            'id'         => \Illuminate\Support\Str::uuid()->toString(),
            'table_name' => 'test_table',
            'record_id'  => \Illuminate\Support\Str::uuid()->toString(),
            'action'     => 'INSERT',
            'user_id'    => $user->id,
            'actor_pg'   => 'app_runtime',
            'new_values' => '{"test": true}',
            'created_at' => now(),
        ]);

        // El propio usuario VE su audit_log
        $pdoSelf = new \PDO($dsn, $config['username'], $config['password']);
        $pdoSelf->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdoSelf->exec("SET app.current_user_id = '{$user->id}'");
        $pdoSelf->exec("SET app.current_user_role = 'patient'");

        $stmt = $pdoSelf->query("SELECT count(*) FROM audit_logs");
        $selfCount = (int) $stmt->fetchColumn();
        $this->assertEquals(1, $selfCount, 'El usuario debe ver sus propios audit_logs');

        // Otro usuario NO ve el audit_log
        $pdoOther = new \PDO($dsn, $config['username'], $config['password']);
        $pdoOther->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdoOther->exec("SET app.current_user_id = '{$otherUser->id}'");
        $pdoOther->exec("SET app.current_user_role = 'patient'");

        $stmt = $pdoOther->query("SELECT count(*) FROM audit_logs");
        $otherCount = (int) $stmt->fetchColumn();
        $this->assertEquals(0, $otherCount, 'Otro usuario NO debe ver audit_logs ajenos');

        // Admin VE todos los audit_logs
        $pdoAdmin = new \PDO($dsn, $config['username'], $config['password']);
        $pdoAdmin->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdoAdmin->exec("SET app.current_user_id = '{$otherUser->id}'");
        $pdoAdmin->exec("SET app.current_user_role = 'admin'");

        $stmt = $pdoAdmin->query("SELECT count(*) FROM audit_logs");
        $adminCount = (int) $stmt->fetchColumn();
        $this->assertEquals(1, $adminCount, 'El admin debe ver todos los audit_logs');

        // Cleanup
        $mc->table('audit_logs')->delete();
        $mc->table('users')->whereIn('id', [$user->id, $otherUser->id])->delete();
    }

    /**
     * Barrera: user_roles — solo admin escribe, nunca a sí mismo.
     */
    public function test_user_roles_solo_admin_escribe_nunca_a_si_mismo(): void
    {
        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $mc = DB::connection('pgsql_migration');

        // Limpiar
        $mc->table('audit_logs')->delete();
        $mc->table('user_roles')->delete();
        $mc->table('users')->delete();

        $admin = \App\Models\User::factory()->create();
        $target = \App\Models\User::factory()->create();
        $regular = \App\Models\User::factory()->create();

        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        if (!$adminRole) {
            $mc->table('roles')->insert(['id' => \Illuminate\Support\Str::uuid()->toString(), 'name' => 'admin', 'description' => 'Admin', 'created_at' => now(), 'updated_at' => now()]);
            $adminRole = \App\Models\Role::where('name', 'admin')->first();
        }
        $patientRole = \App\Models\Role::where('name', 'patient')->first();
        if (!$patientRole) {
            $mc->table('roles')->insert(['id' => \Illuminate\Support\Str::uuid()->toString(), 'name' => 'patient', 'description' => 'Paciente', 'created_at' => now(), 'updated_at' => now()]);
            $patientRole = \App\Models\Role::where('name', 'patient')->first();
        }

        // Asignar rol admin a $admin vía superusuario
        $mc->table('user_roles')->insert(['user_id' => $admin->id, 'role_id' => $adminRole->id]);

        // 1. Usuario regular intenta asignar rol → DENEGADO
        $pdoRegular = new \PDO($dsn, $config['username'], $config['password']);
        $pdoRegular->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdoRegular->exec("SET app.current_user_id = '{$regular->id}'");
        $pdoRegular->exec("SET app.current_user_role = 'patient'");

        try {
            $pdoRegular->exec("INSERT INTO user_roles (user_id, role_id) VALUES ('{$target->id}', '{$patientRole->id}')");
            $this->fail('Un usuario regular NO debe poder insertar en user_roles');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('row-level security', $e->getMessage());
        }

        // 2. Admin asigna rol a otro → PERMITIDO
        $pdoAdmin = new \PDO($dsn, $config['username'], $config['password']);
        $pdoAdmin->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdoAdmin->exec("SET app.current_user_id = '{$admin->id}'");
        $pdoAdmin->exec("SET app.current_user_role = 'admin'");

        $pdoAdmin->exec("INSERT INTO user_roles (user_id, role_id) VALUES ('{$target->id}', '{$patientRole->id}')");
        $count = (int) $pdoAdmin->query("SELECT count(*) FROM user_roles WHERE user_id = '{$target->id}'")->fetchColumn();
        $this->assertEquals(1, $count, 'El admin debe poder asignar roles a otros');

        // 3. Admin intenta asignarse rol a sí mismo → DENEGADO
        $doctorRole = \App\Models\Role::where('name', 'doctor')->first();
        if (!$doctorRole) {
            $mc->table('roles')->insert(['id' => \Illuminate\Support\Str::uuid()->toString(), 'name' => 'doctor', 'description' => 'Médico', 'created_at' => now(), 'updated_at' => now()]);
            $doctorRole = \App\Models\Role::where('name', 'doctor')->first();
        }

        try {
            $pdoAdmin->exec("INSERT INTO user_roles (user_id, role_id) VALUES ('{$admin->id}', '{$doctorRole->id}')");
            $this->fail('El admin NO debe poder asignarse roles a sí mismo');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('row-level security', $e->getMessage());
        }

        // Cleanup
        $mc->table('audit_logs')->delete();
        $mc->table('user_roles')->delete();
        $mc->table('audit_logs')->delete();
        $mc->table('users')->delete();
    }

    /**
     * Deuda Regla 3: doctor_profiles se inserta en 3 setUp como fixture de
     * pgsql_migration, pero no existe prueba del camino legítimo (doctor
     * crea su propio perfil via app_runtime con RLS).
     */
    public function test_doctor_profiles_camino_legitimo_insert(): void
    {
        $this->markTestSkipped('PENDIENTE RF-08: falta prueba del camino legítimo para doctor_profiles (doctor crea perfil via app_runtime)');
    }

    /**
     * Deuda Regla 3: schedules se inserta en 3 setUp como fixture de
     * pgsql_migration, pero no existe prueba del camino legítimo (doctor
     * crea su agenda via app_runtime con RLS).
     */
    public function test_schedules_camino_legitimo_insert(): void
    {
        $this->markTestSkipped('PENDIENTE RF-08: falta prueba del camino legítimo para schedules (doctor gestiona agenda via app_runtime)');
    }

    // ================================================================
    // HALLAZGO 17: PRUEBAS POSITIVAS DE FLUJO LEGÍTIMO
    // Cada política cerrada viaja con una prueba positiva de que el
    // flujo legítimo sigue pasando. La barrera demuestra que ninguna
    // política es permisiva; estas prueban que tampoco son excesivas.
    // ================================================================

    /**
     * users_select: paciente lee su propio registro.
     */
    public function test_positiva_users_select_paciente_lee_self(): void
    {
        $mc = DB::connection('pgsql_migration');

        // Fixture: paciente
        $userId = \Illuminate\Support\Str::uuid()->toString();
        $mc->table('users')->insert([
            'id' => $userId, 'name' => 'TestSelf', 'last_name' => 'User',
            'email' => 'self_' . \Illuminate\Support\Str::random(5) . '@test.com',
            'password' => bcrypt('pass'), 'timezone' => 'UTC',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $patientRole = $mc->table('roles')->where('name', 'patient')->first();
        $mc->table('user_roles')->insert(['user_id' => $userId, 'role_id' => $patientRole->id]);

        // Query como app_runtime con contexto self
        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new \PDO($dsn, $config['username'], $config['password']);
        $pdo->exec("SET app.current_user_id = '{$userId}'");
        $pdo->exec("SET app.current_user_role = 'patient'");

        // SELECT con columnas explícitas (password/remember_token no están en GRANT)
        $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row, 'El paciente debe poder leer su propio User');
        $this->assertEquals($userId, $row['id']);

        // Verificar que password NO es accesible vía SELECT columna
        try {
            $pdo->query("SELECT password FROM users WHERE id = '{$userId}'");
            $this->fail('app_runtime NO debe poder leer password de users');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('permission denied', $e->getMessage());
        }

        // Verificar que remember_token NO es accesible vía SELECT columna
        try {
            $pdo->query("SELECT remember_token FROM users WHERE id = '{$userId}'");
            $this->fail('app_runtime NO debe poder leer remember_token de users');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('permission denied', $e->getMessage());
        }

        // Cleanup
        $mc->table('user_roles')->where('user_id', $userId)->delete();
        $mc->table('audit_logs')->delete();
        $mc->table('users')->where('id', $userId)->delete();
    }

    /**
     * users_select: paciente NO lee registro de otro usuario.
     */
    public function test_positiva_users_select_paciente_no_lee_otro(): void
    {
        $mc = DB::connection('pgsql_migration');

        $userId = \Illuminate\Support\Str::uuid()->toString();
        $otherId = \Illuminate\Support\Str::uuid()->toString();
        $mc->table('users')->insert([
            ['id' => $userId, 'name' => 'Self', 'last_name' => 'U', 'email' => 'me_' . \Illuminate\Support\Str::random(5) . '@test.com', 'password' => bcrypt('p'), 'timezone' => 'UTC', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $otherId, 'name' => 'Other', 'last_name' => 'U', 'email' => 'other_' . \Illuminate\Support\Str::random(5) . '@test.com', 'password' => bcrypt('p'), 'timezone' => 'UTC', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new \PDO($dsn, $config['username'], $config['password']);
        $pdo->exec("SET app.current_user_id = '{$userId}'");
        $pdo->exec("SET app.current_user_role = 'patient'");

        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
        $stmt->execute([$otherId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertFalse($row, 'El paciente NO debe poder leer el User de otro (sin cita)');

        // Cleanup
        $mc->table('audit_logs')->delete();
        $mc->table('users')->whereIn('id', [$userId, $otherId])->delete();
    }

    /**
     * v_doctor_directory: paciente consulta directorio de médicos aprobados.
     */
    public function test_positiva_v_doctor_directory_paciente_lee_aprobados(): void
    {
        $mc = DB::connection('pgsql_migration');

        $doctorUserId = \Illuminate\Support\Str::uuid()->toString();
        $patientId = \Illuminate\Support\Str::uuid()->toString();
        $mc->table('users')->insert([
            ['id' => $doctorUserId, 'name' => 'DocDir', 'last_name' => 'Test', 'email' => 'docdir_' . \Illuminate\Support\Str::random(5) . '@test.com', 'password' => bcrypt('p'), 'timezone' => 'UTC', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $patientId, 'name' => 'PatDir', 'last_name' => 'Test', 'email' => 'patdir_' . \Illuminate\Support\Str::random(5) . '@test.com', 'password' => bcrypt('p'), 'timezone' => 'UTC', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $dpId = \Illuminate\Support\Str::uuid()->toString();
        $mc->table('doctor_profiles')->insert([
            'id' => $dpId, 'user_id' => $doctorUserId, 'status' => 'approved',
            'license_number' => 'DIR001', 'consultation_fee' => 50.00,
            'university' => 'TestU', 'description' => 'Test',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new \PDO($dsn, $config['username'], $config['password']);
        $pdo->exec("SET app.current_user_id = '{$patientId}'");
        $pdo->exec("SET app.current_user_role = 'patient'");

        // Directorio público: el paciente puede ver al doctor aprobado
        $stmt = $pdo->query("SELECT user_id, name, consultation_fee FROM v_doctor_directory WHERE user_id = '{$doctorUserId}'");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row, 'Paciente debe ver doctor aprobado en v_doctor_directory');
        $this->assertEquals('50.00', $row['consultation_fee']);

        // Cleanup
        $mc->table('doctor_profiles')->where('id', $dpId)->delete();
        $mc->table('audit_logs')->delete();
        $mc->table('users')->whereIn('id', [$doctorUserId, $patientId])->delete();
    }

    /**
     * doctor_profiles_select: paciente NO lee perfil pending de otro doctor.
     */
    public function test_positiva_doctor_profiles_select_paciente_no_lee_pending(): void
    {
        $mc = DB::connection('pgsql_migration');

        $doctorUserId = \Illuminate\Support\Str::uuid()->toString();
        $patientId = \Illuminate\Support\Str::uuid()->toString();
        $mc->table('users')->insert([
            ['id' => $doctorUserId, 'name' => 'DocPend', 'last_name' => 'T', 'email' => 'docpend_' . \Illuminate\Support\Str::random(5) . '@test.com', 'password' => bcrypt('p'), 'timezone' => 'UTC', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $patientId, 'name' => 'Pat', 'last_name' => 'T', 'email' => 'patpend_' . \Illuminate\Support\Str::random(5) . '@test.com', 'password' => bcrypt('p'), 'timezone' => 'UTC', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $dpId = \Illuminate\Support\Str::uuid()->toString();
        $mc->table('doctor_profiles')->insert([
            'id' => $dpId, 'user_id' => $doctorUserId, 'status' => 'pending',
            'license_number' => 'PND001', 'consultation_fee' => 60.00,
            'university' => 'TestU', 'description' => 'Test', 'rejection_reason' => 'Motivo secreto',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new \PDO($dsn, $config['username'], $config['password']);
        $pdo->exec("SET app.current_user_id = '{$patientId}'");
        $pdo->exec("SET app.current_user_role = 'patient'");

        $stmt = $pdo->query("SELECT id, rejection_reason FROM doctor_profiles WHERE user_id = '{$doctorUserId}'");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertFalse($row, 'Paciente NO debe ver doctor_profiles pending (rejection_reason expuesto)');

        // Cleanup
        $mc->table('doctor_profiles')->where('id', $dpId)->delete();
        $mc->table('audit_logs')->delete();
        $mc->table('users')->whereIn('id', [$doctorUserId, $patientId])->delete();
    }

    /**
     * user_roles_select: paciente lee su propio rol.
     */
    public function test_positiva_user_roles_select_self(): void
    {
        $mc = DB::connection('pgsql_migration');

        $userId = \Illuminate\Support\Str::uuid()->toString();
        $mc->table('users')->insert([
            'id' => $userId, 'name' => 'RoleSelf', 'last_name' => 'T',
            'email' => 'roleself_' . \Illuminate\Support\Str::random(5) . '@test.com',
            'password' => bcrypt('p'), 'timezone' => 'UTC',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $patientRole = $mc->table('roles')->where('name', 'patient')->first();
        $mc->table('user_roles')->insert(['user_id' => $userId, 'role_id' => $patientRole->id]);

        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new \PDO($dsn, $config['username'], $config['password']);
        $pdo->exec("SET app.current_user_id = '{$userId}'");
        $pdo->exec("SET app.current_user_role = 'patient'");

        $stmt = $pdo->query("SELECT user_id, role_id FROM user_roles WHERE user_id = '{$userId}'");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row, 'Paciente debe poder leer su propio user_roles');
        $this->assertEquals($userId, $row['user_id']);

        // Cleanup
        $mc->table('user_roles')->where('user_id', $userId)->delete();
        $mc->table('audit_logs')->delete();
        $mc->table('users')->where('id', $userId)->delete();
    }

    /**
     * user_roles_select: paciente NO lee roles de otro.
     */
    public function test_positiva_user_roles_select_otro_negado(): void
    {
        $mc = DB::connection('pgsql_migration');

        $userId = \Illuminate\Support\Str::uuid()->toString();
        $otherId = \Illuminate\Support\Str::uuid()->toString();
        $mc->table('users')->insert([
            ['id' => $userId, 'name' => 'Self', 'last_name' => 'T', 'email' => 'roleself2_' . \Illuminate\Support\Str::random(5) . '@test.com', 'password' => bcrypt('p'), 'timezone' => 'UTC', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $otherId, 'name' => 'Other', 'last_name' => 'T', 'email' => 'roleother_' . \Illuminate\Support\Str::random(5) . '@test.com', 'password' => bcrypt('p'), 'timezone' => 'UTC', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $patientRole = $mc->table('roles')->where('name', 'patient')->first();
        $mc->table('user_roles')->insert(['user_id' => $otherId, 'role_id' => $patientRole->id]);

        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new \PDO($dsn, $config['username'], $config['password']);
        $pdo->exec("SET app.current_user_id = '{$userId}'");
        $pdo->exec("SET app.current_user_role = 'patient'");

        $stmt = $pdo->query("SELECT user_id FROM user_roles WHERE user_id = '{$otherId}'");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertFalse($row, 'Paciente NO debe poder leer user_roles de otro');

        // Cleanup
        $mc->table('user_roles')->where('user_id', $otherId)->delete();
        $mc->table('audit_logs')->delete();
        $mc->table('users')->whereIn('id', [$userId, $otherId])->delete();
    }

    /**
     * fn_user_for_auth: app_runtime puede ejecutar la función y obtener password.
     * Verifica que el mecanismo de autenticación funciona vía SECURITY DEFINER.
     */
    public function test_positiva_fn_user_for_auth_retorna_credenciales(): void
    {
        $mc = DB::connection('pgsql_migration');

        $userId = \Illuminate\Support\Str::uuid()->toString();
        $email = 'authtest_' . \Illuminate\Support\Str::random(5) . '@test.com';
        $mc->table('users')->insert([
            'id' => $userId, 'name' => 'Auth', 'last_name' => 'Test',
            'email' => $email, 'password' => bcrypt('testpass'),
            'timezone' => 'UTC', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new \PDO($dsn, $config['username'], $config['password']);
        // Sin contexto de usuario — simula login
        $pdo->exec("SET app.current_user_id = ''");
        $pdo->exec("SET app.current_user_role = 'guest'");

        // fn_user_for_auth devuelve password (SECURITY DEFINER)
        $stmt = $pdo->prepare("SELECT * FROM fn_user_for_auth(?)");
        $stmt->execute([$email]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row, 'fn_user_for_auth debe devolver el usuario por email');
        $this->assertEquals($userId, $row['id']);
        $this->assertNotEmpty($row['password'], 'fn_user_for_auth debe incluir password');
        $this->assertArrayNotHasKey('remember_token', $row, 'fn_user_for_auth NO debe devolver remember_token (Hallazgo 18)');

        // Pero SELECT directo sobre password NO funciona
        try {
            $pdo->query("SELECT password FROM users WHERE id = '{$userId}'");
            $this->fail('SELECT directo sobre password DEBE fallar');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('permission denied', $e->getMessage());
        }

        // SELECT remember_token directo también falla
        try {
            $pdo->query("SELECT remember_token FROM users WHERE id = '{$userId}'");
            $this->fail('SELECT directo sobre remember_token DEBE fallar');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('permission denied', $e->getMessage());
        }

        // Cleanup
        $mc->table('audit_logs')->delete();
        $mc->table('users')->where('id', $userId)->delete();
    }

    /**
     * v_schedule_blocks_availability: paciente lee bloqueos sin reason.
     */
    public function test_positiva_v_schedule_blocks_sin_reason(): void
    {
        $mc = DB::connection('pgsql_migration');

        $doctorUserId = \Illuminate\Support\Str::uuid()->toString();
        $patientId = \Illuminate\Support\Str::uuid()->toString();
        $mc->table('users')->insert([
            ['id' => $doctorUserId, 'name' => 'DocBlock', 'last_name' => 'T', 'email' => 'docblk_' . \Illuminate\Support\Str::random(5) . '@test.com', 'password' => bcrypt('p'), 'timezone' => 'UTC', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $patientId, 'name' => 'PatBlock', 'last_name' => 'T', 'email' => 'patblk_' . \Illuminate\Support\Str::random(5) . '@test.com', 'password' => bcrypt('p'), 'timezone' => 'UTC', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $dpId = \Illuminate\Support\Str::uuid()->toString();
        $mc->table('doctor_profiles')->insert([
            'id' => $dpId, 'user_id' => $doctorUserId, 'status' => 'approved',
            'license_number' => 'BLK001', 'consultation_fee' => 50.00,
            'university' => 'TestU', 'description' => 'Test',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $blockId = \Illuminate\Support\Str::uuid()->toString();
        $mc->table('schedule_blocks')->insert([
            'id' => $blockId, 'doctor_profile_id' => $dpId,
            'blocked_date' => '2026-12-25',
            'franja' => '[08:00:00, 12:00:00)',
            'reason' => 'Cirugía programada — dato sensible',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new \PDO($dsn, $config['username'], $config['password']);
        $pdo->exec("SET app.current_user_id = '{$patientId}'");
        $pdo->exec("SET app.current_user_role = 'patient'");

        // Vista pública: ve la franja pero NO el motivo
        $stmt = $pdo->query("SELECT id, doctor_profile_id, blocked_date, franja FROM v_schedule_blocks_availability WHERE id = '{$blockId}'");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row, 'Paciente debe ver bloqueo de doctor aprobado en vista');
        $this->assertEquals($dpId, $row['doctor_profile_id']);

        // La vista NO tiene columna reason
        $columns = array_keys($row);
        $this->assertNotContains('reason', $columns, 'v_schedule_blocks_availability NO debe exponer reason');

        // Cleanup
        $mc->table('schedule_blocks')->where('id', $blockId)->delete();
        $mc->table('doctor_profiles')->where('id', $dpId)->delete();
        $mc->table('audit_logs')->delete();
        $mc->table('users')->whereIn('id', [$doctorUserId, $patientId])->delete();
    }

    /**
     * fn_rotate_remember_token: solo podés rotar TU propio token.
     * Genera valor server-side. Hallazgo 19.
     */
    public function test_positiva_fn_rotate_remember_token(): void
    {
        $mc = DB::connection('pgsql_migration');

        $userId = \Illuminate\Support\Str::uuid()->toString();
        $mc->table('users')->insert([
            'id' => $userId, 'name' => 'Rotate', 'last_name' => 'Test',
            'email' => 'rotate_' . \Illuminate\Support\Str::random(5) . '@test.com',
            'password' => bcrypt('test'), 'timezone' => 'UTC', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new \PDO($dsn, $config['username'], $config['password']);

        // Con contexto del usuario
        $pdo->exec("SET app.current_user_id = '{$userId}'");
        $pdo->exec("SET app.current_user_role = 'patient'");

        $stmt = $pdo->query('SELECT fn_rotate_remember_token()');
        $token = $stmt->fetchColumn();

        $this->assertNotEmpty($token, 'fn_rotate_remember_token debe devolver un token');
        $this->assertEquals(64, strlen($token), 'Token debe ser de 64 hex chars (32 bytes)');

        // Sin contexto de usuario — debe fallar
        $pdo2 = new \PDO($dsn, $config['username'], $config['password']);
        $pdo2->exec("SET app.current_user_id = ''");

        try {
            $pdo2->query('SELECT fn_rotate_remember_token()');
            $this->fail('fn_rotate_remember_token SIN contexto DEBE fallar');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('app.current_user_id', $e->getMessage());
        }

        // Cleanup
        $mc->table('audit_logs')->delete();
        $mc->table('users')->where('id', $userId)->delete();
    }

    /**
     * fn_user_by_remember_token: comparación interna del token.
     * El token nunca sale de la base. Hallazgo 18.
     */
    public function test_positiva_fn_user_by_remember_token(): void
    {
        $mc = DB::connection('pgsql_migration');

        $userId = \Illuminate\Support\Str::uuid()->toString();
        $mc->table('users')->insert([
            'id' => $userId, 'name' => 'Token', 'last_name' => 'Test',
            'email' => 'token_' . \Illuminate\Support\Str::random(5) . '@test.com',
            'password' => bcrypt('test'), 'timezone' => 'UTC', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new \PDO($dsn, $config['username'], $config['password']);

        // Primero rotar para tener un token
        $pdo->exec("SET app.current_user_id = '{$userId}'");
        $pdo->exec("SET app.current_user_role = 'patient'");

        $stmt = $pdo->query('SELECT fn_rotate_remember_token()');
        $token = $stmt->fetchColumn();

        // Comparación correcta — devuelve el id
        $stmt2 = $pdo->prepare('SELECT fn_user_by_remember_token(?::uuid, ?)');
        $stmt2->execute([$userId, $token]);
        $result = $stmt2->fetchColumn();
        $this->assertEquals($userId, $result, 'Token correcto debe devolver el id');

        // Token incorrecto — devuelve null
        $stmt3 = $pdo->prepare('SELECT fn_user_by_remember_token(?::uuid, ?)');
        $stmt3->execute([$userId, 'token_falso']);
        $result2 = $stmt3->fetchColumn();
        $this->assertEmpty($result2, 'Token incorrecto NO debe devolver nada');

        // Cleanup
        $mc->table('audit_logs')->delete();
        $mc->table('users')->where('id', $userId)->delete();
    }

    /**
     * Barrera: las columnas del scope global de User.php deben coincidir
     * con information_schema.column_privileges para app_runtime en users.
     *
     * Si alguien agrega una columna a la tabla y la otorga en la migración
     * pero no la agrega al scope del modelo (o viceversa), este test falla.
     */
    public function test_barrera_columnas_user_scope_vs_grant(): void
    {
        // Leer las columnas del GRANT SELECT de app_runtime sobre users
        $grants = DB::connection('pgsql_migration')->select(
            "SELECT column_name FROM information_schema.column_privileges
             WHERE table_name = 'users'
               AND grantee = 'app_runtime'
               AND privilege_type = 'SELECT'
             ORDER BY column_name"
        );
        $grantedColumns = array_map(fn($r) => $r->column_name, $grants);
        sort($grantedColumns);

        // Leer las columnas del scope global del modelo User
        $reflection = new \ReflectionClass(\App\Models\User::class);
        $const = $reflection->getReflectionConstant('SELECTABLE_COLUMNS');
        $this->assertNotFalse($const, 'User::SELECTABLE_COLUMNS debe existir');
        $scopeColumns = $const->getValue();
        sort($scopeColumns);

        $this->assertEquals(
            $grantedColumns,
            $scopeColumns,
            'Las columnas del scope global de User::SELECTABLE_COLUMNS DEBEN coincidir '
            . 'con las columnas otorgadas vía GRANT SELECT a app_runtime en users. '
            . 'Divergencia detectada: scope=[' . implode(',', $scopeColumns) . '] '
            . 'vs grant=[' . implode(',', $grantedColumns) . ']'
        );
    }

    /**
     * schedule_blocks.reason NO es legible sin la vista.
     * Verifica Hallazgo 20: REVOKE SELECT tabla + política sin cláusula pública.
     */
    public function test_schedule_blocks_reason_no_legible_por_paciente(): void
    {
        $mc = DB::connection('pgsql_migration');

        $doctorUserId = \Illuminate\Support\Str::uuid()->toString();
        $patientId = \Illuminate\Support\Str::uuid()->toString();
        $dpId = \Illuminate\Support\Str::uuid()->toString();

        $mc->table('users')->insert([
            ['id' => $doctorUserId, 'name' => 'Dr', 'last_name' => 'Block',
             'email' => 'drblock_' . \Illuminate\Support\Str::random(5) . '@test.com',
             'password' => bcrypt('x'), 'timezone' => 'UTC', 'is_active' => true,
             'created_at' => now(), 'updated_at' => now()],
            ['id' => $patientId, 'name' => 'Pat', 'last_name' => 'Block',
             'email' => 'patblock_' . \Illuminate\Support\Str::random(5) . '@test.com',
             'password' => bcrypt('x'), 'timezone' => 'UTC', 'is_active' => true,
             'created_at' => now(), 'updated_at' => now()],
        ]);

        $patientRole = \App\Models\Role::where('name', 'patient')->first();
        $mc->table('user_roles')->insert(['user_id' => $patientId, 'role_id' => $patientRole->id]);

        $mc->table('doctor_profiles')->insert([
            'id' => $dpId, 'user_id' => $doctorUserId, 'status' => 'approved',
            'license_number' => 'BLK002', 'consultation_fee' => 50.00,
            'university' => 'TestU', 'description' => 'Test',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $blockId = \Illuminate\Support\Str::uuid()->toString();
        $mc->table('schedule_blocks')->insert([
            'id' => $blockId, 'doctor_profile_id' => $dpId,
            'blocked_date' => '2026-12-26',
            'franja' => '[08:00:00, 12:00:00)',
            'reason' => 'Cirugía programada — secreto',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        $pdo = new \PDO($dsn, $config['username'], $config['password']);
        $pdo->exec("SET app.current_user_id = '{$patientId}'");
        $pdo->exec("SET app.current_user_role = 'patient'");

        // Paciente NO puede leer schedule_blocks directamente
        try {
            $pdo->query('SELECT reason FROM schedule_blocks');
            $this->fail('Paciente NO debe poder SELECT schedule_blocks directamente');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('permission denied', $e->getMessage());
        }

        // Cleanup
        $mc->table('schedule_blocks')->where('id', $blockId)->delete();
        $mc->table('doctor_profiles')->where('id', $dpId)->delete();
        $mc->table('audit_logs')->delete();
        $mc->table('user_roles')->where('user_id', $patientId)->delete();
        $mc->table('users')->whereIn('id', [$doctorUserId, $patientId])->delete();
    }

    /**
     * Deuda: flujo legítimo de auth (login, registro, verificación email)
     * requiere RF-01. Marcado como skipped con visibilidad permanente.
     */
    public function test_auth_login_flujo_legitimo(): void
    {
        $this->markTestSkipped('PENDIENTE RF-01: falta prueba del flujo de login vía SecureEloquentUserProvider + fn_user_for_auth');
    }
}
