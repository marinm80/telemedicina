<?php
declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeder de demostración para entorno de desarrollo y portafolio.
     *
     * Ejercita las políticas RLS, triggers de auditoría y privilegios de columna
     * sobre la conexión por defecto (app_runtime) fijando el contexto de admin.
     */
    public function run(): void
    {
        // 1. Establecer contexto RLS administrativo inicial sobre app_runtime
        DB::statement("SELECT set_config('app.current_user_role', 'admin', false)");

        try {
            // 2. Obtener referencias de roles del sistema
            $roles = DB::table('roles')->pluck('id', 'name')->toArray();

            if (empty($roles)) {
                foreach (['admin', 'doctor', 'patient', 'agent'] as $roleName) {
                    $roleId = Str::uuid()->toString();
                    DB::table('roles')->insert([
                        'id'          => $roleId,
                        'name'        => $roleName,
                        'description' => "Rol de {$roleName}",
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                    $roles[$roleName] = $roleId;
                }
            }

            $passwordHash = Hash::make('Password123!');
            $allowedUserCols = ['id', 'name', 'last_name', 'email', 'timezone', 'is_active', 'created_at', 'updated_at'];

            // ID pre-generado para el Administrador
            $adminUser = DB::table('users')->select($allowedUserCols)->where('email', 'admin@telemedicina.com')->first();
            $adminId = $adminUser?->id ?? Str::uuid()->toString();

            // Para la asignación de rol del admin, el contexto current_user_id no debe ser igual al propio admin_id
            $tempActorId = Str::uuid()->toString();
            DB::statement("SELECT set_config('app.current_user_id', '{$tempActorId}', false)");

            // ===================================================================
            // 3. CUENTA 1: Administrador (Super Admin)
            // ===================================================================
            if (!$adminUser) {
                DB::table('users')->insert([
                    'id'         => $adminId,
                    'name'       => 'Administrador',
                    'last_name'  => 'Sistema',
                    'email'      => 'admin@telemedicina.com',
                    'password'   => $passwordHash,
                    'timezone'   => 'America/Santiago',
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('user_roles')->insert([
                    'user_id' => $adminId,
                    'role_id' => $roles['admin'],
                ]);
            }

            // Una vez creado el Admin en users, fijamos app.current_user_id = adminId (usuario real para fn_audit_log FK)
            DB::statement("SELECT set_config('app.current_user_id', '{$adminId}', false)");

            // ===================================================================
            // 4. CUENTA 2: Médico Especialista (Aprobado + Perfil + Agenda)
            // ===================================================================
            $doctorUser = DB::table('users')->select($allowedUserCols)->where('email', 'doctor@telemedicina.com')->first();
            $doctorId = $doctorUser?->id ?? Str::uuid()->toString();

            if (!$doctorUser) {
                DB::table('users')->insert([
                    'id'         => $doctorId,
                    'name'       => 'Dr. Carlos',
                    'last_name'  => 'Mendoza',
                    'email'      => 'doctor@telemedicina.com',
                    'password'   => $passwordHash,
                    'timezone'   => 'America/Santiago',
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('user_roles')->insert([
                    'user_id' => $doctorId,
                    'role_id' => $roles['doctor'],
                ]);
            }

            // Perfil Médico
            $doctorProfile = DB::table('doctor_profiles')->where('user_id', $doctorId)->first();
            $doctorProfileId = $doctorProfile?->id ?? Str::uuid()->toString();

            if (!$doctorProfile) {
                DB::table('doctor_profiles')->insert([
                    'id'               => $doctorProfileId,
                    'user_id'          => $doctorId,
                    'license_number'   => 'MED-994422',
                    'university'       => 'Universidad de Chile',
                    'years_experience' => 12,
                    'description'      => 'Especialista en Cardiología y enfermedades cardiovasculares con amplia trayectoria en telemedicina.',
                    'consultation_fee' => 45000.00,
                    'status'           => 'approved',
                    'approved_at'      => now(),
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }

            // Especialidad de Cardiología
            $cardiology = DB::table('specialties')->where('name', 'Cardiología')->first();
            $cardiologyId = $cardiology?->id ?? Str::uuid()->toString();

            if (!$cardiology) {
                DB::table('specialties')->insert([
                    'id'          => $cardiologyId,
                    'name'        => 'Cardiología',
                    'description' => 'Prevención, diagnóstico y tratamiento de enfermedades cardiovasculares.',
                    'is_active'   => true,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            // Vincular especialidad
            $hasSpecialty = DB::table('doctor_specialties')
                ->where('doctor_profile_id', $doctorProfileId)
                ->where('specialty_id', $cardiologyId)
                ->exists();

            if (!$hasSpecialty) {
                DB::table('doctor_specialties')->insert([
                    'doctor_profile_id' => $doctorProfileId,
                    'specialty_id'      => $cardiologyId,
                    'created_at'        => now(),
                ]);
            }

            // Franja recurrente de atención (Lunes 09:00 a 17:00, slot de 30 min)
            $hasSchedule = DB::table('schedules')
                ->where('doctor_profile_id', $doctorProfileId)
                ->where('day_of_week', 1)
                ->whereNull('deleted_at')
                ->exists();

            if (!$hasSchedule) {
                DB::table('schedules')->insert([
                    'id'                => Str::uuid()->toString(),
                    'doctor_profile_id' => $doctorProfileId,
                    'day_of_week'       => 1, // Lunes
                    'franja'            => '[09:00:00, 17:00:00)',
                    'slot_duration'     => 30,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }

            // ===================================================================
            // 5. CUENTA 3: Paciente (Perfil de Paciente)
            // ===================================================================
            $patientUser = DB::table('users')->select($allowedUserCols)->where('email', 'paciente@telemedicina.com')->first();
            $patientId = $patientUser?->id ?? Str::uuid()->toString();

            if (!$patientUser) {
                DB::table('users')->insert([
                    'id'         => $patientId,
                    'name'       => 'María',
                    'last_name'  => 'González',
                    'email'      => 'paciente@telemedicina.com',
                    'password'   => $passwordHash,
                    'timezone'   => 'America/Santiago',
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('user_roles')->insert([
                    'user_id' => $patientId,
                    'role_id' => $roles['patient'],
                ]);
            }

            // Perfil Paciente
            $patientProfile = DB::table('patient_profiles')->where('user_id', $patientId)->first();
            if (!$patientProfile) {
                DB::table('patient_profiles')->insert([
                    'id'            => Str::uuid()->toString(),
                    'user_id'       => $patientId,
                    'phone'         => '+56912345678',
                    'date_of_birth' => '1990-05-15',
                    'gender'        => 'Femenino',
                    'address'       => 'Av. Providencia 1234, Depto 502, Santiago',
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }

            // ===================================================================
            // 6. CUENTA 4: Agente / Recepcionista
            // ===================================================================
            $agentUser = DB::table('users')->select($allowedUserCols)->where('email', 'agente@telemedicina.com')->first();
            if (!$agentUser) {
                $agentId = Str::uuid()->toString();
                DB::table('users')->insert([
                    'id'         => $agentId,
                    'name'       => 'Sofía',
                    'last_name'  => 'López',
                    'email'      => 'agente@telemedicina.com',
                    'password'   => $passwordHash,
                    'timezone'   => 'America/Santiago',
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('user_roles')->insert([
                    'user_id' => $agentId,
                    'role_id' => $roles['agent'],
                ]);
            }

            // 7. Datos de Demostración Extra
            $this->call(DemoDataSeeder::class);

        } finally {
            // 8. Limpiar contexto administrativo al finalizar la ejecución del seeder
            DB::statement("SELECT set_config('app.current_user_role', '', false)");
            DB::statement("SELECT set_config('app.current_user_id', '', false)");
        }
    }
}
