<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Hallazgos 3, 4, 5, 9 + RLS faltante en patient_conditions/medications:
 *  3 - fn_audit_log() SECURITY DEFINER sin search_path fijado
 *  4 - REVOKE INSERT ON audit_logs FROM app_runtime, app_worker
 *  5 - actor_pg DEFAULT current_user dentro de SECURITY DEFINER es 'postgres'
 *  9 - patient_profiles_select no incluye role='agent'
 *  Descubierto por test: patient_conditions y patient_medications sin RLS
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql_migration')->unprepared("
            -- HALLAZGO 3: fijar search_path y revocar EXECUTE público
            CREATE OR REPLACE FUNCTION fn_audit_log() RETURNS TRIGGER AS \$fn\$
            DECLARE
                v_user_id uuid;
                v_actor_pg text;
                v_record_id uuid;
                v_old jsonb;
                v_new jsonb;
            BEGIN
                v_user_id := NULLIF(current_setting('app.current_user_id', true), '')::uuid;
                v_actor_pg := session_user;

                IF TG_OP = 'INSERT' THEN
                    v_record_id := NEW.id;
                    v_old := NULL;
                    v_new := to_jsonb(NEW);
                ELSIF TG_OP = 'UPDATE' THEN
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
            \$fn\$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = pg_catalog, public;

            REVOKE EXECUTE ON FUNCTION fn_audit_log() FROM PUBLIC;

            -- HALLAZGO 5: DROP DEFAULT en actor_pg
            ALTER TABLE audit_logs ALTER COLUMN actor_pg DROP DEFAULT;

            -- HALLAZGO 4: REVOKE INSERT — solo el trigger (SECURITY DEFINER) escribe
            REVOKE INSERT ON audit_logs FROM app_runtime;
            REVOKE INSERT ON audit_logs FROM app_worker;

            -- HALLAZGO 9: el agente necesita leer patient_profiles (datos de contacto)
            DROP POLICY IF EXISTS patient_profiles_select ON patient_profiles;
            CREATE POLICY patient_profiles_select ON patient_profiles
                FOR SELECT USING (
                    current_user = 'app_worker'
                    OR current_setting('app.current_user_role', true) = 'admin'
                    OR current_setting('app.current_user_role', true) = 'agent'
                    OR user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    OR (current_setting('app.current_user_role', true) = 'doctor' AND EXISTS (
                        SELECT 1 FROM appointments a
                        WHERE a.patient_id = patient_profiles.user_id
                          AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                          AND a.status IN ('confirmed', 'completed')
                    ))
                );

            -- DESCUBIERTO POR TEST: patient_conditions sin RLS
            ALTER TABLE patient_conditions ENABLE ROW LEVEL SECURITY;
            CREATE POLICY patient_conditions_select ON patient_conditions
                FOR SELECT USING (
                    current_user = 'app_worker' OR EXISTS (
                        SELECT 1 FROM patient_profiles p
                        WHERE p.id = patient_profile_id AND (
                            current_setting('app.current_user_role', true) = 'admin'
                            OR p.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                            OR (current_setting('app.current_user_role', true) = 'doctor' AND EXISTS (
                                SELECT 1 FROM appointments a
                                WHERE a.patient_id = p.user_id
                                  AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                                  AND a.status IN ('confirmed', 'completed')
                            ))
                        )
                    )
                );
            CREATE POLICY patient_conditions_insert ON patient_conditions
                FOR INSERT WITH CHECK (EXISTS (
                    SELECT 1 FROM patient_profiles p
                    WHERE p.id = patient_profile_id AND (
                        p.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                        OR (current_setting('app.current_user_role', true) = 'doctor' AND EXISTS (
                            SELECT 1 FROM appointments a
                            WHERE a.patient_id = p.user_id
                              AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                              AND a.status IN ('confirmed', 'completed')
                        ))
                    )
                ));
            CREATE POLICY patient_conditions_update ON patient_conditions
                FOR UPDATE USING (EXISTS (
                    SELECT 1 FROM patient_profiles p
                    WHERE p.id = patient_profile_id AND (
                        p.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                        OR (current_setting('app.current_user_role', true) = 'doctor' AND EXISTS (
                            SELECT 1 FROM appointments a
                            WHERE a.patient_id = p.user_id
                              AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                              AND a.status IN ('confirmed', 'completed')
                        ))
                    )
                ));

            -- DESCUBIERTO POR TEST: patient_medications sin RLS
            ALTER TABLE patient_medications ENABLE ROW LEVEL SECURITY;
            CREATE POLICY patient_medications_select ON patient_medications
                FOR SELECT USING (
                    current_user = 'app_worker' OR EXISTS (
                        SELECT 1 FROM patient_profiles p
                        WHERE p.id = patient_profile_id AND (
                            current_setting('app.current_user_role', true) = 'admin'
                            OR p.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                            OR (current_setting('app.current_user_role', true) = 'doctor' AND EXISTS (
                                SELECT 1 FROM appointments a
                                WHERE a.patient_id = p.user_id
                                  AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                                  AND a.status IN ('confirmed', 'completed')
                            ))
                        )
                    )
                );
            CREATE POLICY patient_medications_insert ON patient_medications
                FOR INSERT WITH CHECK (EXISTS (
                    SELECT 1 FROM patient_profiles p
                    WHERE p.id = patient_profile_id AND (
                        p.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                        OR (current_setting('app.current_user_role', true) = 'doctor' AND EXISTS (
                            SELECT 1 FROM appointments a
                            WHERE a.patient_id = p.user_id
                              AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                              AND a.status IN ('confirmed', 'completed')
                        ))
                    )
                ));
            CREATE POLICY patient_medications_update ON patient_medications
                FOR UPDATE USING (EXISTS (
                    SELECT 1 FROM patient_profiles p
                    WHERE p.id = patient_profile_id AND (
                        p.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                        OR (current_setting('app.current_user_role', true) = 'doctor' AND EXISTS (
                            SELECT 1 FROM appointments a
                            WHERE a.patient_id = p.user_id
                              AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                              AND a.status IN ('confirmed', 'completed')
                        ))
                    )
                ));
        ");
    }

    public function down(): void
    {
        DB::connection('pgsql_migration')->unprepared("
            -- Revertir RLS de patient_medications
            DROP POLICY IF EXISTS patient_medications_update ON patient_medications;
            DROP POLICY IF EXISTS patient_medications_insert ON patient_medications;
            DROP POLICY IF EXISTS patient_medications_select ON patient_medications;
            ALTER TABLE patient_medications DISABLE ROW LEVEL SECURITY;

            -- Revertir RLS de patient_conditions
            DROP POLICY IF EXISTS patient_conditions_update ON patient_conditions;
            DROP POLICY IF EXISTS patient_conditions_insert ON patient_conditions;
            DROP POLICY IF EXISTS patient_conditions_select ON patient_conditions;
            ALTER TABLE patient_conditions DISABLE ROW LEVEL SECURITY;

            -- Restaurar policy original sin agent
            DROP POLICY IF EXISTS patient_profiles_select ON patient_profiles;
            CREATE POLICY patient_profiles_select ON patient_profiles
                FOR SELECT USING (
                    current_user = 'app_worker'
                    OR current_setting('app.current_user_role', true) = 'admin'
                    OR user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    OR (current_setting('app.current_user_role', true) = 'doctor' AND EXISTS (
                        SELECT 1 FROM appointments a
                        WHERE a.patient_id = patient_profiles.user_id
                          AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                          AND a.status IN ('confirmed', 'completed')
                    ))
                );

            -- Restaurar INSERT grants
            GRANT SELECT, INSERT ON audit_logs TO app_runtime;
            GRANT SELECT, INSERT ON audit_logs TO app_worker;

            -- Restaurar default
            ALTER TABLE audit_logs ALTER COLUMN actor_pg SET DEFAULT current_user;

            -- fn_audit_log sin search_path (restaurar original)
            CREATE OR REPLACE FUNCTION fn_audit_log() RETURNS TRIGGER AS \$fn\$
            DECLARE
                v_user_id uuid;
                v_actor_pg text;
                v_record_id uuid;
                v_old jsonb;
                v_new jsonb;
            BEGIN
                v_user_id := NULLIF(current_setting('app.current_user_id', true), '')::uuid;
                v_actor_pg := session_user;
                IF TG_OP = 'INSERT' THEN
                    v_record_id := NEW.id;
                    v_old := NULL;
                    v_new := to_jsonb(NEW);
                ELSIF TG_OP = 'UPDATE' THEN
                    IF to_jsonb(OLD) = to_jsonb(NEW) THEN RETURN NEW; END IF;
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
            \$fn\$ LANGUAGE plpgsql SECURITY DEFINER;

            GRANT EXECUTE ON FUNCTION fn_audit_log() TO PUBLIC;
        ");
    }
};
