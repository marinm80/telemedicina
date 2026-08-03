<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 */

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
            -- Add actor_pg column to audit_logs
            ALTER TABLE audit_logs ADD COLUMN actor_pg varchar(63) NOT NULL DEFAULT current_user;

            -- Audit trigger function
            CREATE OR REPLACE FUNCTION fn_audit_log() RETURNS TRIGGER AS $$
            DECLARE
                v_user_id uuid;
                v_actor_pg text;
                v_record_id uuid;
                v_old jsonb;
                v_new jsonb;
            BEGIN
                -- Obtener el actor HTTP si existe; NULL si no hay contexto (worker, seeders)
                v_user_id := NULLIF(current_setting('app.current_user_id', true), '')::uuid;

                -- Siempre registrar el rol de PostgreSQL como contexto de sistema
                v_actor_pg := session_user;

                IF TG_OP = 'INSERT' THEN
                    v_record_id := NEW.id;
                    v_old := NULL;
                    v_new := to_jsonb(NEW);
                ELSIF TG_OP = 'UPDATE' THEN
                    -- Optimización: no registrar si la fila no cambió
                    IF to_jsonb(OLD) = to_jsonb(NEW) THEN
                        RETURN NEW;
                    END IF;
                    v_record_id := NEW.id;
                    v_old := to_jsonb(OLD);
                    v_new := to_jsonb(NEW);
                ELSIF TG_OP = 'DELETE' THEN
                    v_record_id := OLD.id;
                    v_old := to_jsonb(OLD);
                    v_new := NULL;
                END IF;

                INSERT INTO audit_logs (table_name, record_id, action, user_id, actor_pg, old_values, new_values)
                VALUES (TG_TABLE_NAME, v_record_id, TG_OP, v_user_id, v_actor_pg, v_old, v_new);

                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql SECURITY DEFINER;

            -- Triggers individuales por tabla auditada
            CREATE TRIGGER trg_audit_appointments       AFTER INSERT OR UPDATE ON appointments        FOR EACH ROW EXECUTE FUNCTION fn_audit_log();
            CREATE TRIGGER trg_audit_consultation_notes  AFTER INSERT OR UPDATE ON consultation_notes  FOR EACH ROW EXECUTE FUNCTION fn_audit_log();
            CREATE TRIGGER trg_audit_note_amendments     AFTER INSERT ON note_amendments                FOR EACH ROW EXECUTE FUNCTION fn_audit_log();
            CREATE TRIGGER trg_audit_patient_profiles    AFTER INSERT OR UPDATE ON patient_profiles      FOR EACH ROW EXECUTE FUNCTION fn_audit_log();
            CREATE TRIGGER trg_audit_patient_allergies   AFTER INSERT OR UPDATE ON patient_allergies    FOR EACH ROW EXECUTE FUNCTION fn_audit_log();
            CREATE TRIGGER trg_audit_patient_conditions  AFTER INSERT OR UPDATE ON patient_conditions   FOR EACH ROW EXECUTE FUNCTION fn_audit_log();
            CREATE TRIGGER trg_audit_patient_medications AFTER INSERT OR UPDATE ON patient_medications  FOR EACH ROW EXECUTE FUNCTION fn_audit_log();
            CREATE TRIGGER trg_audit_vital_signs         AFTER INSERT ON vital_signs                     FOR EACH ROW EXECUTE FUNCTION fn_audit_log();
            CREATE TRIGGER trg_audit_schedules           AFTER INSERT OR UPDATE OR DELETE ON schedules   FOR EACH ROW EXECUTE FUNCTION fn_audit_log();
            CREATE TRIGGER trg_audit_schedule_blocks     AFTER INSERT OR UPDATE OR DELETE ON schedule_blocks FOR EACH ROW EXECUTE FUNCTION fn_audit_log();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection('pgsql_migration')->unprepared("
            DROP TRIGGER IF EXISTS trg_audit_schedule_blocks ON schedule_blocks;
            DROP TRIGGER IF EXISTS trg_audit_schedules ON schedules;
            DROP TRIGGER IF EXISTS trg_audit_vital_signs ON vital_signs;
            DROP TRIGGER IF EXISTS trg_audit_patient_medications ON patient_medications;
            DROP TRIGGER IF EXISTS trg_audit_patient_conditions ON patient_conditions;
            DROP TRIGGER IF EXISTS trg_audit_patient_allergies ON patient_allergies;
            DROP TRIGGER IF EXISTS trg_audit_patient_profiles ON patient_profiles;
            DROP TRIGGER IF EXISTS trg_audit_note_amendments ON note_amendments;
            DROP TRIGGER IF EXISTS trg_audit_consultation_notes ON consultation_notes;
            DROP TRIGGER IF EXISTS trg_audit_appointments ON appointments;
            DROP FUNCTION IF EXISTS fn_audit_log();
            ALTER TABLE audit_logs DROP COLUMN IF EXISTS actor_pg;
        ");
    }
};
