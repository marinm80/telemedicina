<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InertiaRoutesTest extends TestCase
{
    /**
     * Test 1: Verificar que la ruta /directory es accesible públicamente.
     */
    public function test_directory_accesible_publicamente(): void
    {
        $response = $this->get('/directory');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Directory')
            ->has('specialties')
            ->has('doctors')
            ->has('filters')
        );
    }

    /**
     * Test 2: RLS en Dashboards por Rol (Opción B).
     */
    public function test_dashboards_por_rol_opcion_b(): void
    {
        $mc = DB::connection('pgsql_migration');
        $roles = DB::table('roles')->pluck('id', 'name')->toArray();

        // 1. Paciente ingresa a /admin y recibe PatientDashboard
        $patId = \Illuminate\Support\Str::uuid()->toString();
        $mc->table('users')->insert([
            'id' => $patId, 'name' => 'Paciente', 'last_name' => 'DashTest',
            'email' => 'paciente_dash_' . \Illuminate\Support\Str::random(5) . '@test.com',
            'password' => bcrypt('Password123!'), 'timezone' => 'UTC', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $mc->table('user_roles')->insert(['user_id' => $patId, 'role_id' => $roles['patient']]);

        $patientUser = User::on('pgsql_migration')->find($patId);
        $this->actingAs($patientUser);

        $resPat = $this->get('/admin');
        $resPat->assertStatus(200);
        $resPat->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/PatientDashboard')
            ->has('upcoming_appointments')
            ->has('past_consultations_count')
            ->has('active_prescriptions_count')
        );

        // 2. Médico ingresa a /admin y recibe DoctorDashboard
        $docId = \Illuminate\Support\Str::uuid()->toString();
        $mc->table('users')->insert([
            'id' => $docId, 'name' => 'Dr', 'last_name' => 'DashTest',
            'email' => 'doc_dash_' . \Illuminate\Support\Str::random(5) . '@test.com',
            'password' => bcrypt('Password123!'), 'timezone' => 'UTC', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $mc->table('user_roles')->insert(['user_id' => $docId, 'role_id' => $roles['doctor']]);
        $mc->table('doctor_profiles')->insert([
            'id' => \Illuminate\Support\Str::uuid()->toString(), 'user_id' => $docId,
            'license_number' => 'MED-' . \Illuminate\Support\Str::random(5),
            'university' => 'UChile', 'years_experience' => 5,
            'description' => 'Test', 'consultation_fee' => 30000, 'status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $doctorUser = User::on('pgsql_migration')->find($docId);
        $this->actingAs($doctorUser);

        $resDoc = $this->get('/admin');
        $resDoc->assertStatus(200);
        $resDoc->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/DoctorDashboard')
            ->has('profile_status')
            ->has('today_appointments')
            ->has('pending_notes_count')
            ->has('month_earnings')
        );

        // 3. Admin ingresa a /admin y recibe AdminDashboard
        $adminId = \Illuminate\Support\Str::uuid()->toString();
        $mc->table('users')->insert([
            'id' => $adminId, 'name' => 'Admin', 'last_name' => 'DashTest',
            'email' => 'admin_dash_' . \Illuminate\Support\Str::random(5) . '@test.com',
            'password' => bcrypt('Password123!'), 'timezone' => 'UTC', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $mc->table('user_roles')->insert(['user_id' => $adminId, 'role_id' => $roles['admin']]);

        $adminUser = User::on('pgsql_migration')->find($adminId);
        $this->actingAs($adminUser);

        $resAdm = $this->get('/admin');
        $resAdm->assertStatus(200);
        $resAdm->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/AdminDashboard')
            ->has('total_users')
            ->has('pending_doctor_approvals')
            ->has('monthly_appointments_count')
            ->has('total_revenue')
        );
    }

    /**
     * Test 3: Middleware de Rol rechaza accesos no autorizados (403 Forbidden).
     */
    public function test_middleware_de_rol_bloquea_paciente_en_agenda(): void
    {
        $mc = DB::connection('pgsql_migration');
        $roles = DB::table('roles')->pluck('id', 'name')->toArray();

        $patId = \Illuminate\Support\Str::uuid()->toString();
        $mc->table('users')->insert([
            'id' => $patId, 'name' => 'Paciente', 'last_name' => 'BlockTest',
            'email' => 'paciente_block_' . \Illuminate\Support\Str::random(5) . '@test.com',
            'password' => bcrypt('Password123!'), 'timezone' => 'UTC', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $mc->table('user_roles')->insert(['user_id' => $patId, 'role_id' => $roles['patient']]);

        $patient = User::on('pgsql_migration')->find($patId);
        $this->actingAs($patient);

        $response = $this->get('/agenda');
        $response->assertStatus(403);
    }

    /**
     * Test 4: Aislamiento RLS en /appointments: Paciente B no ve citas de Paciente A.
     */
    public function test_aislamiento_rls_citas_entre_usuarios(): void
    {
        $mc = DB::connection('pgsql_migration');
        $roles = DB::table('roles')->pluck('id', 'name')->toArray();

        $docId = \Illuminate\Support\Str::uuid()->toString();
        $mc->table('users')->insert([
            'id' => $docId, 'name' => 'Dr', 'last_name' => 'CitaTest',
            'email' => 'doc_cita_' . \Illuminate\Support\Str::random(5) . '@test.com',
            'password' => bcrypt('Password123!'), 'timezone' => 'UTC', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $mc->table('user_roles')->insert(['user_id' => $docId, 'role_id' => $roles['doctor']]);

        // Crear Paciente A con cita
        $patAId = \Illuminate\Support\Str::uuid()->toString();
        $mc->table('users')->insert([
            'id' => $patAId, 'name' => 'Paciente A', 'last_name' => 'Test',
            'email' => 'pacienteA_' . \Illuminate\Support\Str::random(5) . '@test.com',
            'password' => bcrypt('Password123!'), 'timezone' => 'UTC', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $mc->table('user_roles')->insert(['user_id' => $patAId, 'role_id' => $roles['patient']]);

        $mc->table('appointments')->insert([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'patient_id' => $patAId,
            'doctor_id' => $docId,
            'franja' => '[2026-09-20 09:00:00+00, 2026-09-20 09:30:00+00)',
            'status' => 'confirmed',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Crear Paciente B sin citas
        $patBId = \Illuminate\Support\Str::uuid()->toString();
        $mc->table('users')->insert([
            'id' => $patBId, 'name' => 'Paciente B', 'last_name' => 'Test',
            'email' => 'pacienteB_' . \Illuminate\Support\Str::random(5) . '@test.com',
            'password' => bcrypt('Password123!'), 'timezone' => 'UTC', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $mc->table('user_roles')->insert(['user_id' => $patBId, 'role_id' => $roles['patient']]);

        // Consulta de Paciente A -> ve 1 cita
        $userA = User::on('pgsql_migration')->find($patAId);
        $this->actingAs($userA);
        $resA = $this->get('/appointments');
        $resA->assertStatus(200);
        $resA->assertInertia(fn (Assert $page) => $page
            ->component('Appointments/MyAppointments')
            ->has('appointments', 1)
        );

        // Consulta de Paciente B -> RLS entrega CERO filas (aislamiento verificado)
        $userB = User::on('pgsql_migration')->find($patBId);
        $this->actingAs($userB);
        $resB = $this->get('/appointments');
        $resB->assertStatus(200);
        $resB->assertInertia(fn (Assert $page) => $page
            ->component('Appointments/MyAppointments')
            ->has('appointments', 0)
        );
    }
}
