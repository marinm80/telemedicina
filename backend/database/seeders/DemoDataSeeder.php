<?php
declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Establecer contexto RLS administrativo (session-level, antes de la transacción)
        $tempActorId = Str::uuid()->toString();
        DB::statement("SELECT set_config('app.current_user_role', 'admin', false)");
        DB::statement("SELECT set_config('app.current_user_id', '{$tempActorId}', false)");

        DB::transaction(function () use ($tempActorId) {
            
            $password = Hash::make('password');
            $now = now();
            
            // 1. Create specialties
            $specialties = [
                'Cardiología', 'Dermatología', 'Pediatría', 'Neurología', 
                'Traumatología', 'Psiquiatría', 'Medicina General', 'Ginecología'
            ];

            $specialtyIds = [];
            foreach ($specialties as $specName) {
                $specialty = DB::table('specialties')->where('name', $specName)->first();
                if (!$specialty) {
                    $id = Str::uuid()->toString();
                    DB::table('specialties')->insert([
                        'id' => $id,
                        'name' => $specName,
                        'description' => "Especialidad de $specName",
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $specialtyIds[$specName] = $id;
                } else {
                    $specialtyIds[$specName] = $specialty->id;
                }
            }

            // Roles array for user_roles table if it's used
            $roles = Schema::hasTable('roles') ? DB::table('roles')->pluck('id', 'name')->toArray() : [];
            $hasRoleCol = Schema::hasColumn('users', 'role');
            $hasIsActiveCol = Schema::hasColumn('users', 'is_active');

            // 2. Create 5 doctors
            $doctors = [
                [
                    'name' => 'María', 'last_name' => 'García', 'email' => 'maria.garcia@salvia.test', 
                    'timezone' => 'America/Argentina/Buenos_Aires',
                    'specialties' => ['Cardiología'],
                    'schedule' => '[08:00:00,17:00:00)'
                ],
                [
                    'name' => 'Alejandro', 'last_name' => 'Ruiz', 'email' => 'alejandro.ruiz@salvia.test', 
                    'timezone' => 'America/Tegucigalpa',
                    'specialties' => ['Dermatología'],
                    'schedule' => '[10:00:00,18:00:00)'
                ],
                [
                    'name' => 'Lucía', 'last_name' => 'Fernández', 'email' => 'lucia.fernandez@salvia.test', 
                    'timezone' => 'America/Mexico_City',
                    'specialties' => ['Pediatría'],
                    'schedule' => '[08:00:00,17:00:00)'
                ],
                [
                    'id' => 'e894791a-215d-41a2-8aa8-60e702c37229',
                    'profile_id' => 'f17a288d-de23-4656-8615-67ba91114588',
                    'name' => 'Carlos', 'last_name' => 'Mendoza', 'email' => 'carlos.mendoza@salvia.test', 
                    'timezone' => 'America/Bogota',
                    'specialties' => ['Neurología'],
                    'schedule' => '[08:00:00,17:00:00)'
                ],
                [
                    'name' => 'Ana', 'last_name' => 'Torres', 'email' => 'ana.torres@salvia.test', 
                    'timezone' => 'America/Santo_Domingo',
                    'specialties' => ['Medicina General', 'Psiquiatría'],
                    'schedule' => '[07:00:00,15:00:00)'
                ]
            ];

            foreach ($doctors as $docData) {
                if (isset($docData['id'])) {
                    $userId = $docData['id'];
                    $user = DB::table('users')->where('id', $userId)->first();
                } else {
                    $user = DB::table('users')->where('email', $docData['email'])->first();
                    $userId = $user ? $user->id : Str::uuid()->toString();
                }

                if (!$user) {
                    $userData = [
                        'id' => $userId,
                        'name' => $docData['name'],
                        'last_name' => $docData['last_name'],
                        'email' => $docData['email'],
                        'password' => $password,
                        'timezone' => $docData['timezone'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    
                    if ($hasRoleCol) $userData['role'] = 'doctor';
                    if ($hasIsActiveCol) $userData['is_active'] = true;
                    
                    DB::table('users')->insert($userData);
                    
                    if (isset($roles['doctor'])) {
                        DB::table('user_roles')->insertOrIgnore([
                            'user_id' => $userId,
                            'role_id' => $roles['doctor'],
                        ]);
                    }
                }

                // Profile
                if (isset($docData['profile_id'])) {
                    $profileId = $docData['profile_id'];
                    $profile = DB::table('doctor_profiles')->where('id', $profileId)->first();
                } else {
                    $profile = DB::table('doctor_profiles')->where('user_id', $userId)->first();
                    $profileId = $profile ? $profile->id : Str::uuid()->toString();
                }

                if (!$profile) {
                    DB::table('doctor_profiles')->insert([
                        'id' => $profileId,
                        'user_id' => $userId,
                        'status' => 'approved',
                        'consultation_fee' => 50000.00,
                        'description' => "Especialista con amplia experiencia.",
                        'years_experience' => 10,
                        'university' => 'Universidad de la Ciudad',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                // Specialties
                foreach ($docData['specialties'] as $specName) {
                    $specId = $specialtyIds[$specName];
                    $hasSpec = DB::table('doctor_specialties')
                        ->where('doctor_profile_id', $profileId)
                        ->where('specialty_id', $specId)
                        ->exists();

                    if (!$hasSpec) {
                        DB::table('doctor_specialties')->insert([
                            'doctor_profile_id' => $profileId,
                            'specialty_id' => $specId,
                        ]);
                    }
                }

                // Schedules (Lunes a Viernes / 1 a 5)
                for ($day = 1; $day <= 5; $day++) {
                    $hasSchedule = DB::table('schedules')
                        ->where('doctor_profile_id', $profileId)
                        ->where('day_of_week', $day)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (!$hasSchedule) {
                        DB::table('schedules')->insert([
                            'id' => Str::uuid()->toString(),
                            'doctor_profile_id' => $profileId,
                            'day_of_week' => $day,
                            'franja' => $docData['schedule'],
                            'slot_duration' => 30,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }

            // 5. Create 2 patient users
            $patients = [
                ['name' => 'Juan', 'last_name' => 'Pérez', 'email' => 'patient@salvia.test'],
                ['name' => 'María', 'last_name' => 'López', 'email' => 'maria@salvia.test'],
            ];

            foreach ($patients as $patData) {
                $user = DB::table('users')->where('email', $patData['email'])->first();
                $userId = $user ? $user->id : Str::uuid()->toString();

                if (!$user) {
                    $userData = [
                        'id' => $userId,
                        'name' => $patData['name'],
                        'last_name' => $patData['last_name'],
                        'email' => $patData['email'],
                        'password' => $password,
                        'timezone' => 'America/Santiago', // default for test
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    
                    if ($hasRoleCol) $userData['role'] = 'patient';
                    if ($hasIsActiveCol) $userData['is_active'] = true;
                    
                    DB::table('users')->insert($userData);

                    if (isset($roles['patient'])) {
                        DB::table('user_roles')->insertOrIgnore([
                            'user_id' => $userId,
                            'role_id' => $roles['patient'],
                        ]);
                    }
                }
            }
            
            // Restaurar contexto administrativo al finalizar la transacción de forma segura
            DB::statement("SELECT set_config('app.current_user_role', '', false)");
        });
    }
}
