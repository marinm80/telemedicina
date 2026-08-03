<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * HALLAZGOS 18, 19, 20:
 *
 * 18. fn_user_for_auth devolvía remember_token: reintroducción del secreto
 *     que el GRANT de columna acababa de sacar. Quitado del RETURNS TABLE
 *     y del SELECT. Creada fn_user_by_remember_token que compara DENTRO de
 *     la base — el token nunca sale de PostgreSQL.
 *
 * 19. fn_update_remember_token aceptaba id+token arbitrarios: primitiva de
 *     suplantación. Reemplazada por fn_rotate_remember_token() que lee
 *     current_setting('app.current_user_id'), genera el token con
 *     gen_random_bytes, y lo devuelve. Solo podés rotar TU propio token,
 *     y a un valor que no elegiste.
 *
 * 20. La 000005 re-otorgaba SELECT a nivel tabla en schedule_blocks,
 *     haciendo reason legible por cualquier paciente vía la tabla directa.
 *     Revocado. schedule_blocks_select restringida a dueño + admin + worker.
 *     El paciente pasa por v_schedule_blocks_availability que evade RLS
 *     al correr como su owner. schedules_select confirmada correcta:
 *     la disponibilidad es pública y no tiene columna sensible.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql_migration')->unprepared("
            -- pgcrypto requerido para gen_random_bytes en fn_rotate_remember_token
            CREATE EXTENSION IF NOT EXISTS pgcrypto;

            -- ================================================================
            -- HALLAZGO 18: fn_user_for_auth — quitar remember_token
            -- ================================================================
            -- remember_token en el RETURNS TABLE permitía:
            --   SELECT remember_token FROM fn_user_for_auth('victima@ejemplo.com');
            -- Una sentencia y tenés la credencial de sesión viva.
            --
            -- DROP obligatorio: la firma cambia (se quita remember_token del
            -- RETURNS TABLE). CREATE OR REPLACE no permite cambiar el tipo de retorno.
            -- ================================================================

            DROP FUNCTION IF EXISTS fn_user_for_auth(text);

            CREATE FUNCTION fn_user_for_auth(p_email text)
            RETURNS TABLE (
                id                uuid,
                name              varchar,
                last_name         varchar,
                email             varchar,
                password          varchar,
                timezone          varchar,
                email_verified_at timestamptz,
                is_active         boolean,
                created_at        timestamptz,
                updated_at        timestamptz
            )
            LANGUAGE sql
            SECURITY DEFINER
            SET search_path = pg_catalog, public
            STABLE
            AS \$\$
                SELECT
                    u.id, u.name, u.last_name, u.email, u.password,
                    u.timezone, u.email_verified_at, u.is_active,
                    u.created_at, u.updated_at
                FROM users u
                WHERE u.email = p_email
                  AND u.is_active = true
                  AND u.deleted_at IS NULL
                LIMIT 1;
            \$\$;

            -- Permisos (reiterar por CREATE OR REPLACE)
            REVOKE EXECUTE ON FUNCTION fn_user_for_auth(text) FROM PUBLIC;
            GRANT EXECUTE ON FUNCTION fn_user_for_auth(text) TO app_runtime;

            -- ================================================================
            -- HALLAZGO 18: fn_user_by_remember_token — comparación interna
            -- ================================================================
            -- El token nunca sale de la base. Recibe el token que presenta
            -- la cookie y devuelve el id del usuario SI coincide, o nada.
            -- ================================================================

            CREATE OR REPLACE FUNCTION fn_user_by_remember_token(
                p_user_id uuid,
                p_token   text
            )
            RETURNS uuid
            LANGUAGE sql
            SECURITY DEFINER
            SET search_path = pg_catalog, public
            STABLE
            AS \$\$
                SELECT u.id
                FROM users u
                WHERE u.id = p_user_id
                  AND u.remember_token IS NOT NULL
                  AND u.remember_token = p_token
                  AND u.is_active = true
                  AND u.deleted_at IS NULL
                LIMIT 1;
            \$\$;

            REVOKE EXECUTE ON FUNCTION fn_user_by_remember_token(uuid, text) FROM PUBLIC;
            GRANT EXECUTE ON FUNCTION fn_user_by_remember_token(uuid, text) TO app_runtime;

            -- ================================================================
            -- HALLAZGO 19: DROP fn_update_remember_token — primitiva de suplantación
            -- ================================================================
            -- La firma aceptaba id + token del llamador. Se reemplaza por
            -- fn_rotate_remember_token() que:
            --   1. Lee app.current_user_id — solo podés rotar el tuyo
            --   2. Genera el token con gen_random_bytes — no elegís el valor
            --   3. Lo escribe y lo devuelve
            -- ================================================================

            DROP FUNCTION IF EXISTS fn_update_remember_token(uuid, varchar);

            CREATE OR REPLACE FUNCTION fn_rotate_remember_token()
            RETURNS text
            LANGUAGE plpgsql
            SECURITY DEFINER
            SET search_path = pg_catalog, public
            AS \$\$
            DECLARE
                v_user_id uuid;
                v_token   text;
            BEGIN
                v_user_id := NULLIF(current_setting('app.current_user_id', true), '')::uuid;

                IF v_user_id IS NULL THEN
                    RAISE EXCEPTION 'fn_rotate_remember_token: app.current_user_id no está definido'
                        USING ERRCODE = 'P0001';
                END IF;

                v_token := encode(public.gen_random_bytes(32), 'hex');

                UPDATE users
                SET remember_token = v_token, updated_at = now()
                WHERE id = v_user_id
                  AND is_active = true
                  AND deleted_at IS NULL;

                IF NOT FOUND THEN
                    RAISE EXCEPTION 'fn_rotate_remember_token: usuario % no encontrado o inactivo', v_user_id
                        USING ERRCODE = 'P0001';
                END IF;

                RETURN v_token;
            END;
            \$\$;

            REVOKE EXECUTE ON FUNCTION fn_rotate_remember_token() FROM PUBLIC;
            GRANT EXECUTE ON FUNCTION fn_rotate_remember_token() TO app_runtime;

            -- ================================================================
            -- HALLAZGO 20: schedule_blocks — revocar SELECT tabla, endurecer política
            -- ================================================================
            -- La 000005 volvió a dar SELECT tabla (última línea), haciendo
            -- reason legible. schedule_blocks_select tenía cláusula de
            -- 'cualquier médico aprobado' sin referencia al usuario actual.
            --
            -- Solución:
            --   - REVOKE SELECT tabla. El paciente pasa por la vista.
            --   - La vista evade RLS por diseño (corre como su owner).
            --   - La política queda: dueño + admin + worker. Nadie más.
            -- ================================================================

            REVOKE SELECT ON schedule_blocks FROM app_runtime;

            DROP POLICY IF EXISTS schedule_blocks_select ON schedule_blocks;
            CREATE POLICY schedule_blocks_select ON schedule_blocks
                FOR SELECT USING (
                    CURRENT_USER = 'app_worker'
                    OR current_setting('app.current_user_role', true) = 'admin'
                    OR EXISTS (
                        SELECT 1 FROM doctor_profiles dp
                        WHERE dp.id = schedule_blocks.doctor_profile_id
                          AND dp.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    )
                );

            -- ================================================================
            -- schedules_select: CONFIRMADA CORRECTA — NO se modifica.
            -- La disponibilidad es pública y no hay columna sensible en schedules.
            -- La cláusula de 'médico aprobado' es intencional: un paciente
            -- necesita ver la agenda de cualquier doctor aprobado para reservar.
            -- schedules no contiene equivalente a 'reason'. Las columnas son:
            --   id, doctor_profile_id, day_of_week, start_time, end_time,
            --   is_active, created_at, updated_at.
            -- ================================================================
        ");
    }

    public function down(): void
    {
        DB::connection('pgsql_migration')->unprepared("
            -- Restaurar fn_user_for_auth con remember_token
            DROP FUNCTION IF EXISTS fn_user_for_auth(text);
            CREATE FUNCTION fn_user_for_auth(p_email text)
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
            REVOKE EXECUTE ON FUNCTION fn_user_for_auth(text) FROM PUBLIC;
            GRANT EXECUTE ON FUNCTION fn_user_for_auth(text) TO app_runtime;

            -- Restaurar fn_update_remember_token
            DROP FUNCTION IF EXISTS fn_rotate_remember_token();
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

            DROP FUNCTION IF EXISTS fn_user_by_remember_token(uuid, text);

            -- Restaurar SELECT tabla en schedule_blocks
            GRANT SELECT ON schedule_blocks TO app_runtime;

            DROP POLICY IF EXISTS schedule_blocks_select ON schedule_blocks;
            CREATE POLICY schedule_blocks_select ON schedule_blocks
                FOR SELECT USING (
                    CURRENT_USER = 'app_worker'
                    OR current_setting('app.current_user_role', true) = 'admin'
                    OR EXISTS (
                        SELECT 1 FROM doctor_profiles dp
                        WHERE dp.id = schedule_blocks.doctor_profile_id
                          AND dp.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    )
                    OR EXISTS (
                        SELECT 1 FROM doctor_profiles dp
                        WHERE dp.id = schedule_blocks.doctor_profile_id
                          AND dp.status = 'approved'
                    )
                );
        ");
    }
};
