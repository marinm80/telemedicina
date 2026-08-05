<?php
declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Usar conexión superuser para bypass RLS en seeders
        $db = DB::connection('pgsql_admin');

        $db->transaction(function () use ($db) {

            $password = Hash::make('password');
            $now = now();

            // Get role IDs
            $roles = $db->table('roles')->pluck('id', 'name')->toArray();

            // 1. Especialidades
            $specialtyNames = [
                'Cardiología', 'Dermatología', 'Pediatría', 'Neurología',
                'Traumatología', 'Psiquiatría', 'Medicina General', 'Ginecología'
            ];

            $specialtyIds = [];
            foreach ($specialtyNames as $name) {
                $existing = $db->table('specialties')->where('name', $name)->first();
                if ($existing) {
                    $specialtyIds[$name] = $existing->id;
                } else {
                    $id = Str::uuid()->toString();
                    $db->table('specialties')->insert([
                        'id'          => $id,
                        'name'        => $name,
                        'description' => "Especialidad de {$name}",
                        'is_active'   => true,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ]);
                    $specialtyIds[$name] = $id;
                }
            }

            // 2. Doctores
            $doctors = [
                [
                    'name' => 'María', 'last_name' => 'García',
                    'email' => 'maria.garcia@salvia.test',
                    'timezone' => 'America/Argentina/Buenos_Aires',
                    'specialties' => ['Cardiología'],
                    'franja' => '[08:00:00,17:00:00)',
                    'fee' => 75000, 'exp' => 15, 'uni' => 'Universidad de Buenos Aires',
                ],
                [
                    'name' => 'Alejandro', 'last_name' => 'Ruiz',
                    'email' => 'alejandro.ruiz@salvia.test',
                    'timezone' => 'America/Tegucigalpa',
                    'specialties' => ['Dermatología'],
                    'franja' => '[10:00:00,18:00:00)',
                    'fee' => 60000, 'exp' => 8, 'uni' => 'Universidad Nacional Autónoma de Honduras',
                ],
                [
                    'name' => 'Lucía', 'last_name' => 'Fernández',
                    'email' => 'lucia.fernandez@salvia.test',
                    'timezone' => 'America/Mexico_City',
                    'specialties' => ['Pediatría'],
                    'franja' => '[08:00:00,17:00:00)',
                    'fee' => 55000, 'exp' => 12, 'uni' => 'UNAM',
                ],
                [
                    // Dr. Mendoza ya existe — solo agregar schedules faltantes
                    'id'         => 'e894791a-215d-41a2-8aa8-60e702c37229',
                    'profile_id' => 'f17a288d-de23-4656-8615-67ba91114588',
                    'name' => 'Carlos', 'last_name' => 'Mendoza',
                    'email' => 'carlos.mendoza@salvia.test',
                    'timezone' => 'America/Bogota',
                    'specialties' => ['Neurología'],
                    'franja' => '[08:00:00,17:00:00)',
                    'fee' => 80000, 'exp' => 20, 'uni' => 'Universidad Nacional de Colombia',
                ],
                [
                    'name' => 'Ana', 'last_name' => 'Torres',
                    'email' => 'ana.torres@salvia.test',
                    'timezone' => 'America/Santo_Domingo',
                    'specialties' => ['Medicina General', 'Psiquiatría'],
                    'franja' => '[07:00:00,15:00:00)',
                    'fee' => 45000, 'exp' => 6, 'uni' => 'Universidad Autónoma de Santo Domingo',
                ],
            ];

            foreach ($doctors as $doc) {
                // User
                $userId = $doc['id'] ?? null;
                $user = $userId
                    ? $db->table('users')->where('id', $userId)->first()
                    : $db->table('users')->where('email', $doc['email'])->first();

                if (!$user && !$userId) {
                    $userId = Str::uuid()->toString();
                }
                $userId = $userId ?? $user->id;

                if (!$user) {
                    $db->table('users')->insert([
                        'id'         => $userId,
                        'name'       => $doc['name'],
                        'last_name'  => $doc['last_name'],
                        'email'      => $doc['email'],
                        'password'   => $password,
                        'timezone'   => $doc['timezone'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    // Assign doctor role
                    if (isset($roles['doctor'])) {
                        $db->table('user_roles')->insertOrIgnore([
                            'user_id' => $userId,
                            'role_id' => $roles['doctor'],
                        ]);
                    }
                }

                // Profile
                $profileId = $doc['profile_id'] ?? null;
                $profile = $profileId
                    ? $db->table('doctor_profiles')->where('id', $profileId)->first()
                    : $db->table('doctor_profiles')->where('user_id', $userId)->first();

                if (!$profile && !$profileId) {
                    $profileId = Str::uuid()->toString();
                }
                $profileId = $profileId ?? $profile->id;

                if (!$profile) {
                    $db->table('doctor_profiles')->insert([
                        'id'               => $profileId,
                        'user_id'          => $userId,
                        'status'           => 'approved',
                        'license_number'   => 'MED-' . strtoupper(substr(md5($doc['email']), 0, 8)),
                        'consultation_fee' => $doc['fee'],
                        'description'      => "Especialista con amplia experiencia en " . implode(' y ', $doc['specialties']) . ".",
                        'years_experience' => $doc['exp'],
                        'university'       => $doc['uni'],
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ]);
                }

                // Specialties
                foreach ($doc['specialties'] as $specName) {
                    $specId = $specialtyIds[$specName];
                    $exists = $db->table('doctor_specialties')
                        ->where('doctor_profile_id', $profileId)
                        ->where('specialty_id', $specId)
                        ->exists();
                    if (!$exists) {
                        $db->table('doctor_specialties')->insert([
                            'doctor_profile_id' => $profileId,
                            'specialty_id'      => $specId,
                        ]);
                    }
                }

                // Schedules Lun-Vie (day_of_week 1-5)
                for ($day = 1; $day <= 5; $day++) {
                    $hasSchedule = $db->table('schedules')
                        ->where('doctor_profile_id', $profileId)
                        ->where('day_of_week', $day)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (!$hasSchedule) {
                        $db->table('schedules')->insert([
                            'id'                => Str::uuid()->toString(),
                            'doctor_profile_id' => $profileId,
                            'day_of_week'       => $day,
                            'franja'            => $doc['franja'],
                            'slot_duration'     => 30,
                            'created_at'        => $now,
                            'updated_at'        => $now,
                        ]);
                    }
                }
            }

            // 3. Pacientes de prueba
            $patients = [
                ['name' => 'Juan',  'last_name' => 'Pérez', 'email' => 'patient@salvia.test'],
                ['name' => 'María', 'last_name' => 'López', 'email' => 'maria@salvia.test'],
            ];

            foreach ($patients as $pat) {
                $exists = $db->table('users')->where('email', $pat['email'])->exists();
                if (!$exists) {
                    $db->table('users')->insert([
                        'id'         => Str::uuid()->toString(),
                        'name'       => $pat['name'],
                        'last_name'  => $pat['last_name'],
                        'email'      => $pat['email'],
                        'password'   => $password,
                        'timezone'   => 'America/Santo_Domingo',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    // Assign patient role
                    $patUser = $db->table('users')->where('email', $pat['email'])->first();
                    if ($patUser && isset($roles['patient'])) {
                        $db->table('user_roles')->insertOrIgnore([
                            'user_id' => $patUser->id,
                            'role_id' => $roles['patient'],
                        ]);
                    }
                }
            }
        });
    }
}
