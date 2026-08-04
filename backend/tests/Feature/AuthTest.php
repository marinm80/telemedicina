<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Pruebas exhaustivas para RF-01: Autenticación, Rate Limiting,
 * Rotación de Remember Token en Logout y Contexto RLS Post-Login.
 */

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class AuthTest extends TestCase
{
    /**
     * 1. Login exitoso con credenciales correctas + Verificación RLS post-login.
     */
    public function test_login_exitoso_y_aislamiento_rls(): void
    {
        $mc = DB::connection('pgsql_migration');
        $patientRole = Role::where('name', 'patient')->first();
        $doctorRole = Role::where('name', 'doctor')->first();

        $patientId = \Illuminate\Support\Str::uuid()->toString();
        $patientEmail = 'paciente_authtest_' . \Illuminate\Support\Str::random(5) . '@test.com';
        $rawPassword = 'Password123!';

        $mc->table('users')->insert([
            'id' => $patientId,
            'name' => 'Paciente Auth',
            'last_name' => 'Test',
            'email' => $patientEmail,
            'password' => bcrypt($rawPassword),
            'timezone' => 'America/Tegucigalpa',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $mc->table('user_roles')->insert(['user_id' => $patientId, 'role_id' => $patientRole->id]);

        $otherId = \Illuminate\Support\Str::uuid()->toString();
        $otherEmail = 'otro_authtest_' . \Illuminate\Support\Str::random(5) . '@test.com';
        $mc->table('users')->insert([
            'id' => $otherId,
            'name' => 'Otro Paciente',
            'last_name' => 'Test',
            'email' => $otherEmail,
            'password' => bcrypt($rawPassword),
            'timezone' => 'UTC',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $mc->table('user_roles')->insert(['user_id' => $otherId, 'role_id' => $patientRole->id]);

        $doctorId = \Illuminate\Support\Str::uuid()->toString();
        $mc->table('users')->insert([
            'id' => $doctorId,
            'name' => 'Dr Auth',
            'last_name' => 'Test',
            'email' => 'doc_authtest_' . \Illuminate\Support\Str::random(5) . '@test.com',
            'password' => bcrypt($rawPassword),
            'timezone' => 'UTC',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $mc->table('user_roles')->insert(['user_id' => $doctorId, 'role_id' => $doctorRole->id]);

        // Cita propia (paciente 1) y cita ajena (paciente 2)
        $mc->table('appointments')->insert([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'patient_id' => $patientId,
            'doctor_id' => $doctorId,
            'franja' => '[2026-09-10 09:00:00+00, 2026-09-10 09:30:00+00)',
            'status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $mc->table('appointments')->insert([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'patient_id' => $otherId,
            'doctor_id' => $doctorId,
            'franja' => '[2026-09-10 10:00:00+00, 2026-09-10 10:30:00+00)',
            'status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Intentar Login POST
        $res = $this->post('/login', [
            'email' => $patientEmail,
            'password' => $rawPassword,
        ]);
        $res->assertStatus(302);

        DB::statement("SET app.current_user_id = '{$patientId}'");
        DB::statement("SET app.current_user_role = 'patient'");
        $patientUser = User::on('pgsql_migration')->find($patientId);
        $this->assertAuthenticatedAs($patientUser);

        // Aislamiento RLS en consultas tras el login
        $this->actingAs($patientUser);
        $myAppointments = Appointment::all();
        $this->assertCount(1, $myAppointments, 'El paciente solo ve su cita vía RLS');
        $this->assertEquals($patientId, $myAppointments->first()->patient_id);
    }

    /**
     * 2. Anti-enumeración de usuarios: clave incorrecta vs correo inexistente dan el mismo mensaje.
     */
    public function test_login_fallido_anti_enumeracion(): void
    {
        $mc = DB::connection('pgsql_migration');
        $patientRole = Role::where('name', 'patient')->first();

        $patientId = \Illuminate\Support\Str::uuid()->toString();
        $patientEmail = 'paciente_anti_' . \Illuminate\Support\Str::random(5) . '@test.com';
        $rawPassword = 'Password123!';

        $mc->table('users')->insert([
            'id' => $patientId,
            'name' => 'Paciente Anti',
            'last_name' => 'Test',
            'email' => $patientEmail,
            'password' => bcrypt($rawPassword),
            'timezone' => 'UTC',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $mc->table('user_roles')->insert(['user_id' => $patientId, 'role_id' => $patientRole->id]);

        // Contraseña incorrecta
        $resWrongPassword = $this->post('/login', [
            'email' => $patientEmail,
            'password' => 'ClaveTotalmenteErronea1!',
        ]);
        $resWrongPassword->assertStatus(302);
        $resWrongPassword->assertSessionHasErrors('email');
        $msgWrongPassword = session('errors')->get('email')[0];

        // Limpiar rate limiter para el siguiente intento
        RateLimiter::clear(mb_strtolower($patientEmail).'|127.0.0.1');

        // Correo inexistente
        $resNonExistent = $this->post('/login', [
            'email' => 'correo_inexistente_' . \Illuminate\Support\Str::random(5) . '@test.com',
            'password' => 'Password123!',
        ]);
        $resNonExistent->assertStatus(302);
        $resNonExistent->assertSessionHasErrors('email');
        $msgNonExistent = session('errors')->get('email')[0];

        $this->assertEquals($msgWrongPassword, $msgNonExistent, 'Los mensajes de error deben ser idénticos');
        $this->assertGuest();
    }

    /**
     * 3. Usuario inactivo (is_active = false) es filtrado por fn_user_for_auth y no puede entrar.
     */
    public function test_login_bloqueado_para_usuario_inactivo(): void
    {
        $mc = DB::connection('pgsql_migration');
        $patientRole = Role::where('name', 'patient')->first();

        $inactiveId = \Illuminate\Support\Str::uuid()->toString();
        $inactiveEmail = 'inactivo_test_' . \Illuminate\Support\Str::random(5) . '@test.com';
        $rawPassword = 'Password123!';

        $mc->table('users')->insert([
            'id' => $inactiveId,
            'name' => 'Usuario Inactivo',
            'last_name' => 'Test',
            'email' => $inactiveEmail,
            'password' => bcrypt($rawPassword),
            'timezone' => 'UTC',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $mc->table('user_roles')->insert(['user_id' => $inactiveId, 'role_id' => $patientRole->id]);

        // Verificación previa directa en función PostgreSQL
        $dbRow = DB::selectOne('SELECT * FROM fn_user_for_auth(?)', [$inactiveEmail]);
        $this->assertNull($dbRow, 'fn_user_for_auth debe devolver NULL para usuarios inactivos');

        // Intento por endpoint /login
        $res = $this->post('/login', [
            'email' => $inactiveEmail,
            'password' => $rawPassword,
        ]);
        $res->assertStatus(302);
        $res->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /**
     * 4. Cierre de sesión (logout) rota el remember_token vía fn_rotate_remember_token() invalidadando el token viejo.
     */
    public function test_logout_rota_remember_token_y_invalida_token_viejo(): void
    {
        $mc = DB::connection('pgsql_migration');
        $patientRole = Role::where('name', 'patient')->first();

        $userId = \Illuminate\Support\Str::uuid()->toString();
        $userEmail = 'paciente_logout_' . \Illuminate\Support\Str::random(5) . '@test.com';
        $rawPassword = 'Password123!';

        $mc->table('users')->insert([
            'id' => $userId,
            'name' => 'Paciente Logout',
            'last_name' => 'Test',
            'email' => $userEmail,
            'password' => bcrypt($rawPassword),
            'timezone' => 'UTC',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $mc->table('user_roles')->insert(['user_id' => $userId, 'role_id' => $patientRole->id]);

        // Generar un token inicial simulado en DB
        DB::statement("SET app.current_user_id = '{$userId}'");
        $tokenViejo = DB::selectOne('SELECT fn_rotate_remember_token()')->fn_rotate_remember_token;
        $this->assertNotEmpty($tokenViejo);

        // Verificar que fn_user_by_remember_token lo reconoce
        $idResult = DB::selectOne('SELECT fn_user_by_remember_token(?::uuid, ?)', [$userId, $tokenViejo])->fn_user_by_remember_token;
        $this->assertEquals($userId, $idResult);

        // Iniciar sesión y luego hacer logout
        DB::statement("SET app.current_user_id = '{$userId}'");
        DB::statement("SET app.current_user_role = 'patient'");
        $userObj = User::on('pgsql_migration')->find($userId);

        $this->actingAs($userObj);
        $resLogout = $this->post('/logout');
        $resLogout->assertStatus(302);
        $this->assertGuest();

        // El token viejo YA NO debe coincidir en la base de datos tras el logout
        $idResultDespues = DB::selectOne('SELECT fn_user_by_remember_token(?::uuid, ?)', [$userId, $tokenViejo])->fn_user_by_remember_token;
        $this->assertNull($idResultDespues, 'El token viejo NO debe servir tras el logout');
    }

    /**
     * 5. Rate Limiter: bloquea peticiones tras 5 intentos fallidos consecutivos.
     */
    public function test_rate_limiter_bloquea_fuerza_bruta_tras_5_intentos(): void
    {
        $email = 'bruteforce_' . \Illuminate\Support\Str::random(5) . '@test.com';

        // 5 intentos fallidos
        for ($i = 0; $i < 5; $i++) {
            $res = $this->post('/login', [
                'email' => $email,
                'password' => 'WrongPass!',
            ]);
            $res->assertStatus(302);
        }

        // El 6to intento debe ser bloqueado por RateLimiter con error de throttle
        $resThrottled = $this->post('/login', [
            'email' => $email,
            'password' => 'WrongPass!',
        ]);
        $resThrottled->assertStatus(302);
        $resThrottled->assertSessionHasErrors('email');

        $errorMessage = session('errors')->get('email');
        $errorStr = is_array($errorMessage) ? $errorMessage[0] : (string) $errorMessage;
        $this->assertTrue(
            str_contains($errorStr, 'seconds') || str_contains($errorStr, 'segundos') || str_contains($errorStr, 'Too many'),
            'Debe contener mensaje de limitación de tasa (throttle)'
        );
    }

    /**
     * 6. Redirección según rol: admin -> /admin, paciente -> /.
     */
    public function test_redireccion_por_rol_tras_login(): void
    {
        $mc = DB::connection('pgsql_migration');
        $adminRole = Role::where('name', 'admin')->first();

        $adminId = \Illuminate\Support\Str::uuid()->toString();
        $adminEmail = 'admin_login_' . \Illuminate\Support\Str::random(5) . '@test.com';
        $rawPassword = 'Password123!';

        $mc->table('users')->insert([
            'id' => $adminId,
            'name' => 'Admin User',
            'last_name' => 'Test',
            'email' => $adminEmail,
            'password' => bcrypt($rawPassword),
            'timezone' => 'UTC',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $mc->table('user_roles')->insert(['user_id' => $adminId, 'role_id' => $adminRole->id]);

        $resAdmin = $this->post('/login', [
            'email' => $adminEmail,
            'password' => $rawPassword,
        ]);
        $resAdmin->assertRedirect('/admin');
    }
}
