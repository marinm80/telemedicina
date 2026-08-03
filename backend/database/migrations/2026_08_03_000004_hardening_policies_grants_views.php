<?php
declare(strict_types=1);
/**
 * ====================================================================
 * PLATAFORMA DE TELEMEDICINA
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 * ====================================================================
 *
 * HALLAZGO 13: Políticas estrechas, GRANT de columna, vista pública.
 *
 * Corrige 8 políticas RLS permisivas detectadas por la barrera R7b.
 * Crea vista v_doctor_directory con security_barrier.
 * Restringe GRANTs de UPDATE a columnas específicas.
 * REVOKE INSERT processed_stripe_events de app_runtime.
 * REVOKE SELECT schedule_blocks de app_runtime (re-grant columnas sin reason).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql_migration')->unprepared("
            -- ================================================================
            -- 1. VISTA PÚBLICA DEL DIRECTORIO MÉDICO
            -- ================================================================
            -- La vista EVADE el RLS de la tabla base a propósito: su WHERE ES
            -- la frontera de seguridad. Un paciente que necesita buscar médicos
            -- no puede leer users ni doctor_profiles directamente — usa esta vista.
            -- Con security_barrier=true el planificador no empuja predicados del
            -- usuario por debajo del filtro WHERE.
            -- ================================================================

            CREATE OR REPLACE VIEW v_doctor_directory
            WITH (security_barrier = true)
            AS SELECT
                u.id            AS user_id,
                u.name,
                u.last_name,
                u.timezone,
                dp.id           AS doctor_profile_id,
                dp.consultation_fee,
                dp.description,
                dp.years_experience,
                dp.university
            FROM users u
            JOIN doctor_profiles dp ON dp.user_id = u.id
            WHERE dp.status = 'approved'
              AND dp.deleted_at IS NULL
              AND u.is_active = true;

            GRANT SELECT ON v_doctor_directory TO app_runtime;
            GRANT SELECT ON v_doctor_directory TO app_worker;

            -- ================================================================
            -- 2. POLÍTICAS ESTRECHAS — una por una
            -- ================================================================

            -- 2.1 users_select: QUITAR OR true
            -- Antes: current_user='app_worker' OR admin OR agent OR true
            -- Ahora: self OR admin OR agent OR app_worker OR doctor/patient con cita
            DROP POLICY users_select ON users;
            CREATE POLICY users_select ON users
                FOR SELECT USING (
                    current_user = 'app_worker'
                    OR current_setting('app.current_user_role', true) = 'admin'
                    OR current_setting('app.current_user_role', true) = 'agent'
                    OR id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    OR (current_setting('app.current_user_role', true) = 'doctor' AND EXISTS (
                        SELECT 1 FROM appointments a
                        WHERE a.patient_id = users.id
                          AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                          AND a.status IN ('confirmed', 'completed', 'pending')
                    ))
                    OR (current_setting('app.current_user_role', true) = 'patient' AND EXISTS (
                        SELECT 1 FROM appointments a
                        WHERE a.doctor_id = users.id
                          AND a.patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                          AND a.status IN ('confirmed', 'completed', 'pending')
                    ))
                );

            -- 2.2 users_update: GRANT de columna
            -- Un usuario NO puede cambiar email (evade verificación), ni
            -- is_active (auto-activación), ni email_verified_at, ni deleted_at.
            REVOKE UPDATE ON users FROM app_runtime;
            GRANT UPDATE (name, last_name, password, timezone, remember_token, updated_at) ON users TO app_runtime;

            -- 2.3 doctor_profiles_select: QUITAR OR true
            -- Antes: app_worker OR true
            -- Ahora: approved OR self OR admin OR app_worker
            -- Perfiles pending/rejected (con license_number, rejection_reason) solo para dueño o admin.
            DROP POLICY doctor_profiles_select ON doctor_profiles;
            CREATE POLICY doctor_profiles_select ON doctor_profiles
                FOR SELECT USING (
                    current_user = 'app_worker'
                    OR current_setting('app.current_user_role', true) = 'admin'
                    OR user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    OR status = 'approved'
                );

            -- 2.4 doctor_profiles_update: GRANT de columna
            -- Un doctor NO puede cambiar status, approved_at, rejection_reason, license_number.
            REVOKE UPDATE ON doctor_profiles FROM app_runtime;
            GRANT UPDATE (description, consultation_fee, years_experience, university, updated_at) ON doctor_profiles TO app_runtime;

            -- 2.5 user_roles_select: QUITAR USING (true)
            -- Antes: USING (true)
            -- Ahora: self OR admin OR app_worker
            -- La validación de booking apuntará a doctor_profiles (no user_roles).
            DROP POLICY user_roles_select ON user_roles;
            CREATE POLICY user_roles_select ON user_roles
                FOR SELECT USING (
                    current_user = 'app_worker'
                    OR current_setting('app.current_user_role', true) = 'admin'
                    OR user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                );

            -- 2.6 user_permissions_select: QUITAR USING (true)
            -- Ningún flujo necesita leer permisos de otro usuario.
            DROP POLICY user_permissions_select ON user_permissions;
            CREATE POLICY user_permissions_select ON user_permissions
                FOR SELECT USING (
                    current_user = 'app_worker'
                    OR current_setting('app.current_user_role', true) = 'admin'
                    OR user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                );

            -- 2.7 processed_stripe_events: REVOKE INSERT FROM app_runtime
            -- El webhook corre como app_worker, no app_runtime.
            REVOKE INSERT ON processed_stripe_events FROM app_runtime;
            DROP POLICY processed_stripe_events_insert ON processed_stripe_events;
            CREATE POLICY processed_stripe_events_insert ON processed_stripe_events
                FOR INSERT WITH CHECK (
                    current_user = 'app_worker'
                    OR current_setting('app.current_user_role', true) = 'admin'
                );

            -- 2.8 schedules_select: QUITAR OR true
            -- La agenda de un médico pendiente no aparece en ningún listado.
            DROP POLICY schedules_select ON schedules;
            CREATE POLICY schedules_select ON schedules
                FOR SELECT USING (
                    current_user = 'app_worker'
                    OR current_setting('app.current_user_role', true) = 'admin'
                    OR EXISTS (
                        SELECT 1 FROM doctor_profiles dp
                        WHERE dp.id = schedules.doctor_profile_id
                          AND dp.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    )
                    OR EXISTS (
                        SELECT 1 FROM doctor_profiles dp
                        WHERE dp.id = schedules.doctor_profile_id
                          AND dp.status = 'approved'
                    )
                );

            -- 2.9 schedule_blocks_select: QUITAR OR true + GRANT de columna
            -- schedule_blocks.reason es sensible: motivos del bloqueo.
            -- La disponibilidad necesita el RANGO, no el MOTIVO.
            DROP POLICY schedule_blocks_select ON schedule_blocks;
            CREATE POLICY schedule_blocks_select ON schedule_blocks
                FOR SELECT USING (
                    current_user = 'app_worker'
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
            -- GRANT de columna: sin reason
            REVOKE SELECT ON schedule_blocks FROM app_runtime;
            GRANT SELECT (id, doctor_profile_id, blocked_date, franja, created_at, updated_at) ON schedule_blocks TO app_runtime;

            -- 2.10 doctor_specialties_select: QUITAR USING (true)
            -- Solo especialidades de médicos aprobados son públicas.
            DROP POLICY doctor_specialties_select ON doctor_specialties;
            CREATE POLICY doctor_specialties_select ON doctor_specialties
                FOR SELECT USING (
                    current_user = 'app_worker'
                    OR current_setting('app.current_user_role', true) = 'admin'
                    OR EXISTS (
                        SELECT 1 FROM doctor_profiles dp
                        WHERE dp.id = doctor_specialties.doctor_profile_id
                          AND dp.user_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    )
                    OR EXISTS (
                        SELECT 1 FROM doctor_profiles dp
                        WHERE dp.id = doctor_specialties.doctor_profile_id
                          AND dp.status = 'approved'
                    )
                );
        ");
    }

    public function down(): void
    {
        DB::connection('pgsql_migration')->unprepared("
            -- Restaurar políticas permisivas de la migración 000003

            -- users_select: restaurar OR true
            DROP POLICY IF EXISTS users_select ON users;
            CREATE POLICY users_select ON users
                FOR SELECT USING (
                    current_user = 'app_worker'
                    OR current_setting('app.current_user_role', true) = 'admin'
                    OR current_setting('app.current_user_role', true) = 'agent'
                    OR true
                );

            -- users_update: restaurar GRANT blanket
            REVOKE UPDATE (name, last_name, password, timezone, remember_token, updated_at) ON users FROM app_runtime;
            GRANT UPDATE ON users TO app_runtime;

            -- doctor_profiles_select: restaurar OR true
            DROP POLICY IF EXISTS doctor_profiles_select ON doctor_profiles;
            CREATE POLICY doctor_profiles_select ON doctor_profiles
                FOR SELECT USING (
                    current_user = 'app_worker'
                    OR true
                );

            -- doctor_profiles_update: restaurar GRANT blanket
            REVOKE UPDATE (description, consultation_fee, years_experience, university, updated_at) ON doctor_profiles FROM app_runtime;
            GRANT UPDATE ON doctor_profiles TO app_runtime;

            -- user_roles_select: restaurar USING (true)
            DROP POLICY IF EXISTS user_roles_select ON user_roles;
            CREATE POLICY user_roles_select ON user_roles
                FOR SELECT USING (true);

            -- user_permissions_select: restaurar USING (true)
            DROP POLICY IF EXISTS user_permissions_select ON user_permissions;
            CREATE POLICY user_permissions_select ON user_permissions
                FOR SELECT USING (true);

            -- processed_stripe_events: restaurar INSERT
            GRANT INSERT ON processed_stripe_events TO app_runtime;
            DROP POLICY IF EXISTS processed_stripe_events_insert ON processed_stripe_events;
            CREATE POLICY processed_stripe_events_insert ON processed_stripe_events
                FOR INSERT WITH CHECK (
                    current_user = 'app_worker'
                    OR current_setting('app.current_user_role', true) = 'admin'
                    OR true
                );

            -- schedules_select: restaurar OR true
            DROP POLICY IF EXISTS schedules_select ON schedules;
            CREATE POLICY schedules_select ON schedules
                FOR SELECT USING (
                    current_user = 'app_worker'
                    OR true
                );

            -- schedule_blocks_select: restaurar OR true + GRANT blanket
            DROP POLICY IF EXISTS schedule_blocks_select ON schedule_blocks;
            CREATE POLICY schedule_blocks_select ON schedule_blocks
                FOR SELECT USING (
                    current_user = 'app_worker'
                    OR true
                );
            REVOKE SELECT (id, doctor_profile_id, blocked_date, franja, created_at, updated_at) ON schedule_blocks FROM app_runtime;
            GRANT SELECT ON schedule_blocks TO app_runtime;

            -- doctor_specialties_select: restaurar USING (true)
            DROP POLICY IF EXISTS doctor_specialties_select ON doctor_specialties;
            CREATE POLICY doctor_specialties_select ON doctor_specialties
                FOR SELECT USING (true);

            -- Vista
            DROP VIEW IF EXISTS v_doctor_directory;
        ");
    }
};
