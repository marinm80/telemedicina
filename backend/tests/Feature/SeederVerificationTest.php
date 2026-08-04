<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DoctorProfile;
use App\Models\PatientProfile;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SeederVerificationTest extends TestCase
{
    /**
     * Verificar que las 4 cuentas de demostración creadas por el Seeder
     * pueden autenticarse, son redirigidas según su rol y cargan sus datos bajo RLS.
     */
    public function test_verificacion_cuatro_cuentas_demostracion(): void
    {
        // Correr el seeder de la aplicación
        $this->seed();

        $accounts = [
            [
                'email'              => 'admin@telemedicina.com',
                'password'           => 'Password123!',
                'expected_role'      => 'admin',
                'expected_redirect'  => '/admin',
            ],
            [
                'email'              => 'doctor@telemedicina.com',
                'password'           => 'Password123!',
                'expected_role'      => 'doctor',
                'expected_redirect'  => '/admin',
            ],
            [
                'email'              => 'paciente@telemedicina.com',
                'password'           => 'Password123!',
                'expected_role'      => 'patient',
                'expected_redirect'  => '/admin',
            ],
            [
                'email'              => 'agente@telemedicina.com',
                'password'           => 'Password123!',
                'expected_role'      => 'agent',
                'expected_redirect'  => '/admin',
            ],
        ];

        foreach ($accounts as $acc) {
            // 1. Intentar inicio de sesión
            $response = $this->post('/login', [
                'email'    => $acc['email'],
                'password' => $acc['password'],
            ]);

            $response->assertStatus(302);
            $response->assertRedirect($acc['expected_redirect']);
            $this->assertAuthenticated();

            $user = Auth::user();
            $this->assertNotNull($user, "La sesión debe estar activa para {$acc['email']}");
            $this->assertEquals($acc['expected_role'], $user->role, "El rol de {$acc['email']} debe ser {$acc['expected_role']}");

            // 2. Verificar datos en base de datos bajo contexto RLS
            DB::statement("SET app.current_user_id = '{$user->id}'");
            DB::statement("SET app.current_user_role = '{$user->role}'");

            if ($user->role === 'doctor') {
                $docProfile = DoctorProfile::where('user_id', $user->id)->first();
                $this->assertNotNull($docProfile, 'El perfil de médico debe existir bajo RLS');
                $this->assertEquals('approved', $docProfile->status);

                $schedulesCount = Schedule::where('doctor_profile_id', $docProfile->id)->count();
                $this->assertGreaterThan(0, $schedulesCount, 'El médico debe tener al menos una franja horaria');
            }

            if ($user->role === 'patient') {
                $patProfile = PatientProfile::where('user_id', $user->id)->first();
                $this->assertNotNull($patProfile, 'El perfil de paciente debe existir bajo RLS');
                $this->assertEquals('+56912345678', $patProfile->phone);
            }

            // Hacer logout para probar el siguiente usuario
            $this->post('/logout');
            $this->assertGuest();
        }
    }
}
