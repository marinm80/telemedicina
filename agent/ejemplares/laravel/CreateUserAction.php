<?php
declare(strict_types=1);
/**
 * ====================================================================
 * EJEMPLAR CANÓNICO — Action / Servicio (Laravel 11)
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * QUÉ FIJA ESTE EJEMPLAR
 * Una clase, una operación de negocio, un método público: `handle()`.
 * NO recibe Request ni devuelve Response: no sabe que HTTP existe. Eso permite
 * invocarla igual desde un controlador, desde un job de cola o desde Artisan.
 *
 * ALTERNATIVA DESCARTADA
 * Un `UserService` con quince métodos. Crece sin techo y termina siendo el
 * mismo "fat controller" con otro nombre. Una Action por operación se mantiene
 * chica y se testea sola.
 *
 * TRANSACCIÓN + EFECTO EXTERNO DESPUÉS DEL COMMIT: ver el comentario abajo.
 * Es la regla de la sección 1.3 del Manual.
 */

namespace App\Actions\Users;

use App\Events\UserCreated;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateUserAction
{
    /**
     * @param  array{email: string, name: string, role?: string}  $datos
     */
    public function handle(array $datos): User
    {
        $user = DB::transaction(function () use ($datos): User {
            // La defensa real contra duplicados es el índice UNIQUE de la tabla.
            // La regla 'unique' del Form Request solo da un mensaje amable: entre
            // validar y escribir hay una condición de carrera.
            return User::create([
                'email' => $datos['email'],
                'name'  => $datos['name'],
                'role'  => $datos['role'] ?? 'user',
            ]);
        });

        // FUERA de la transacción, a propósito. Despachar un evento que manda un
        // correo o llama a una API dentro de la transacción mantiene filas
        // bloqueadas mientras se espera la red: es como se agota el pool de
        // conexiones y se cae la aplicación entera.
        UserCreated::dispatch($user);

        return $user;
    }
}
