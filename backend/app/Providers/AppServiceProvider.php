<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 */

namespace App\Providers;

use App\Auth\SecureEloquentUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registrar proveedor de autenticación seguro que usa fn_user_for_auth
        // (SECURITY DEFINER) en lugar de SELECT * sobre users. password y
        // remember_token quedan fuera del alcance de app_runtime.
        Auth::provider('secure-eloquent', function ($app, array $config) {
            return new SecureEloquentUserProvider(
                $app['hash'],
                $config['model'],
            );
        });
    }
}
