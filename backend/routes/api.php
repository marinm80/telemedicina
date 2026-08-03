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

Route::middleware([SetPostgresSessionContext::class])->group(function () {
    // 1. Endpoint de disponibilidad
    Route::get('/doctors/{id}/availability', [AppointmentController::class, 'availability']);

    // 2. Endpoint de reserva de citas
    Route::post('/appointments', [AppointmentController::class, 'store']);
});
