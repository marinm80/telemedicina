<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared("
            CREATE OR REPLACE FUNCTION fn_prevent_schedule_block_appointment_overlap()
            RETURNS trigger
            LANGUAGE plpgsql
            SECURITY DEFINER
            SET search_path = pg_catalog, public
            AS \$\$
            DECLARE
                v_user_id uuid;
                v_tz text;
                v_block_range tstzrange;
                v_conflict_count integer;
            BEGIN
                -- Obtener el user_id del médico y su zona horaria desde doctor_profiles y users
                SELECT d.user_id, COALESCE(u.timezone, 'UTC')
                INTO v_user_id, v_tz
                FROM doctor_profiles d
                JOIN users u ON u.id = d.user_id
                WHERE d.id = NEW.doctor_profile_id;

                IF v_user_id IS NULL THEN
                    RAISE EXCEPTION 'fn_prevent_schedule_block_appointment_overlap: perfil médico no encontrado'
                        USING ERRCODE = 'P0001';
                END IF;

                -- Convertir la fecha de pared y timerange a tstzrange en UTC
                v_block_range := tstzrange(
                    (NEW.blocked_date + lower(NEW.franja)) AT TIME ZONE v_tz,
                    (NEW.blocked_date + upper(NEW.franja)) AT TIME ZONE v_tz,
                    '[)'
                );

                -- Verificar si existe alguna cita confirmada o pendiente que solape con el bloqueo
                SELECT COUNT(*)
                INTO v_conflict_count
                FROM appointments a
                WHERE a.doctor_id = v_user_id
                  AND a.status IN ('pending', 'confirmed')
                  AND a.franja && v_block_range;

                IF v_conflict_count > 0 THEN
                    RAISE EXCEPTION 'No se puede bloquear un horario con citas activas confirmadas o pendientes'
                        USING ERRCODE = 'P0002';
                END IF;

                RETURN NEW;
            END;
            \$\$;

            REVOKE EXECUTE ON FUNCTION fn_prevent_schedule_block_appointment_overlap() FROM PUBLIC;
            GRANT EXECUTE ON FUNCTION fn_prevent_schedule_block_appointment_overlap() TO app_runtime;

            DROP TRIGGER IF EXISTS trg_prevent_schedule_block_appointment_overlap ON schedule_blocks;
            CREATE TRIGGER trg_prevent_schedule_block_appointment_overlap
                BEFORE INSERT OR UPDATE ON schedule_blocks
                FOR EACH ROW
                EXECUTE FUNCTION fn_prevent_schedule_block_appointment_overlap();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_prevent_schedule_block_appointment_overlap ON schedule_blocks;
            DROP FUNCTION IF EXISTS fn_prevent_schedule_block_appointment_overlap();
        ");
    }
};
