<?php
/**
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 *
 * Fix P1: Corrige GUC names en RLS policies de referrals
 *          (app.user_id → app.current_user_id, app.user_role → app.current_user_role)
 * Fix P2: Agrega specialty_id FK a specialties para enlazar referidos al catálogo
 * Fix P3: Agrega appointment_id para rastrear la cita del especialista (ciclo accepted→completed)
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql_admin')->statement("
            -- ═══════════════════════════════════════════════════
            -- FIX P1: Corregir GUC names en RLS policies
            -- ═══════════════════════════════════════════════════

            -- Drop las políticas rotas
            DROP POLICY IF EXISTS referrals_patient_policy ON referrals;
            DROP POLICY IF EXISTS referrals_doctor_policy ON referrals;
            DROP POLICY IF EXISTS referrals_admin_policy ON referrals;

            -- Recrear con los nombres correctos (app.current_user_id / app.current_user_role)
            CREATE POLICY referrals_patient_policy ON referrals
                FOR SELECT
                TO app_runtime
                USING (patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid);

            CREATE POLICY referrals_doctor_policy ON referrals
                FOR ALL
                TO app_runtime
                USING (referring_doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid);

            CREATE POLICY referrals_admin_policy ON referrals
                FOR ALL
                TO app_runtime
                USING ((current_setting('app.current_user_role', true)) = 'admin');

            -- ═══════════════════════════════════════════════════
            -- FIX P2: Agregar specialty_id como FK a specialties
            -- ═══════════════════════════════════════════════════

            ALTER TABLE referrals
                ADD COLUMN specialty_id uuid REFERENCES specialties(id) ON DELETE SET NULL;

            CREATE INDEX idx_referrals_specialty_id ON referrals(specialty_id);

            -- ═══════════════════════════════════════════════════
            -- FIX P3: Agregar appointment_id para ciclo de vida
            -- consultation_id = consulta del médico QUE REFIERE (origen)
            -- appointment_id  = cita que el paciente AGENDÓ con el especialista (destino)
            -- ═══════════════════════════════════════════════════

            ALTER TABLE referrals
                ADD COLUMN appointment_id uuid REFERENCES appointments(id) ON DELETE SET NULL;

            CREATE INDEX idx_referrals_appointment_id ON referrals(appointment_id);
        ");
    }

    public function down(): void
    {
        DB::connection('pgsql_admin')->statement("
            -- Revert columns
            DROP INDEX IF EXISTS idx_referrals_appointment_id;
            ALTER TABLE referrals DROP COLUMN IF EXISTS appointment_id;
            DROP INDEX IF EXISTS idx_referrals_specialty_id;
            ALTER TABLE referrals DROP COLUMN IF EXISTS specialty_id;

            -- Revert to old (broken) policies
            DROP POLICY IF EXISTS referrals_patient_policy ON referrals;
            DROP POLICY IF EXISTS referrals_doctor_policy ON referrals;
            DROP POLICY IF EXISTS referrals_admin_policy ON referrals;

            CREATE POLICY referrals_patient_policy ON referrals
                FOR SELECT TO app_runtime
                USING (patient_id = (current_setting('app.user_id', true))::uuid);

            CREATE POLICY referrals_doctor_policy ON referrals
                FOR ALL TO app_runtime
                USING (referring_doctor_id = (current_setting('app.user_id', true))::uuid);

            CREATE POLICY referrals_admin_policy ON referrals
                FOR ALL TO app_runtime
                USING ((current_setting('app.user_role', true)) = 'admin');
        ");
    }
};
