<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GenerateClinicalNotePdfJob;
use App\Models\Role;
use App\Models\User;
use App\Services\PdfGeneratorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ClinicalPdfTest extends TestCase
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

    public function test_firma_de_nota_dispara_generacion_de_pdf_en_horizon(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();
        $consultationId = Str::uuid()->toString();
        $noteId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-09-01 10:00:00+00, 2026-09-01 10:30:00+00)',
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
            'symptoms'        => 'Evaluación general',
            'objective'       => 'Constantes normales',
            'analysis'        => 'Paciente sano',
            'plan'            => 'Control anual',
            'status'          => 'draft',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->actingAs($this->doctor);

        // Firmar la nota clínica
        $response = $this->postJson("/api/consultations/{$consultationId}/notes/sign");
        $response->assertStatus(200);

        // Ejecutar el Job directamente
        $job = new GenerateClinicalNotePdfJob($noteId);
        $job->handle(new PdfGeneratorService());

        // Verificar que el estado del PDF se actualiza a pdf_ready (vía conexión de migración superusuario)
        $mc = DB::connection('pgsql_migration');
        $this->assertEquals('pdf_ready', $mc->table('consultation_notes')->where('id', $noteId)->value('pdf_status'));
    }

    public function test_verificacion_de_autenticidad_por_qr_publico(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();
        $consultationId = Str::uuid()->toString();
        $noteId = Str::uuid()->toString();
        $hash = hash('sha256', 'test_public_verification_hash');

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-09-01 11:00:00+00, 2026-09-01 11:30:00+00)',
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
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // Verificación pública sin autenticación
        $response = $this->getJson("/verify/note/{$hash}");

        $response->assertStatus(200)
            ->assertJson([
                'valid'        => true,
                'content_hash' => $hash,
                'signed_by'    => $this->doctor->id,
            ]);

        // Hash inexistente
        $resInvalid = $this->getJson("/verify/note/hash_inexistente_12345");
        $resInvalid->assertStatus(404)
            ->assertJson(['valid' => false]);
    }

    public function test_descarga_de_pdf_por_paciente_autorizado_exito(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();
        $consultationId = Str::uuid()->toString();
        $noteId = Str::uuid()->toString();
        $hash = hash('sha256', 'test_download_pdf_hash');

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-09-01 12:00:00+00, 2026-09-01 12:30:00+00)',
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

        $relativePath = 'private/pdfs/note_' . $noteId . '.pdf';
        $fullPath = storage_path('app/' . $relativePath);
        File::ensureDirectoryExists(dirname($fullPath));
        File::put($fullPath, '%PDF-1.4 Fake PDF Content for Test');

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
            'pdf_path'        => $relativePath,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->actingAs($this->patient);

        $response = $this->get("/api/consultations/{$consultationId}/pdf");

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    public function test_agente_o_tercero_no_puede_descargar_pdf_403_o_404(): void
    {
        $mc = DB::connection('pgsql_migration');
        $appointmentId = Str::uuid()->toString();
        $consultationId = Str::uuid()->toString();
        $noteId = Str::uuid()->toString();

        $mc->table('appointments')->insert([
            'id'         => $appointmentId,
            'patient_id' => $this->patient->id,
            'doctor_id'  => $this->doctor->id,
            'franja'     => '[2026-09-01 14:00:00+00, 2026-09-01 14:30:00+00)',
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
            'pdf_status'      => 'pdf_ready',
            'pdf_path'        => 'private/pdfs/note_test.pdf',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // Agente intenta descargar PDF -> 403
        $this->actingAs($this->agent);
        $resAgent = $this->getJson("/api/consultations/{$consultationId}/pdf");
        $resAgent->assertStatus(403);

        // Paciente ajeno intenta descargar PDF -> 404
        $this->actingAs($this->otherPatient);
        $resOther = $this->getJson("/api/consultations/{$consultationId}/pdf");
        $resOther->assertStatus(404);
    }
}
