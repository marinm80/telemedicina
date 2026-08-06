<?php
/**
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
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
        DB::connection('pgsql_admin')->unprepared("
            CREATE TABLE referrals (
                id                  uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                consultation_id     uuid NOT NULL REFERENCES consultations(id) ON DELETE RESTRICT,
                referring_doctor_id uuid NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
                patient_id          uuid NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
                specialty_name      varchar(100) NOT NULL,
                referred_doctor_id  uuid REFERENCES users(id) ON DELETE SET NULL,
                reason              text NOT NULL,
                priority            varchar(20) NOT NULL DEFAULT 'normal' CHECK (priority IN ('normal', 'urgente')),
                status              varchar(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'accepted', 'completed', 'cancelled')),
                notes               text,
                created_at          timestamptz NOT NULL DEFAULT now(),
                updated_at          timestamptz NOT NULL DEFAULT now()
            );

            CREATE INDEX idx_referrals_patient_id ON referrals(patient_id);
            CREATE INDEX idx_referrals_consultation_id ON referrals(consultation_id);
            CREATE INDEX idx_referrals_referring_doctor_id ON referrals(referring_doctor_id);

            GRANT SELECT, INSERT, UPDATE ON referrals TO app_runtime;

            ALTER TABLE referrals ENABLE ROW LEVEL SECURITY;

            CREATE POLICY referrals_patient_policy ON referrals
                FOR SELECT
                TO app_runtime
                USING (patient_id = (current_setting('app.user_id', true))::uuid);

            CREATE POLICY referrals_doctor_policy ON referrals
                FOR ALL
                TO app_runtime
                USING (referring_doctor_id = (current_setting('app.user_id', true))::uuid);

            CREATE POLICY referrals_admin_policy ON referrals
                FOR ALL
                TO app_runtime
                USING ((current_setting('app.user_role', true)) = 'admin');
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection('pgsql_admin')->statement("
            DROP TABLE IF EXISTS referrals CASCADE;
        ");
    }
};
