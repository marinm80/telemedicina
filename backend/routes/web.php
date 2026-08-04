<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 */

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Landing pública sin autenticación
Route::get('/', fn() => Inertia::render('Landing'));
Route::get('/verify/note/{hash}', [\App\Http\Controllers\Api\VerificationController::class, 'verifyNote']);

// Autenticación (visitantes sin sesión)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store']);
});

// Área protegida (requiere inicio de sesión)
Route::middleware('auth')->group(function () {
    Route::get('/admin', fn() => Inertia::render('Dashboard'))->name('dashboard');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});
