<?php
declare(strict_types=1);

/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 */

use App\Http\Controllers\Appointments\AgendaController;
use App\Http\Controllers\Appointments\AppointmentController;
use App\Http\Controllers\Appointments\BookingController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Clinical\ConsultationRoomController;
use App\Http\Controllers\Clinical\DirectoryController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Landing pública y verificación de notas
Route::get('/', fn() => Inertia::render('Landing'));
Route::get('/verify/note/{hash}', [\App\Http\Controllers\Api\VerificationController::class, 'verifyNote']);
Route::get('/directory', [DirectoryController::class, 'index'])->name('directory');

// Autenticación (visitantes sin sesión)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
});

// Área protegida (requiere inicio de sesión)
Route::middleware('auth')->group(function () {
    // Dashboard principal con despacho por rol (Opción B)
    Route::get('/admin', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Citas del usuario
    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');

    // Agendamiento de Citas (Pacientes, Agentes, Admins)
    Route::middleware('role:patient,agent,admin')->group(function () {
        Route::get('/booking/{doctorProfileId}', [BookingController::class, 'create'])->name('booking.create');
    });

    // Gestión de Agenda del Médico (Médicos, Admins)
    Route::middleware('role:doctor,admin')->group(function () {
        Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda.index');
    });

    // Sala de Telemedicina en Vivo (Médicos, Pacientes, Admins)
    Route::middleware('role:doctor,patient,admin')->group(function () {
        Route::get('/consultation/{appointmentId}', [ConsultationRoomController::class, 'show'])->name('consultation.show');
    });

    // ─── Admin Panel Unificado ───
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/panel', fn() => Inertia::render('Admin/AdminPanel'))->name('admin.panel');
    });

    // Rutas legacy (redirigen al panel unificado)
    Route::get('/admin/verificaciones', fn() => redirect('/admin/panel'))->name('admin.verificaciones');
    Route::get('/admin/medicos', fn() => redirect('/admin/panel'))->name('admin.medicos');
    Route::get('/admin/ajustes', fn() => redirect('/admin/panel'))->name('admin.ajustes');

    // ─── Vista Paciente ───
    Route::get('/paciente/directorio', fn() => Inertia::render('Patient/DoctorDirectory'))->name('paciente.directorio');

    // ─── Vista Médico ───
    Route::middleware('role:doctor,admin')->group(function () {
        Route::get('/doctor/horarios', fn() => Inertia::render('Doctor/MisHorarios'))->name('doctor.horarios');
        
        Route::get('/doctor/consulta/{consultationId}', function (string $consultationId) {
            $db = \Illuminate\Support\Facades\DB::connection('pgsql_admin');
            $consultation = $db->table('consultations as c')
                ->join('appointments as a', 'a.id', '=', 'c.appointment_id')
                ->join('users as u_pat', 'u_pat.id', '=', 'a.patient_id')
                ->join('users as u_doc', 'u_doc.id', '=', 'a.doctor_id')
                ->where('c.id', $consultationId)
                ->select(
                    'c.id', 'c.appointment_id', 'c.started_at', 'c.ended_at',
                    'u_pat.id as patient_id', 'u_pat.name as patient_name', 'u_pat.last_name as patient_last_name', 'u_pat.email as patient_email',
                    'u_doc.id as doctor_id', 'u_doc.name as doctor_name', 'u_doc.last_name as doctor_last_name',
                    \Illuminate\Support\Facades\DB::raw("lower(a.franja) as franja_start"),
                    \Illuminate\Support\Facades\DB::raw("upper(a.franja) as franja_end"),
                    'a.status as appointment_status'
                )
                ->first();

            if (!$consultation) abort(404);

            return \Inertia\Inertia::render('Doctor/ConsultationView', [
                'consultation' => [
                    'id' => $consultation->id,
                    'appointment_id' => $consultation->appointment_id,
                    'started_at' => $consultation->started_at,
                    'ended_at' => $consultation->ended_at,
                    'patient' => [
                        'id' => $consultation->patient_id,
                        'name' => $consultation->patient_name,
                        'last_name' => $consultation->patient_last_name,
                        'email' => $consultation->patient_email,
                    ],
                    'doctor' => [
                        'id' => $consultation->doctor_id,
                        'name' => $consultation->doctor_name,
                        'last_name' => $consultation->doctor_last_name,
                    ],
                    'appointment' => [
                        'franja_start' => $consultation->franja_start,
                        'franja_end' => $consultation->franja_end,
                        'status' => $consultation->appointment_status,
                    ],
                ],
            ]);
        })->name('doctor.consulta');
    });

    // ─── Recetas ───
    Route::get('/admin/recetas', fn() => Inertia::render('ComingSoon', [
        'title' => 'Recetas Médicas',
        'description' => 'Emite y gestiona prescripciones de tus pacientes.',
    ]))->name('admin.recetas');

    // ─── Secciones placeholder ───
    Route::get('/admin/pacientes', fn() => Inertia::render('ComingSoon', [
        'title' => 'Gestión de Pacientes',
        'description' => 'Consulta perfiles de pacientes y su historial de citas.',
    ]))->name('admin.pacientes');

    Route::get('/admin/notas', fn() => Inertia::render('ComingSoon', [
        'title' => 'Notas Clínicas',
        'description' => 'Gestiona tus notas SOAP, borradores y enmiendas.',
    ]))->name('admin.notas');

    Route::get('/admin/perfil', fn() => Inertia::render('ComingSoon', [
        'title' => 'Mi Perfil',
        'description' => 'Edita tu información profesional y configuración de cuenta.',
    ]))->name('admin.perfil');

    Route::get('/admin/citas', fn() => redirect('/appointments'))->name('admin.citas');

    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});
