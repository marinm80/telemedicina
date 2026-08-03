<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * HALLAZGO 15+16: Secretos de users inaccesibles + auth via SECURITY DEFINER.
 * MENOR b: vista v_schedule_blocks_availability reemplaza GRANT columna.
 *
 * password y remember_token quedan FUERA del alcance de CUALQUIER política.
 * El acceso privilegiado para autenticación va en fn_user_for_auth, no en
 * permisos de tabla. Misma herramienta que fn_audit_log.
 *
 * users_select NO necesita cláusula "sin contexto". El login pasa por la
 * función SECURITY DEFINER, no por SELECT directo.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql_migration')->unprepared("
            -- ================================================================
            -- 1. FUNCIÓN fn_user_for_auth — SECURITY DEFINER
            -- ================================================================
            -- Devuelve las credenciales para autenticación sin exponer password
            -- ni remember_token a app_runtime. Corre como el OWNER (pgsql_owner),
            -- que tiene acceso completo a users.
            --
            -- search_path fijado a pg_catalog, public para evitar inyección de
            -- funciones vía manipulación del search_path.
            -- ================================================================

            CREATE OR REPLACE FUNCTION fn_user_for_auth(p_email text)
            RETURNS TABLE (
                id             uuid,
                name           varchar,
                last_name      varchar,
                email          varchar,
                password       varchar,
                timezone       varchar,
                email_verified_at timestamptz,
                is_active      boolean,
                remember_token varchar,
                created_at     timestamptz,
                updated_at     timestamptz
            )
            LANGUAGE sql
            SECURITY DEFINER
            SET search_path = pg_catalog, public
            STABLE
            AS \$\$
                SELECT
                    u.id, u.name, u.last_name, u.email, u.password,
                    u.timezone, u.email_verified_at, u.is_active,
                    u.remember_token, u.created_at, u.updated_at
                FROM users u
                WHERE u.email = p_email
                  AND u.is_active = true
                  AND u.deleted_at IS NULL
                LIMIT 1;
            \$\$;

            -- Solo app_runtime puede ejecutar. PUBLIC revocado.
            REVOKE EXECUTE ON FUNCTION fn_user_for_auth(text) FROM PUBLIC;
            GRANT EXECUTE ON FUNCTION fn_user_for_auth(text) TO app_runtime;

            -- ================================================================
            -- 2. fn_update_remember_token — SECURITY DEFINER
            -- ================================================================
            -- Laravel necesita escribir remember_token. Con el GRANT de columna
            -- sin remember_token en SELECT, y sin UPDATE blanket, la actualización
            -- del token pasa por esta función.
            -- ================================================================

            CREATE OR REPLACE FUNCTION fn_update_remember_token(p_user_id uuid, p_token varchar)
            RETURNS void
            LANGUAGE sql
            SECURITY DEFINER
            SET search_path = pg_catalog, public
            AS \$\$
                UPDATE users
                SET remember_token = p_token, updated_at = now()
                WHERE id = p_user_id;
            \$\$;

            REVOKE EXECUTE ON FUNCTION fn_update_remember_token(uuid, varchar) FROM PUBLIC;
            GRANT EXECUTE ON FUNCTION fn_update_remember_token(uuid, varchar) TO app_runtime;

            -- ================================================================
            -- 3. GRANT de columna SELECT en users — sin password ni remember_token
            -- ================================================================
            REVOKE SELECT ON users FROM app_runtime;
            GRANT SELECT (id, name, last_name, email, timezone, email_verified_at,
                          is_active, created_at, updated_at, deleted_at) ON users TO app_runtime;

            -- ================================================================
            -- 4. Vista v_schedule_blocks_availability — sin reason
            -- ================================================================
            -- Reemplaza el GRANT de columna en schedule_blocks.
            -- schedule_blocks.reason es sensible: motivos del bloqueo del médico.
            -- La disponibilidad necesita el RANGO, no el MOTIVO.
            -- ================================================================

            CREATE OR REPLACE VIEW v_schedule_blocks_availability
            WITH (security_barrier = true)
            AS SELECT
                sb.id,
                sb.doctor_profile_id,
                sb.blocked_date,
                sb.franja,
                sb.created_at,
                sb.updated_at
            FROM schedule_blocks sb
            JOIN doctor_profiles dp ON dp.id = sb.doctor_profile_id
            WHERE dp.status = 'approved'
              AND dp.deleted_at IS NULL;

            GRANT SELECT ON v_schedule_blocks_availability TO app_runtime;
            GRANT SELECT ON v_schedule_blocks_availability TO app_worker;

            -- Revertir el GRANT de columna en schedule_blocks de la migración 000004.
            -- Con la vista, app_runtime no necesita SELECT directo en schedule_blocks.
            -- El REVOKE de columna no aplica cuando hay table-level grant activo;
            -- primero verificar el estado actual y limpiar.
            REVOKE SELECT (id, doctor_profile_id, blocked_date, franja, created_at, updated_at) ON schedule_blocks FROM app_runtime;

            -- schedule_blocks ya tenía SELECT revocado a nivel tabla en 000004,
            -- aquí revocamos el column-level grant que se dio en 000004.
            -- El doctor_profile dueño accede via schedule_blocks_select policy
            -- (que necesita SELECT sobre la tabla), así que RE-GRANT a nivel tabla
            -- con la policy estricta como control:
            GRANT SELECT ON schedule_blocks TO app_runtime;
        ");
    }

    public function down(): void
    {
        DB::connection('pgsql_migration')->unprepared("
            -- Restaurar SELECT blanket en users
            REVOKE SELECT (id, name, last_name, email, timezone, email_verified_at,
                          is_active, created_at, updated_at, deleted_at) ON users FROM app_runtime;
            GRANT SELECT ON users TO app_runtime;

            -- Eliminar funciones
            DROP FUNCTION IF EXISTS fn_user_for_auth(text);
            DROP FUNCTION IF EXISTS fn_update_remember_token(uuid, varchar);

            -- Eliminar vista
            DROP VIEW IF EXISTS v_schedule_blocks_availability;

            -- Restaurar GRANT de columna en schedule_blocks (como estaba en 000004)
            REVOKE SELECT ON schedule_blocks FROM app_runtime;
            GRANT SELECT (id, doctor_profile_id, blocked_date, franja, created_at, updated_at) ON schedule_blocks TO app_runtime;
        ");
    }
};
