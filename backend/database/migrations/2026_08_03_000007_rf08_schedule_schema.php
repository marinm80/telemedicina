<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * RF-08 — Migración de esquema para agenda del médico.
 *
 * Cuatro cambios decididos y documentados en DECISIONES_ALCANCE.md §11:
 *
 * 1. DROP COLUMN schedules.is_active
 *    Decisión 11.2: dos mecanismos para "apagado" divergen. deleted_at
 *    preserva la fila y el EXCLUDE ya lo maneja. La pausa temporal se
 *    hace con schedule_blocks.
 *
 * 2. CREATE UNIQUE INDEX schedule_blocks_unique_exact
 *    Decisión 11.3: idempotencia gratis. Un reintento con los mismos
 *    datos choca con 23505 y se traduce a 204.
 *
 * 3. fn_prevent_timezone_change_with_appointments + trigger
 *    Decisión 11.1d: un médico no puede cambiar users.timezone con
 *    citas futuras activas. Trigger BEFORE UPDATE para evitar la
 *    condición de carrera verificar-y-después-escribir (D1).
 *
 * 4. GRANT SELECT ON schedule_blocks TO app_runtime
 *    Paso 0a de RF-08: la migración 000006 revocó SELECT de tabla y no
 *    concedió nada. La política schedule_blocks_select (dueño + admin +
 *    worker) es inalcanzable sin GRANT de tabla. Esto NO contradice el
 *    hallazgo 20: lo peligroso era política laxa + GRANT de tabla. La
 *    política cambió (ya no tiene "cualquier médico aprobado"); el GRANT
 *    ya no es el problema.
 *
 * DECLARACIÓN DE RIESGO:
 *   - DROP COLUMN en tabla vacía (cero filas en producción): costo cero.
 *   - CREATE UNIQUE INDEX en tabla vacía: costo cero.
 *   - Función + trigger: no bloquea escrituras existentes (BEFORE UPDATE
 *     solo verifica el cambio de timezone).
 *   - GRANT: operación DDL instantánea.
 *   - Reversa: down() restaura todo al estado anterior.
 */

namespace Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::connection('pgsql_migration')->unprepared("
            -- ============================================================
            -- 1. DROP COLUMN schedules.is_active
            --    Decisión 11.2: sobra. deleted_at cubre el borrado lógico
            --    y schedule_blocks cubre la pausa temporal.
            -- ============================================================
            ALTER TABLE schedules DROP COLUMN IF EXISTS is_active;

            -- ============================================================
            -- 2. CREATE UNIQUE INDEX schedule_blocks_unique_exact
            --    Decisión 11.3: prohíbe el duplicado exacto sin prohibir
            --    solapamiento. Idempotencia: reintento → 23505 → 204.
            -- ============================================================
            CREATE UNIQUE INDEX IF NOT EXISTS schedule_blocks_unique_exact
                ON schedule_blocks (doctor_profile_id, blocked_date, franja);

            -- ============================================================
            -- 3. TRIGGER: prohibir cambio de timezone con citas futuras
            --    Decisión 11.1d: las citas son instantes absolutos
            --    (tstzrange) y los horarios son hora de pared (timerange).
            --    Cambiar la zona desplaza la representación local sin
            --    mover los instantes. Trigger BEFORE UPDATE para evitar
            --    verificar-y-después-escribir (D1 del protocolo).
            -- ============================================================
            CREATE OR REPLACE FUNCTION fn_prevent_timezone_change_with_appointments()
            RETURNS trigger
            LANGUAGE plpgsql
            SET search_path = pg_catalog, public
            AS \$\$
            BEGIN
                IF OLD.timezone IS DISTINCT FROM NEW.timezone THEN
                    IF EXISTS (
                        SELECT 1 FROM public.appointments
                        WHERE doctor_id = NEW.id
                          AND status IN ('pending', 'confirmed')
                          AND upper(franja) > now()
                    ) THEN
                        RAISE EXCEPTION
                            'No se puede cambiar la zona horaria con citas futuras activas'
                            USING ERRCODE = 'P0001';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            \$\$;

            CREATE TRIGGER trg_prevent_timezone_change
                BEFORE UPDATE ON users
                FOR EACH ROW
                EXECUTE FUNCTION fn_prevent_timezone_change_with_appointments();

            -- ============================================================
            -- 4. GRANT SELECT ON schedule_blocks TO app_runtime
            --    Paso 0a: la 000006 revocó SELECT de tabla. La política
            --    schedule_blocks_select solo permite dueño + admin + worker.
            --    Sin GRANT de tabla la política es inalcanzable: RLS filtra
            --    DESPUÉS del privilegio.
            --    Esto NO contradice el hallazgo 20: lo peligroso era la
            --    política laxa (cualquier médico aprobado) + GRANT de tabla.
            --    La política cambió; el GRANT ya no es el problema.
            -- ============================================================
            GRANT SELECT ON schedule_blocks TO app_runtime;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection('pgsql_migration')->unprepared("
            -- Revocar SELECT de schedule_blocks (volver al estado de 000006)
            REVOKE SELECT ON schedule_blocks FROM app_runtime;

            -- Quitar trigger y función de timezone
            DROP TRIGGER IF EXISTS trg_prevent_timezone_change ON users;
            DROP FUNCTION IF EXISTS fn_prevent_timezone_change_with_appointments();

            -- Quitar índice de idempotencia
            DROP INDEX IF EXISTS schedule_blocks_unique_exact;

            -- Restaurar is_active en schedules
            ALTER TABLE schedules ADD COLUMN IF NOT EXISTS is_active boolean NOT NULL DEFAULT true;
        ");
    }
};
