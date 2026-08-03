<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Proveedor de autenticación seguro que usa fn_user_for_auth (SECURITY DEFINER)
 * en lugar de SELECT directo sobre la tabla users.
 *
 * password es inaccesible para app_runtime vía SELECT.
 * Este proveedor lo obtiene a través de fn_user_for_auth que corre como owner.
 *
 * remember_token NUNCA sale de la base de datos:
 *   - fn_user_by_remember_token(id, token) compara DENTRO de PostgreSQL
 *   - fn_rotate_remember_token() genera y escribe el token internamente
 *
 * Hallazgo 18: fn_user_for_auth ya no devuelve remember_token.
 * Hallazgo 19: fn_rotate_remember_token reemplaza fn_update_remember_token.
 *              Solo podés rotar tu propio token, y a un valor que no elegiste.
 */

namespace App\Auth;

use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class SecureEloquentUserProvider extends EloquentUserProvider
{
    /**
     * Recuperar usuario por credenciales (login).
     *
     * fn_user_for_auth corre como SECURITY DEFINER y devuelve password
     * sin que app_runtime tenga GRANT SELECT sobre esa columna.
     * remember_token NO está en el resultado (Hallazgo 18).
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (!isset($credentials['email'])) {
            return parent::retrieveByCredentials($credentials);
        }

        $row = DB::selectOne(
            'SELECT * FROM fn_user_for_auth(?)',
            [$credentials['email']]
        );

        if (!$row) {
            return null;
        }

        // Hidratar el modelo User con los datos de la función
        $user = new User();
        $user->exists = true;
        $user->forceFill((array) $row);

        return $user;
    }

    /**
     * Validar credenciales contra el hash obtenido de fn_user_for_auth.
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        if (!isset($credentials['password'])) {
            return false;
        }

        return Hash::check($credentials['password'], $user->getAuthPassword());
    }

    /**
     * Recuperar usuario por remember_token (sesión persistente).
     *
     * fn_user_by_remember_token(id, token) compara el token DENTRO de la
     * base y devuelve solo el UUID si coincide, o nada. El token nunca
     * sale de PostgreSQL. (Hallazgo 18)
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        if (empty($token)) {
            return null;
        }

        $row = DB::selectOne(
            'SELECT fn_user_by_remember_token(?::uuid, ?)',
            [$identifier, $token]
        );

        if (!$row || !$row->fn_user_by_remember_token) {
            return null;
        }

        return $this->retrieveById($row->fn_user_by_remember_token);
    }

    /**
     * Actualizar remember_token via fn_rotate_remember_token().
     *
     * La función lee app.current_user_id — solo podés rotar el tuyo.
     * Genera el token con gen_random_bytes — no elegís el valor.
     * Lo escribe y lo devuelve. (Hallazgo 19)
     *
     * El token devuelto se asigna al modelo en memoria para que Laravel
     * lo incluya en la cookie. En la próxima sesión, la verificación
     * pasa por fn_user_by_remember_token (comparación interna).
     */
    public function updateRememberToken(Authenticatable $user, $token): void
    {
        // Laravel llama esto con $token = null al logout (borrar cookie).
        // En ese caso no rotamos — no hay cookie que actualizar.
        // El GRANT columna impide que app_runtime escriba NULL directamente,
        // y la función requiere app.current_user_id definido.
        if ($token === null || $token === '') {
            return;
        }

        $row = DB::selectOne('SELECT fn_rotate_remember_token()');

        if ($row && $row->fn_rotate_remember_token) {
            // Actualizar el modelo en memoria para que la cookie use el token generado
            $user->setRememberToken($row->fn_rotate_remember_token);
        }
    }
}
