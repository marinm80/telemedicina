<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PatientAcknowledgmentTest extends TestCase
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

    public function test_paciente_firma_acuse_de_recibo_exito_200_y_actualiza_acknowledged_at(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();
        $consultationId = Str::uuid()->toString();
        $noteId = Str::uuid()->toString();
        $hash = hash('sha256', 'ack_test_hash');

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-09-02 10:00:00+00, 2026-09-02 10:30:00+00)',
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
            'content_hash'    => $hash,
            'signed_by'       => $this->doctor->id,
            'signed_at'       => now(),
            'pdf_status'      => 'pdf_ready',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->actingAs($this->patient);

        $response = $this->postJson("/api/consultations/{$consultationId}/acknowledge");

        $response->assertStatus(200)
            ->assertJson([
                'id'              => $noteId,
                'consultation_id' => $consultationId,
                'status'          => 'signed',
            ]);

        $this->assertNotNull($response->json('acknowledged_at'));

        $this->assertNotNull($mc->table('consultation_notes')->where('id', $noteId)->value('acknowledged_at'));
    }

    public function test_error_al_firmar_acuse_si_nota_esta_en_borrador_422(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();
        $consultationId = Str::uuid()->toString();
        $noteId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-09-02 11:00:00+00, 2026-09-02 11:30:00+00)',
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
            'symptoms'        => 'Borrador sin firmar',
            'objective'       => 'Objetivo',
            'analysis'        => 'Análisis',
            'plan'            => 'Plan',
            'status'          => 'draft',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->actingAs($this->patient);

        $response = $this->postJson("/api/consultations/{$consultationId}/acknowledge");

        $response->assertStatus(422)
            ->assertJson(['error_code' => 'INVALID_NOTE_STATUS']);
    }

    public function test_tercero_no_autorizado_no_puede_firmar_acuse_403_o_404(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();
        $consultationId = Str::uuid()->toString();
        $noteId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-09-02 12:00:00+00, 2026-09-02 12:30:00+00)',
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

        $this->actingAs($this->otherPatient);

        $response = $this->postJson("/api/consultations/{$consultationId}/acknowledge");

        $this->assertTrue(
            in_array($response->status(), [403, 404], true),
            'Esperado 403 Forbidden o 404 Not Found por RLS.'
        );
    }
}
