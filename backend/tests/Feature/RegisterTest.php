<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

final class RegisterTest extends TestCase
{
    public function test_registro_de_paciente_exitoso_web(): void
    {
        $payload = [
            'name'                  => 'María',
            'last_name'             => 'González',
            'email'                 => 'maria.gonzalez@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
            'timezone'              => 'America/Santiago',
        ];

        $response = $this->post('/register', $payload);

        $response->assertStatus(302)->assertRedirect('/admin');
        $this->assertAuthenticated();

        $user = User::on('pgsql_migration')->where('email', 'maria.gonzalez@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('María', $user->name);
        $this->assertEquals('González', $user->last_name);
        $this->assertEquals('patient', $user->role);
    }

    public function test_registro_de_paciente_exitoso_api(): void
    {
        $payload = [
            'name'                  => 'Carlos',
            'last_name'             => 'Tapia',
            'email'                 => 'carlos.tapia@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
            'timezone'              => 'America/Santiago',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Cuenta registrada exitosamente.',
                'user'    => [
                    'email' => 'carlos.tapia@example.com',
                    'role'  => 'patient',
                ],
            ]);

        $this->assertAuthenticated();
    }

    public function test_registro_de_paciente_falla_si_email_esta_duplicado(): void
    {
        User::factory()->create(['email' => 'duplicado@example.com']);

        $payload = [
            'name'                  => 'Pedro',
            'last_name'             => 'Soto',
            'email'                 => 'duplicado@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->post('/register', $payload);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_registro_de_paciente_falla_si_contrasenas_no_coinciden(): void
    {
        $payload = [
            'name'                  => 'Ana',
            'last_name'             => 'Rojas',
            'email'                 => 'ana.rojas@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Diferente123!',
        ];

        $response = $this->post('/register', $payload);

        $response->assertSessionHasErrors(['password']);
        $this->assertGuest();
    }
}
