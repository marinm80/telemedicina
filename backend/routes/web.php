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

    // ─── Secciones del Panel (Coming Soon placeholders) ───
    // Admin
    Route::get('/admin/verificaciones', fn() => Inertia::render('ComingSoon', [
        'title' => 'Verificación de Médicos',
        'description' => 'Aprueba o rechaza solicitudes de nuevos médicos en la plataforma.',
    ]))->name('admin.verificaciones');

    Route::get('/admin/medicos', fn() => Inertia::render('Admin/ScheduleManager'))->name('admin.medicos');

    Route::get('/admin/pacientes', fn() => Inertia::render('ComingSoon', [
        'title' => 'Gestión de Pacientes',
        'description' => 'Consulta perfiles de pacientes y su historial de citas.',
    ]))->name('admin.pacientes');

    Route::get('/admin/facturacion', fn() => Inertia::render('ComingSoon', [
        'title' => 'Facturación',
        'description' => 'Revisa ingresos, pagos y comisiones de la plataforma.',
    ]))->name('admin.facturacion');

    Route::get('/admin/ajustes', fn() => Inertia::render('ComingSoon', [
        'title' => 'Ajustes del Sistema',
        'description' => 'Configura parámetros generales de la plataforma.',
    ]))->name('admin.ajustes');

    // Doctor
    Route::get('/admin/notas', fn() => Inertia::render('ComingSoon', [
        'title' => 'Notas Clínicas',
        'description' => 'Gestiona tus notas SOAP, borradores y enmiendas.',
    ]))->name('admin.notas');

    Route::get('/admin/recetas', fn() => Inertia::render('ComingSoon', [
        'title' => 'Recetas Médicas',
        'description' => 'Emite y gestiona prescripciones de tus pacientes.',
    ]))->name('admin.recetas');

    Route::get('/admin/ingresos', fn() => Inertia::render('ComingSoon', [
        'title' => 'Mis Ingresos',
        'description' => 'Consulta tus ganancias, comisiones y estado de pagos.',
    ]))->name('admin.ingresos');

    Route::get('/admin/perfil', fn() => Inertia::render('ComingSoon', [
        'title' => 'Mi Perfil',
        'description' => 'Edita tu información profesional y configuración de cuenta.',
    ]))->name('admin.perfil');

    // Patient
    Route::get('/admin/resultados', fn() => Inertia::render('ComingSoon', [
        'title' => 'Mis Resultados',
        'description' => 'Consulta resultados de laboratorio y estudios médicos.',
    ]))->name('admin.resultados');

    Route::get('/admin/pagos', fn() => Inertia::render('ComingSoon', [
        'title' => 'Mis Pagos',
        'description' => 'Revisa tu historial de pagos y métodos de pago.',
    ]))->name('admin.pagos');

    // Agent
    Route::get('/admin/citas', fn() => redirect('/appointments'))->name('admin.citas');

    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});
