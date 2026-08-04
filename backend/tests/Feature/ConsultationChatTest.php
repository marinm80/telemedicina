<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Events\NewConsultationMessage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ConsultationChatTest extends TestCase
{
    private User $patient;
    private User $doctor;
    private User $agent;
    private User $otherPatient;

    protected function setUp(): void
    {
        parent::setUp();

        $mc = DB::connection('pgsql_migration');
        $patientRole = Role::where('name', 'patient')->first();
        $doctorRole  = Role::where('name', 'doctor')->first();
        $agentRole   = Role::where('name', 'agent')->first();

        $this->patient = User::factory()->create(['timezone' => 'UTC']);
        $mc->table('user_roles')->insert(['user_id' => $this->patient->id, 'role_id' => $patientRole->id]);

        $this->otherPatient = User::factory()->create(['timezone' => 'UTC']);
        $mc->table('user_roles')->insert(['user_id' => $this->otherPatient->id, 'role_id' => $patientRole->id]);

        $this->doctor = User::factory()->create(['timezone' => 'UTC']);
        $mc->table('user_roles')->insert(['user_id' => $this->doctor->id, 'role_id' => $doctorRole->id]);

        $this->agent = User::factory()->create(['timezone' => 'UTC']);
        $mc->table('user_roles')->insert(['user_id' => $this->agent->id, 'role_id' => $agentRole->id]);
    }

    public function test_envio_de_mensaje_en_chat_de_consulta_activa_exito(): void
    {
        Event::fake([NewConsultationMessage::class]);

        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();
        $consultationId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-25 10:00:00+00, 2026-08-25 10:30:00+00)',
            'status'     => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mc->table('consultations')->insert([
            'id'             => $consultationId,
            'appointment_id' => $appointmentId,
            'started_at'     => now(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->actingAs($this->patient);

        $response = $this->postJson("/api/consultations/{$consultationId}/messages", [
            'content' => 'Hola doctor, estoy listo para la consulta.',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'consultation_id' => $consultationId,
                'sender_id'       => $this->patient->id,
                'content'         => 'Hola doctor, estoy listo para la consulta.',
            ]);

        $this->assertDatabaseHas('consultation_messages', [
            'consultation_id' => $consultationId,
            'sender_id'       => $this->patient->id,
            'content'         => 'Hola doctor, estoy listo para la consulta.',
        ]);

        Event::assertDispatched(NewConsultationMessage::class, function (NewConsultationMessage $event) use ($consultationId) {
            return $event->consultationId === $consultationId && $event->content === 'Hola doctor, estoy listo para la consulta.';
        });
    }

    public function test_agente_intenta_enviar_un_mensaje_retorna_403(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();
        $consultationId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-25 11:00:00+00, 2026-08-25 11:30:00+00)',
            'status'     => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mc->table('consultations')->insert([
            'id'             => $consultationId,
            'appointment_id' => $appointmentId,
            'started_at'     => now(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->actingAs($this->agent);

        $response = $this->postJson("/api/consultations/{$consultationId}/messages", [
            'content' => 'Intento de unirme a la consulta.',
        ]);

        $response->assertStatus(403)
            ->assertJson(['error_code' => 'AGENT_ACCESS_FORBIDDEN']);
    }

    public function test_agente_o_tercero_intenta_leer_mensajes_retorna_403_o_404(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();
        $consultationId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-25 12:00:00+00, 2026-08-25 12:30:00+00)',
            'status'     => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mc->table('consultations')->insert([
            'id'             => $consultationId,
            'appointment_id' => $appointmentId,
            'started_at'     => now(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Agente intenta leer
        $this->actingAs($this->agent);
        $resAgent = $this->getJson("/api/consultations/{$consultationId}/messages");
        $resAgent->assertStatus(403);

        // Paciente ajeno intenta leer
        $this->actingAs($this->otherPatient);
        $resOther = $this->getJson("/api/consultations/{$consultationId}/messages");
        $resOther->assertStatus(404);
    }

    public function test_intento_enviar_mensaje_en_cita_cancelada_retorna_403(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();
        $consultationId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-25 14:00:00+00, 2026-08-25 14:30:00+00)',
            'status'     => 'cancelled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mc->table('consultations')->insert([
            'id'             => $consultationId,
            'appointment_id' => $appointmentId,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->actingAs($this->patient);

        $response = $this->postJson("/api/consultations/{$consultationId}/messages", [
            'content' => 'Mensaje en cita cancelada',
        ]);

        $response->assertStatus(403)
            ->assertJson(['error_code' => 'INVALID_APPOINTMENT_STATUS']);
    }
}
