<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * Hallazgo 12: habilitar RLS en las 11 tablas que tenían GRANT de escritura sin él.
 * Hallazgo 11: normalizar enum 'firmada' → 'signed' en consultation_notes y policies.
 *
 * Grupos:
 *   1. user_roles, user_permissions, audit_logs (escalada / filtración)
 *   2. users, doctor_profiles, payments, commissions
 *   3. schedules, schedule_blocks (agenda)
 *   4. processed_stripe_events, doctor_specialties
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql_migration')->unprepared("
            -- ================================================================
            -- GRUPO 1: user_roles, user_permissions, audit_logs
            -- Las que anulan todo lo demás si están sin RLS.
            -- ================================================================

            -- audit_logs: SELECT solo admin + propio user_id
            ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY;
            CREATE POLICY audit_logs_select ON audit_logs
                FOR SELECT USING (
                    current_setting('app.current_user_role', true) = 'admin'
                    OR user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                );

            -- user_roles: SELECT propio + admin; escritura SOLO admin, nunca a sí mismo
            ALTER TABLE user_roles ENABLE ROW LEVEL SECURITY;
            CREATE POLICY user_roles_select ON user_roles
                FOR SELECT USING (true);
            CREATE POLICY user_roles_insert ON user_roles
                FOR INSERT WITH CHECK (
                    current_setting('app.current_user_role', true) = 'admin'
                    AND user_id != NULLIF(current_setting('app.current_user_id', true), '')::uuid
                );
            CREATE POLICY user_roles_delete ON user_roles
                FOR DELETE USING (
                    current_setting('app.current_user_role', true) = 'admin'
                    AND user_id != NULLIF(current_setting('app.current_user_id', true), '')::uuid
                );

            -- user_permissions: misma estructura que user_roles
            ALTER TABLE user_permissions ENABLE ROW LEVEL SECURITY;
            CREATE POLICY user_permissions_select ON user_permissions
                FOR SELECT USING (true);
            CREATE POLICY user_permissions_insert ON user_permissions
                FOR INSERT WITH CHECK (
                    current_setting('app.current_user_role', true) = 'admin'
                    AND user_id != NULLIF(current_setting('app.current_user_id', true), '')::uuid
                );
            CREATE POLICY user_permissions_delete ON user_permissions
                FOR DELETE USING (
                    current_setting('app.current_user_role', true) = 'admin'
                    AND user_id != NULLIF(current_setting('app.current_user_id', true), '')::uuid
                );

            -- ================================================================
            -- GRUPO 2: users, doctor_profiles, payments, commissions
            -- ================================================================

            -- users: lectura pública (buscar médicos), escritura propia o admin
            ALTER TABLE users ENABLE ROW LEVEL SECURITY;
            CREATE POLICY users_select ON users
                FOR SELECT USING (
                    current_user = 'app_worker'
                    OR current_setting('app.current_user_role', true) = 'admin'
                    OR current_setting('app.current_user_role', true) = 'agent'
                    OR true
                );
            CREATE POLICY users_insert ON users
                FOR INSERT WITH CHECK (
                    current_setting('app.current_user_role', true) = 'admin'
                    OR current_setting('app.current_user_role', true) = 'agent'
                    OR NULLIF(current_setting('app.current_user_id', true), '') IS NULL
                );
            CREATE POLICY users_update ON users
                FOR UPDATE USING (
                    current_user = 'app_worker'
                    OR current_setting('app.current_user_role', true) = 'admin'
                    OR id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                );

            -- doctor_profiles: lectura pública, escritura propia, aprobación solo admin
            ALTER TABLE doctor_profiles ENABLE ROW LEVEL SECURITY;
            CREATE POLICY doctor_profiles_select ON doctor_profiles
                FOR SELECT USING (
                    current_user = 'app_worker'
                    OR true
                );
            CREATE POLICY doctor_profiles_insert ON doctor_profiles
                FOR INSERT WITH CHECK (
                    user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    OR current_setting('app.current_user_role', true) = 'admin'
                );
            CREATE POLICY doctor_profiles_update ON doctor_profiles
                FOR UPDATE USING (
                    current_user = 'app_worker'
                    OR current_setting('app.current_user_role', true) = 'admin'
                    OR user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                );

            -- payments: lectura por participantes, escritura por sistema
            ALTER TABLE payments ENABLE ROW LEVEL SECURITY;
            CREATE POLICY payments_select ON payments
                FOR SELECT USING (
                    current_user = 'app_worker'
                    OR current_setting('app.current_user_role', true) = 'admin'
                    OR EXISTS (
                        SELECT 1 FROM appointments a
                        WHERE a.id = payments.appointment_id
                        AND (a.patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                             OR a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid)
                    )
                );
            CREATE POLICY payments_insert ON payments
                FOR INSERT WITH CHECK (
                    current_setting('app.current_user_role', true) = 'admin'
                    OR EXISTS (
                        SELECT 1 FROM appointments a
                        WHERE a.id = appointment_id
                        AND a.patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    )
                );
            CREATE POLICY payments_update ON payments
                FOR UPDATE USING (
                    current_user = 'app_worker'
                    OR current_setting('app.current_user_role', true) = 'admin'
                );

            -- commissions: lectura admin + médico propio, escritura solo admin/worker
            ALTER TABLE commissions ENABLE ROW LEVEL SECURITY;
            CREATE POLICY commissions_select ON commissions
                FOR SELECT USING (
                    current_user = 'app_worker'
                    OR current_setting('app.current_user_role', true) = 'admin'
                    OR EXISTS (
                        SELECT 1 FROM payments p
                        JOIN appointments a ON a.id = p.appointment_id
                        WHERE p.id = commissions.payment_id
                        AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    )
                );
            CREATE POLICY commissions_insert ON commissions
                FOR INSERT WITH CHECK (
                    current_user = 'app_worker'
                    OR current_setting('app.current_user_role', true) = 'admin'
                );
            CREATE POLICY commissions_update ON commissions
                FOR UPDATE USING (
                    current_user = 'app_worker'
                    OR current_setting('app.current_user_role', true) = 'admin'
                );

            -- ================================================================
            -- GRUPO 3: schedules, schedule_blocks (agenda — RF-08 pending)
            -- ================================================================

            -- schedules: lectura pública (disponibilidad), escritura solo doctor propietario
            ALTER TABLE schedules ENABLE ROW LEVEL SECURITY;
            CREATE POLICY schedules_select ON schedules
                FOR SELECT USING (
                    current_user = 'app_worker'
                    OR true
                );
            CREATE POLICY schedules_insert ON schedules
                FOR INSERT WITH CHECK (
                    current_setting('app.current_user_role', true) = 'admin'
                    OR EXISTS (
                        SELECT 1 FROM doctor_profiles dp
                        WHERE dp.id = doctor_profile_id
                        AND dp.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    )
                );
            CREATE POLICY schedules_update ON schedules
                FOR UPDATE USING (
                    current_setting('app.current_user_role', true) = 'admin'
                    OR EXISTS (
                        SELECT 1 FROM doctor_profiles dp
                        WHERE dp.id = doctor_profile_id
                        AND dp.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    )
                );
            CREATE POLICY schedules_delete ON schedules
                FOR DELETE USING (
                    current_setting('app.current_user_role', true) = 'admin'
                    OR EXISTS (
                        SELECT 1 FROM doctor_profiles dp
                        WHERE dp.id = doctor_profile_id
                        AND dp.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    )
                );

            -- schedule_blocks: misma estructura que schedules
            ALTER TABLE schedule_blocks ENABLE ROW LEVEL SECURITY;
            CREATE POLICY schedule_blocks_select ON schedule_blocks
                FOR SELECT USING (
                    current_user = 'app_worker'
                    OR true
                );
            CREATE POLICY schedule_blocks_insert ON schedule_blocks
                FOR INSERT WITH CHECK (
                    current_setting('app.current_user_role', true) = 'admin'
                    OR EXISTS (
                        SELECT 1 FROM doctor_profiles dp
                        WHERE dp.id = doctor_profile_id
                        AND dp.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    )
                );
            CREATE POLICY schedule_blocks_update ON schedule_blocks
                FOR UPDATE USING (
                    current_setting('app.current_user_role', true) = 'admin'
                    OR EXISTS (
                        SELECT 1 FROM doctor_profiles dp
                        WHERE dp.id = doctor_profile_id
                        AND dp.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    )
                );
            CREATE POLICY schedule_blocks_delete ON schedule_blocks
                FOR DELETE USING (
                    current_setting('app.current_user_role', true) = 'admin'
                    OR EXISTS (
                        SELECT 1 FROM doctor_profiles dp
                        WHERE dp.id = doctor_profile_id
                        AND dp.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    )
                );

            -- ================================================================
            -- GRUPO 4: processed_stripe_events, doctor_specialties
            -- ================================================================

            -- processed_stripe_events: solo lectura/escritura de sistema (admin/worker)
            ALTER TABLE processed_stripe_events ENABLE ROW LEVEL SECURITY;
            CREATE POLICY processed_stripe_events_select ON processed_stripe_events
                FOR SELECT USING (
                    current_user = 'app_worker'
                    OR current_setting('app.current_user_role', true) = 'admin'
                );
            CREATE POLICY processed_stripe_events_insert ON processed_stripe_events
                FOR INSERT WITH CHECK (
                    current_user = 'app_worker'
                    OR current_setting('app.current_user_role', true) = 'admin'
                    OR true
                );

            -- doctor_specialties: lectura pública, escritura doctor propio o admin
            ALTER TABLE doctor_specialties ENABLE ROW LEVEL SECURITY;
            CREATE POLICY doctor_specialties_select ON doctor_specialties
                FOR SELECT USING (true);
            CREATE POLICY doctor_specialties_insert ON doctor_specialties
                FOR INSERT WITH CHECK (
                    current_setting('app.current_user_role', true) = 'admin'
                    OR EXISTS (
                        SELECT 1 FROM doctor_profiles dp
                        WHERE dp.id = doctor_profile_id
                        AND dp.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    )
                );
            CREATE POLICY doctor_specialties_delete ON doctor_specialties
                FOR DELETE USING (
                    current_setting('app.current_user_role', true) = 'admin'
                    OR EXISTS (
                        SELECT 1 FROM doctor_profiles dp
                        WHERE dp.id = doctor_profile_id
                        AND dp.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    )
                );

            -- ================================================================
            -- HALLAZGO 11: normalizar 'firmada' → 'signed'
            -- ================================================================

            -- Actualizar datos existentes (cero filas en producción, pero correcto)
            UPDATE consultation_notes SET status = 'signed' WHERE status = 'firmada';

            -- Reemplazar CHECK constraint
            ALTER TABLE consultation_notes DROP CONSTRAINT notes_status_valido;
            ALTER TABLE consultation_notes ADD CONSTRAINT notes_status_valido
                CHECK (status IN ('draft', 'signed'));

            -- Reemplazar políticas RLS que referencian 'firmada'
            DROP POLICY IF EXISTS consultation_notes_select ON consultation_notes;
            CREATE POLICY consultation_notes_select ON consultation_notes
                FOR SELECT USING (
                    current_user = 'app_worker'
                    OR (current_setting('app.current_user_role', true) = 'doctor' AND EXISTS (
                        SELECT 1 FROM consultations c
                        JOIN appointments a ON a.id = c.appointment_id
                        WHERE c.id = consultation_notes.consultation_id
                        AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    ))
                    OR (current_setting('app.current_user_role', true) = 'patient'
                        AND status = 'signed'
                        AND EXISTS (
                            SELECT 1 FROM consultations c
                            JOIN appointments a ON a.id = c.appointment_id
                            WHERE c.id = consultation_notes.consultation_id
                            AND a.patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                        ))
                );

            DROP POLICY IF EXISTS consultation_notes_update ON consultation_notes;
            CREATE POLICY consultation_notes_update ON consultation_notes
                FOR UPDATE USING (
                    status = 'draft' AND EXISTS (
                        SELECT 1 FROM consultations c
                        JOIN appointments a ON a.id = c.appointment_id
                        WHERE c.id = consultation_notes.consultation_id
                        AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    )
                ) WITH CHECK (
                    (status = 'draft')
                    OR (status = 'signed' AND signed_by = NULLIF(current_setting('app.current_user_id', true), '')::uuid)
                );

            -- note_amendments: referencian 'firmada'
            DROP POLICY IF EXISTS note_amendments_select ON note_amendments;
            CREATE POLICY note_amendments_select ON note_amendments
                FOR SELECT USING (
                    current_user = 'app_worker'
                    OR EXISTS (
                        SELECT 1 FROM consultation_notes n
                        JOIN consultations c ON c.id = n.consultation_id
                        JOIN appointments a ON a.id = c.appointment_id
                        WHERE n.id = consultation_note_id
                        AND (
                            (current_setting('app.current_user_role', true) = 'doctor'
                             AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid)
                            OR (current_setting('app.current_user_role', true) = 'patient'
                                AND a.patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                                AND n.status = 'signed')
                        )
                    )
                );

            DROP POLICY IF EXISTS note_amendments_insert ON note_amendments;
            CREATE POLICY note_amendments_insert ON note_amendments
                FOR INSERT WITH CHECK (
                    author_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    AND EXISTS (
                        SELECT 1 FROM consultation_notes n
                        JOIN consultations c ON c.id = n.consultation_id
                        JOIN appointments a ON a.id = c.appointment_id
                        WHERE n.id = consultation_note_id
                        AND n.status = 'signed'
                        AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    )
                );
        ");
    }

    public function down(): void
    {
        DB::connection('pgsql_migration')->unprepared("
            -- Revertir enum
            UPDATE consultation_notes SET status = 'firmada' WHERE status = 'signed';
            ALTER TABLE consultation_notes DROP CONSTRAINT notes_status_valido;
            ALTER TABLE consultation_notes ADD CONSTRAINT notes_status_valido
                CHECK (status IN ('draft', 'firmada'));

            -- Restaurar policies originales de consultation_notes
            DROP POLICY IF EXISTS consultation_notes_select ON consultation_notes;
            CREATE POLICY consultation_notes_select ON consultation_notes
                FOR SELECT USING (current_user = 'app_worker' OR (current_setting('app.current_user_role', true) = 'doctor' AND EXISTS (SELECT 1 FROM consultations c JOIN appointments a ON a.id = c.appointment_id WHERE c.id = consultation_notes.consultation_id AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid)) OR (current_setting('app.current_user_role', true) = 'patient' AND status = 'firmada' AND EXISTS (SELECT 1 FROM consultations c JOIN appointments a ON a.id = c.appointment_id WHERE c.id = consultation_notes.consultation_id AND a.patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid)));
            DROP POLICY IF EXISTS consultation_notes_update ON consultation_notes;
            CREATE POLICY consultation_notes_update ON consultation_notes
                FOR UPDATE USING (status = 'draft' AND EXISTS (SELECT 1 FROM consultations c JOIN appointments a ON a.id = c.appointment_id WHERE c.id = consultation_notes.consultation_id AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid)) WITH CHECK ((status = 'draft') OR (status = 'firmada' AND signed_by = NULLIF(current_setting('app.current_user_id', true), '')::uuid));

            -- Restaurar policies originales de note_amendments
            DROP POLICY IF EXISTS note_amendments_select ON note_amendments;
            CREATE POLICY note_amendments_select ON note_amendments
                FOR SELECT USING (current_user = 'app_worker' OR EXISTS (SELECT 1 FROM consultation_notes n JOIN consultations c ON c.id = n.consultation_id JOIN appointments a ON a.id = c.appointment_id WHERE n.id = consultation_note_id AND ((current_setting('app.current_user_role', true) = 'doctor' AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid) OR (current_setting('app.current_user_role', true) = 'patient' AND a.patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid AND n.status = 'firmada'))));
            DROP POLICY IF EXISTS note_amendments_insert ON note_amendments;
            CREATE POLICY note_amendments_insert ON note_amendments
                FOR INSERT WITH CHECK (author_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid AND EXISTS (SELECT 1 FROM consultation_notes n JOIN consultations c ON c.id = n.consultation_id JOIN appointments a ON a.id = c.appointment_id WHERE n.id = consultation_note_id AND n.status = 'firmada' AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid));

            -- Deshabilitar RLS de las 11 tablas
            DROP POLICY IF EXISTS doctor_specialties_delete ON doctor_specialties;
            DROP POLICY IF EXISTS doctor_specialties_insert ON doctor_specialties;
            DROP POLICY IF EXISTS doctor_specialties_select ON doctor_specialties;
            ALTER TABLE doctor_specialties DISABLE ROW LEVEL SECURITY;

            DROP POLICY IF EXISTS processed_stripe_events_insert ON processed_stripe_events;
            DROP POLICY IF EXISTS processed_stripe_events_select ON processed_stripe_events;
            ALTER TABLE processed_stripe_events DISABLE ROW LEVEL SECURITY;

            DROP POLICY IF EXISTS schedule_blocks_delete ON schedule_blocks;
            DROP POLICY IF EXISTS schedule_blocks_update ON schedule_blocks;
            DROP POLICY IF EXISTS schedule_blocks_insert ON schedule_blocks;
            DROP POLICY IF EXISTS schedule_blocks_select ON schedule_blocks;
            ALTER TABLE schedule_blocks DISABLE ROW LEVEL SECURITY;

            DROP POLICY IF EXISTS schedules_delete ON schedules;
            DROP POLICY IF EXISTS schedules_update ON schedules;
            DROP POLICY IF EXISTS schedules_insert ON schedules;
            DROP POLICY IF EXISTS schedules_select ON schedules;
            ALTER TABLE schedules DISABLE ROW LEVEL SECURITY;

            DROP POLICY IF EXISTS commissions_update ON commissions;
            DROP POLICY IF EXISTS commissions_insert ON commissions;
            DROP POLICY IF EXISTS commissions_select ON commissions;
            ALTER TABLE commissions DISABLE ROW LEVEL SECURITY;

            DROP POLICY IF EXISTS payments_update ON payments;
            DROP POLICY IF EXISTS payments_insert ON payments;
            DROP POLICY IF EXISTS payments_select ON payments;
            ALTER TABLE payments DISABLE ROW LEVEL SECURITY;

            DROP POLICY IF EXISTS doctor_profiles_update ON doctor_profiles;
            DROP POLICY IF EXISTS doctor_profiles_insert ON doctor_profiles;
            DROP POLICY IF EXISTS doctor_profiles_select ON doctor_profiles;
            ALTER TABLE doctor_profiles DISABLE ROW LEVEL SECURITY;

            DROP POLICY IF EXISTS users_update ON users;
            DROP POLICY IF EXISTS users_insert ON users;
            DROP POLICY IF EXISTS users_select ON users;
            ALTER TABLE users DISABLE ROW LEVEL SECURITY;

            DROP POLICY IF EXISTS user_permissions_delete ON user_permissions;
            DROP POLICY IF EXISTS user_permissions_insert ON user_permissions;
            DROP POLICY IF EXISTS user_permissions_select ON user_permissions;
            ALTER TABLE user_permissions DISABLE ROW LEVEL SECURITY;

            DROP POLICY IF EXISTS user_roles_delete ON user_roles;
            DROP POLICY IF EXISTS user_roles_insert ON user_roles;
            DROP POLICY IF EXISTS user_roles_select ON user_roles;
            ALTER TABLE user_roles DISABLE ROW LEVEL SECURITY;

            DROP POLICY IF EXISTS audit_logs_select ON audit_logs;
            ALTER TABLE audit_logs DISABLE ROW LEVEL SECURITY;
        ");
    }
};
