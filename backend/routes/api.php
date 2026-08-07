<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 */

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AppointmentController;
use App\Http\Middleware\SetPostgresSessionContext;

Route::middleware(['web', SetPostgresSessionContext::class])->group(function () {
    // 0. Vitrina pública de médicos para la landing (sin sesión)
    Route::get('/public/doctors', [\App\Http\Controllers\Clinical\DirectoryController::class, 'featured']);

    // 1. Endpoint de disponibilidad
    Route::get('/doctors/{id}/availability', [AppointmentController::class, 'availability']);
    Route::get('/doctors/{id}/month-availability', [AppointmentController::class, 'monthAvailability']);

    // 2. Endpoint de reserva, cancelación y reprogramación de citas (RF-09, RF-25, RF-11)
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::post('/appointments/{id}/cancel', [AppointmentController::class, 'cancel']);
    Route::post('/appointments/{id}/reschedule-request', [AppointmentController::class, 'rescheduleRequest']);
    Route::put('/api/appointments/{id}/reschedule-approve', [AppointmentController::class, 'rescheduleApprove']);
    Route::put('/appointments/{id}/reschedule-approve', [AppointmentController::class, 'rescheduleApprove']);
    Route::put('/appointments/{id}/reschedule-reject', [AppointmentController::class, 'rescheduleReject']);

    // 4. Webhook de Stripe (RF-12)
    Route::post('/webhooks/stripe', [\App\Http\Controllers\Api\StripeWebhookController::class, 'handleWebhook']);

    // 5. Cuestionario Pre-consulta (RF-13)
    Route::post('/appointments/{id}/pre-consultation', [\App\Http\Controllers\Api\PreConsultationController::class, 'store']);
    Route::get('/appointments/{id}/pre-consultation', [\App\Http\Controllers\Api\PreConsultationController::class, 'show']);

    // 6. Consulta por Chat en Tiempo Real (RF-14)
    Route::post('/consultations/{id}/messages', [\App\Http\Controllers\Api\ConsultationChatController::class, 'store']);
    Route::get('/consultations/{id}/messages', [\App\Http\Controllers\Api\ConsultationChatController::class, 'index']);

    // 7. Notas SOAP, Firma Electrónica e Enmiendas (RF-15, RF-16, RF-17, RF-18, RF-19)
    Route::get('/consultations/{id}/notes', [\App\Http\Controllers\Api\ConsultationNoteController::class, 'show']);
    Route::get('/consultations/{id}/pdf', [\App\Http\Controllers\Api\ConsultationNoteController::class, 'downloadPdf']);
    Route::post('/consultations/{id}/notes', [\App\Http\Controllers\Api\ConsultationNoteController::class, 'storeDraft']);
    Route::put('/consultations/{id}/notes', [\App\Http\Controllers\Api\ConsultationNoteController::class, 'storeDraft']);
    Route::post('/consultations/{id}/notes/sign', [\App\Http\Controllers\Api\ConsultationNoteController::class, 'sign']);
    Route::post('/consultations/{id}/notes/amendments', [\App\Http\Controllers\Api\ConsultationNoteController::class, 'addAmendment']);
    Route::post('/consultations/{id}/acknowledge', [\App\Http\Controllers\Api\ConsultationNoteController::class, 'acknowledge']);
    Route::post('/consultations/{id}/notes/ack', [\App\Http\Controllers\Api\ConsultationNoteController::class, 'acknowledge']);

    // Consultation form management
    Route::post('/consultations/{id}/form', [\App\Http\Controllers\Api\ConsultationFormController::class, 'store']);
    Route::post('/consultations/{id}/archive', [\App\Http\Controllers\Api\ConsultationFormController::class, 'archive']);
    Route::get('/consultations/{id}/form', [\App\Http\Controllers\Api\ConsultationFormController::class, 'show']);

    // Prescriptions
    Route::get('/prescriptions', [\App\Http\Controllers\Api\PrescriptionController::class, 'index']);
    Route::post('/prescriptions', [\App\Http\Controllers\Api\PrescriptionController::class, 'store']);
    Route::put('/prescriptions/{id}', [\App\Http\Controllers\Api\PrescriptionController::class, 'update']);
    Route::delete('/prescriptions/{id}', [\App\Http\Controllers\Api\PrescriptionController::class, 'destroy']);

    // Referrals (RF-REFERIDOS)
    Route::get('/referrals', [\App\Http\Controllers\Api\ReferralController::class, 'index']);
    Route::post('/referrals', [\App\Http\Controllers\Api\ReferralController::class, 'store']);
    Route::put('/referrals/{id}', [\App\Http\Controllers\Api\ReferralController::class, 'update']);

    // Specialties catalog
    Route::get('/specialties', function () {
        $specialties = \Illuminate\Support\Facades\DB::connection('pgsql_admin')
            ->table('specialties')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->select('id', 'name')
            ->get();
        return response()->json(['data' => $specialties]);
    });

    // Start a consultation (creates record if not exists)
    Route::post('/consultations/{appointmentId}/start', function (\Illuminate\Http\Request $request, string $appointmentId) {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'No autenticado.'], 401);

        $db = \Illuminate\Support\Facades\DB::connection('pgsql_admin');
        $appointment = $db->table('appointments')->where('id', $appointmentId)->first();
        if (!$appointment) return response()->json(['message' => 'Cita no encontrada.'], 404);
        if ($appointment->doctor_id !== $user->id && $user->role !== 'admin') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        // Check if consultation exists
        $existing = $db->table('consultations')->where('appointment_id', $appointmentId)->first();
        if ($existing) {
            return response()->json(['consultation_id' => $existing->id], 200);
        }

        // Create consultation
        $id = \Illuminate\Support\Str::uuid()->toString();
        $db->table('consultations')->insert([
            'id' => $id,
            'appointment_id' => $appointmentId,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update appointment status to confirmed
        if ($appointment->status === 'pending') {
            $db->table('appointments')->where('id', $appointmentId)->update([
                'status' => 'confirmed',
                'updated_at' => now(),
            ]);
        }

        return response()->json(['consultation_id' => $id], 201);
    });

    // 8. Verificación Pública de Hash SHA-256 (RF-18)
    Route::get('/verify/note/{hash}', [\App\Http\Controllers\Api\VerificationController::class, 'verifyNote']);

    // 9. Asistente Conversacional (RF-23 / RF-24)
    Route::post('/assistant/public', [\App\Http\Controllers\Api\AssistantController::class, 'publicAssistant']);
    Route::post('/assistant/clinical', [\App\Http\Controllers\Api\AssistantController::class, 'clinicalAssistant']);
    
    Route::middleware('guest')->group(function () {
        Route::post('/login', [\App\Http\Controllers\Auth\AuthController::class, 'store']);
        Route::post('/register', [\App\Http\Controllers\Auth\RegisterController::class, 'storeApi']);
    });

    Route::post('/schedules', [\App\Http\Controllers\Api\ScheduleController::class, 'storeSchedule']);
    Route::delete('/schedules/{id}', [\App\Http\Controllers\Api\ScheduleController::class, 'destroySchedule']);
    Route::post('/schedule-blocks', [\App\Http\Controllers\Api\ScheduleController::class, 'storeBlock']);
    Route::delete('/schedule-blocks/{id}', [\App\Http\Controllers\Api\ScheduleController::class, 'destroyBlock']);

    // Admin schedule management
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/doctors', [\App\Http\Controllers\Api\AdminDoctorController::class, 'index']);
        Route::post('/doctors', [\App\Http\Controllers\Api\AdminDoctorController::class, 'store']);
        Route::patch('/doctors/{id}/status', [\App\Http\Controllers\Api\AdminDoctorController::class, 'updateStatus']);

        Route::get('/schedules', [\App\Http\Controllers\Api\AdminScheduleController::class, 'index']);
        Route::post('/schedules', [\App\Http\Controllers\Api\AdminScheduleController::class, 'store']);
        Route::delete('/schedules/{id}', [\App\Http\Controllers\Api\AdminScheduleController::class, 'destroy']);

        Route::get('/users', [\App\Http\Controllers\Api\AdminSettingsController::class, 'users']);
        Route::patch('/users/{id}/password', [\App\Http\Controllers\Api\AdminSettingsController::class, 'changePassword']);
        Route::patch('/users/{id}/role', [\App\Http\Controllers\Api\AdminSettingsController::class, 'updateUserRole']);
    });
    // Doctor self-service schedule management
    Route::middleware('role:doctor')->prefix('doctor')->group(function () {
        Route::get('/schedules', [\App\Http\Controllers\Api\DoctorScheduleController::class, 'index']);
        Route::post('/schedules', [\App\Http\Controllers\Api\DoctorScheduleController::class, 'store']);
        Route::delete('/schedules/{id}', [\App\Http\Controllers\Api\DoctorScheduleController::class, 'destroy']);
    });
});

