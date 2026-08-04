<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PreConsultationTest extends TestCase
{
    private User $patient;
    private User $doctor;
    private User $otherPatient;

    protected function setUp(): void
    {
        parent::setUp();

        $mc = DB::connection('pgsql_migration');
        $patientRole = Role::where('name', 'patient')->first();
        $doctorRole  = Role::where('name', 'doctor')->first();

        $this->patient = User::factory()->create(['timezone' => 'UTC']);
        $mc->table('user_roles')->insert(['user_id' => $this->patient->id, 'role_id' => $patientRole->id]);

        $this->otherPatient = User::factory()->create(['timezone' => 'UTC']);
        $mc->table('user_roles')->insert(['user_id' => $this->otherPatient->id, 'role_id' => $patientRole->id]);

        $this->doctor = User::factory()->create(['timezone' => 'UTC']);
        $mc->table('user_roles')->insert(['user_id' => $this->doctor->id, 'role_id' => $doctorRole->id]);
    }

    public function test_paciente_completa_cuestionario_preconsulta_exito(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-20 10:00:00+00, 2026-08-20 10:30:00+00)',
            'status'     => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->patient);

        $payload = [
            'motivo'    => 'Dolor en el pecho persistente',
            'sintomas'  => 'Disnea leve, palpitaciones',
            'form_data' => [
                'sintomas_actuales' => [
                    'nivel_dolor' => 6,
                    'tipo_dolor'  => 'constante',
                    'evolucion'   => 'empeoran',
                ],
                'antecedentes_medicos' => [
                    'enfermedades_cronicas' => ['Hipertensión'],
                    'alergias'              => ['Penicilina'],
                ],
                'estilo_vida' => [
                    'fuma'     => 'no',
                    'alcohol'  => 'ocasional',
                    'ejercicio' => '2 veces/semana',
                ],
                'signos_alerta' => [
                    'dificultad_respiratoria' => true,
                    'dolor_pecho'            => true,
                ],
            ],
        ];

        $response = $this->postJson("/api/appointments/{$appointmentId}/pre-consultation", $payload);

        $response->assertStatus(201)
            ->assertJson([
                'appointment_id' => $appointmentId,
                'motivo'         => 'Dolor en el pecho persistente',
                'sintomas'       => 'Disnea leve, palpitaciones',
            ]);

        $this->assertDatabaseHas('pre_consultation_forms', [
            'appointment_id' => $appointmentId,
            'motivo'         => 'Dolor en el pecho persistente',
        ]);
    }

    public function test_paciente_o_medico_consulta_cuestionario_exito(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-20 11:00:00+00, 2026-08-20 11:30:00+00)',
            'status'     => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mc->table('pre_consultation_forms')->insert([
            'id'             => Str::uuid()->toString(),
            'appointment_id' => $appointmentId,
            'motivo'         => 'Cefalea intensa',
            'sintomas'       => 'Visión borrosa y mareos',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Paciente consulta
        $this->actingAs($this->patient);
        $resPatient = $this->getJson("/api/appointments/{$appointmentId}/pre-consultation");
        $resPatient->assertStatus(200)
            ->assertJson([
                'appointment_id' => $appointmentId,
                'motivo'         => 'Cefalea intensa',
            ]);

        // Médico tratante consulta
        $this->actingAs($this->doctor);
        $resDoctor = $this->getJson("/api/appointments/{$appointmentId}/pre-consultation");
        $resDoctor->assertStatus(200)
            ->assertJson([
                'appointment_id' => $appointmentId,
                'motivo'         => 'Cefalea intensa',
            ]);
    }

    public function test_error_al_enviar_cuestionario_para_cita_cancelada_422(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-20 12:00:00+00, 2026-08-20 12:30:00+00)',
            'status'     => 'cancelled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->patient);

        $response = $this->postJson("/api/appointments/{$appointmentId}/pre-consultation", [
            'motivo'   => 'Consulta de control',
            'sintomas' => 'Ninguno',
        ]);

        $response->assertStatus(422)
            ->assertJson(['error_code' => 'INVALID_APPOINTMENT_STATUS']);
    }

    public function test_intento_duplicar_cuestionario_retorna_409(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-20 14:00:00+00, 2026-08-20 14:30:00+00)',
            'status'     => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->patient);

        $payload = [
            'motivo'   => 'Evaluación médica',
            'sintomas' => 'Fiebre leve',
        ];

        $this->postJson("/api/appointments/{$appointmentId}/pre-consultation", $payload)->assertStatus(201);
        $resDup = $this->postJson("/api/appointments/{$appointmentId}/pre-consultation", $payload);

        $resDup->assertStatus(409)
            ->assertJson(['error_code' => 'PRE_CONSULTATION_ALREADY_EXISTS']);
    }

    public function test_tercero_no_autorizado_recibe_404_por_rls(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-20 15:00:00+00, 2026-08-20 15:30:00+00)',
            'status'     => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mc->table('pre_consultation_forms')->insert([
            'id'             => Str::uuid()->toString(),
            'appointment_id' => $appointmentId,
            'motivo'         => 'Datos privados',
            'sintomas'       => 'Sensibles',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Otro paciente ajeno intenta consultar
        $this->actingAs($this->otherPatient);
        $response = $this->getJson("/api/appointments/{$appointmentId}/pre-consultation");

        $response->assertStatus(404);
    }
}
