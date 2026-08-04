<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ConsultationNoteTest extends TestCase
{
    private User $patient;
    private User $doctor;
    private User $otherDoctor;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $mc = DB::connection('pgsql_migration');
        $patientRole = Role::where('name', 'patient')->first();
        $doctorRole  = Role::where('name', 'doctor')->first();
        $agentRole   = Role::where('name', 'agent')->first();

        $this->patient = User::factory()->create(['timezone' => 'UTC']);
        $mc->table('user_roles')->insert(['user_id' => $this->patient->id, 'role_id' => $patientRole->id]);

        $this->doctor = User::factory()->create(['timezone' => 'UTC']);
        $mc->table('user_roles')->insert(['user_id' => $this->doctor->id, 'role_id' => $doctorRole->id]);

        $this->otherDoctor = User::factory()->create(['timezone' => 'UTC']);
        $mc->table('user_roles')->insert(['user_id' => $this->otherDoctor->id, 'role_id' => $doctorRole->id]);

        $this->agent = User::factory()->create(['timezone' => 'UTC']);
        $mc->table('user_roles')->insert(['user_id' => $this->agent->id, 'role_id' => $agentRole->id]);
    }

    public function test_medico_guarda_borrador_de_nota_soap_exito(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();
        $consultationId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-30 10:00:00+00, 2026-08-30 10:30:00+00)',
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

        $this->actingAs($this->doctor);

        $payload = [
            'symptoms'  => 'Dolor torácico opresivo',
            'objective' => 'Presión arterial 120/80',
            'analysis'  => 'Posible angina estable',
            'plan'      => 'Electrocardiograma y reposo',
        ];

        $response = $this->postJson("/api/consultations/{$consultationId}/notes", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'consultation_id' => $consultationId,
                'symptoms'        => 'Dolor torácico opresivo',
                'status'          => 'draft',
            ]);

        $this->assertDatabaseHas('consultation_notes', [
            'consultation_id' => $consultationId,
            'status'          => 'draft',
        ]);
    }

    public function test_paciente_intenta_ver_borrador_retorna_404(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();
        $consultationId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-30 11:00:00+00, 2026-08-30 11:30:00+00)',
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

        $mc->table('consultation_notes')->insert([
            'id'              => Str::uuid()->toString(),
            'consultation_id' => $consultationId,
            'symptoms'        => 'Borrador confidencial',
            'objective'       => 'Examen físico preliminar',
            'analysis'        => 'En evaluación',
            'plan'            => 'Pendiente',
            'status'          => 'draft',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->actingAs($this->patient);

        $response = $this->getJson("/api/consultations/{$consultationId}/notes");

        $response->assertStatus(404);
    }

    public function test_medico_firma_nota_clinica_calcula_hash_sha256(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();
        $consultationId = Str::uuid()->toString();
        $noteId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-30 12:00:00+00, 2026-08-30 12:30:00+00)',
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

        $mc->table('consultation_notes')->insert([
            'id'              => $noteId,
            'consultation_id' => $consultationId,
            'symptoms'        => 'Dolor precordial',
            'objective'       => 'PA: 130/85 mmHg',
            'analysis'        => 'Síndrome coronario descartado',
            'plan'            => 'Alta con seguimiento',
            'status'          => 'draft',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->actingAs($this->doctor);

        $response = $this->postJson("/api/consultations/{$consultationId}/notes/sign");

        $response->assertStatus(200)
            ->assertJson([
                'id'        => $noteId,
                'status'    => 'signed',
                'signed_by' => $this->doctor->id,
            ]);

        $this->assertNotNull($response->json('content_hash'));
        $this->assertEquals(64, strlen((string) $response->json('content_hash')));

        $this->assertDatabaseHas('consultation_notes', [
            'id'        => $noteId,
            'status'    => 'signed',
            'signed_by' => $this->doctor->id,
        ]);
    }

    public function test_bloqueo_de_modificacion_directa_en_nota_firmada_403(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();
        $consultationId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-30 14:00:00+00, 2026-08-30 14:30:00+00)',
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

        $hash64 = hash('sha256', 'fake_test_content');

        $mc->table('consultation_notes')->insert([
            'id'              => Str::uuid()->toString(),
            'consultation_id' => $consultationId,
            'symptoms'        => 'Síntomas firmados',
            'objective'       => 'Objetivo firmado',
            'analysis'        => 'Análisis firmado',
            'plan'            => 'Plan firmado',
            'status'          => 'signed',
            'content_hash'    => $hash64,
            'signed_by'       => $this->doctor->id,
            'signed_at'       => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->actingAs($this->doctor);

        $response = $this->putJson("/api/consultations/{$consultationId}/notes", [
            'symptoms'  => 'Modificación prohibida',
            'objective' => 'Presión 120/80',
            'analysis'  => 'Modificación',
            'plan'      => 'Modificación',
        ]);

        $response->assertStatus(403)
            ->assertJson(['error_code' => 'NOTE_ALREADY_SIGNED']);
    }

    public function test_medico_anade_enmienda_a_nota_firmada_exito(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();
        $consultationId = Str::uuid()->toString();
        $noteId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-30 15:00:00+00, 2026-08-30 15:30:00+00)',
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

        $hash64 = hash('sha256', 'original_content');

        $mc->table('consultation_notes')->insert([
            'id'              => $noteId,
            'consultation_id' => $consultationId,
            'symptoms'        => 'Síntomas originales',
            'objective'       => 'Objetivo original',
            'analysis'        => 'Análisis original',
            'plan'            => 'Plan original',
            'status'          => 'signed',
            'content_hash'    => $hash64,
            'signed_by'       => $this->doctor->id,
            'signed_at'       => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->actingAs($this->doctor);

        $response = $this->postJson("/api/consultations/{$consultationId}/notes/amendments", [
            'reason'  => 'Olvidé detallar dosis de aspirina',
            'content' => 'Se indica aspirina 100mg diarios por 7 días',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'consultation_note_id' => $noteId,
                'author_id'            => $this->doctor->id,
                'reason'               => 'Olvidé detallar dosis de aspirina',
                'content'              => 'Se indica aspirina 100mg diarios por 7 días',
            ]);

        $this->assertDatabaseHas('note_amendments', [
            'consultation_note_id' => $noteId,
            'reason'               => 'Olvidé detallar dosis de aspirina',
        ]);

        // La nota original permanece 100% idéntica
        $this->assertDatabaseHas('consultation_notes', [
            'id'       => $noteId,
            'symptoms' => 'Síntomas originales',
            'status'   => 'signed',
        ]);
    }

    public function test_usuario_no_autorizado_intenta_enmendar_nota_403(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();
        $consultationId = Str::uuid()->toString();
        $noteId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-30 16:00:00+00, 2026-08-30 16:30:00+00)',
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

        $mc->table('consultation_notes')->insert([
            'id'              => $noteId,
            'consultation_id' => $consultationId,
            'symptoms'        => 'Síntomas',
            'objective'       => 'Objetivo',
            'analysis'        => 'Análisis',
            'plan'            => 'Plan',
            'status'          => 'signed',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // Otro médico no asignado intenta enmendar
        $this->actingAs($this->otherDoctor);

        $response = $this->postJson("/api/consultations/{$consultationId}/notes/amendments", [
            'reason'  => 'Intento no autorizado',
            'content' => 'Enmienda ilícita',
        ]);

        $this->assertTrue(
            in_array($response->status(), [403, 404], true),
            'Esperado 403 Forbidden o 404 Not Found por RLS.'
        );
    }

    public function test_paciente_consulta_nota_firmada_con_enmiendas(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();
        $consultationId = Str::uuid()->toString();
        $noteId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-08-30 17:00:00+00, 2026-08-30 17:30:00+00)',
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

        $hash64 = hash('sha256', 'hash_sha256_verificable');

        $mc->table('consultation_notes')->insert([
            'id'              => $noteId,
            'consultation_id' => $consultationId,
            'symptoms'        => 'Síntomas firmados',
            'objective'       => 'Objetivo',
            'analysis'        => 'Análisis',
            'plan'            => 'Plan',
            'status'          => 'signed',
            'content_hash'    => $hash64,
            'signed_by'       => $this->doctor->id,
            'signed_at'       => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $mc->table('note_amendments')->insert([
            'id'                   => Str::uuid()->toString(),
            'consultation_note_id' => $noteId,
            'author_id'            => $this->doctor->id,
            'reason'               => 'Aclaración de laboratorio',
            'content'              => 'Resultados confirman diagnóstico',
            'created_at'           => now(),
        ]);

        $this->actingAs($this->patient);

        $response = $this->getJson("/api/consultations/{$consultationId}/notes");

        $response->assertStatus(200)
            ->assertJson([
                'id'           => $noteId,
                'status'       => 'signed',
                'content_hash' => $hash64,
            ]);

        $this->assertCount(1, $response->json('amendments'));
        $this->assertEquals('Aclaración de laboratorio', $response->json('amendments.0.reason'));
    }
}
