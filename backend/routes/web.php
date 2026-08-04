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

    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});
