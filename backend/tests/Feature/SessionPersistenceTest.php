<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Cubre el hueco declarado en HANDOFF_LOGIN.md sección 5: la suite corre
 * con SESSION_DRIVER=array y ningún test existente encadena dos requests
 * HTTP reales reutilizando la cookie de sesión (todos usan actingAs(),
 * que evita por completo el ciclo cookie -> StartSession -> Auth::check()
 * que sí ocurre en el navegador).
 *
 * Este test reproduce exactamente ese ciclo: login real, y luego una
 * segunda request GET que reutiliza la cookie de sesión que devolvió el
 * login, sin actingAs() ni SET manual de los GUCs de RLS.
 */

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SessionPersistenceTest extends TestCase
{
    public function test_usuario_autenticado_sigue_autenticado_en_la_siguiente_request(): void
    {
        $mc = DB::connection('pgsql_migration');
        $patientRole = Role::where('name', 'patient')->first();

        $patientId = Str::uuid()->toString();
        $patientEmail = 'paciente_sesion_' . mb_strtolower(Str::random(5)) . '@test.com';
        $rawPassword = 'Password123!';

        $mc->table('users')->insert([
            'id' => $patientId,
            'name' => 'Paciente Sesion',
            'last_name' => 'Test',
            'email' => $patientEmail,
            'password' => bcrypt($rawPassword),
            'timezone' => 'UTC',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $mc->table('user_roles')->insert(['user_id' => $patientId, 'role_id' => $patientRole->id]);

        $login = $this->post('/login', [
            'email' => $patientEmail,
            'password' => $rawPassword,
        ]);
        $login->assertRedirect('/admin');

        // Tomar la cookie de sesión real que devolvió el login (encriptada,
        // tal cual la guardaría el navegador) y reenviarla en la siguiente
        // request, en vez de simular la autenticación con actingAs().
        $cookieName = config('session.cookie');
        $sessionCookieValue = null;
        foreach ($login->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $cookieName) {
                $sessionCookieValue = $cookie->getValue();
            }
        }
        $this->assertNotNull($sessionCookieValue, 'El login debe devolver la cookie de sesión ' . $cookieName);

        $admin = $this->withCookie($cookieName, $sessionCookieValue)->get('/admin');

        $admin->assertStatus(200);
        $this->assertAuthenticated();
    }
}
