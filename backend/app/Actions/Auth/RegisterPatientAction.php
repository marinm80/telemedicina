<?php
declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class RegisterPatientAction
{
    /**
     * Registrar un nuevo paciente en la plataforma.
     *
     * @param  array{name: string, last_name: string, email: string, password: string, timezone?: string|null}  $data
     * @return \App\Models\User
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(array $data): User
    {
        try {
            return DB::transaction(function () use ($data): User {
                $mc = DB::connection('pgsql_migration');

                $patientRole = Role::where('name', 'patient')->firstOrFail();
                $userId = Str::uuid()->toString();

                $now = now();
                $email = strtolower(trim($data['email']));

                // Creación de usuario y asignación de rol bajo conexión administrativa sin bloqueo RLS previo a login
                $mc->table('users')->insert([
                    'id'         => $userId,
                    'name'       => trim($data['name']),
                    'last_name'  => trim($data['last_name']),
                    'email'      => $email,
                    'password'   => Hash::make($data['password']),
                    'timezone'   => $data['timezone'] ?? 'UTC',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $mc->table('user_roles')->insert([
                    'user_id' => $userId,
                    'role_id' => $patientRole->id,
                ]);

                return User::on('pgsql_migration')->findOrFail($userId);
            });
        } catch (QueryException $e) {
            // Manejar violación de clave única en email
            if ($e->getCode() === '23505' || str_contains($e->getMessage(), 'users_email_key')) {
                throw ValidationException::withMessages([
                    'email' => 'El correo electrónico ya se encuentra registrado.',
                ]);
            }
            throw $e;
        }
    }
}
