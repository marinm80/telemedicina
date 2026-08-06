<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql_admin')->unprepared("
            CREATE TABLE IF NOT EXISTS prescriptions (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                consultation_id uuid NULL REFERENCES consultations(id) ON DELETE SET NULL,
                doctor_id uuid NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
                patient_id uuid NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
                fecha date NOT NULL DEFAULT CURRENT_DATE,
                medicamentos jsonb NOT NULL DEFAULT '[]'::jsonb,
                indicaciones text NULL,
                notas text NULL,
                status varchar(20) NOT NULL DEFAULT 'active',
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                deleted_at timestamptz NULL,
                CONSTRAINT prescriptions_status_valido CHECK (status IN ('active', 'cancelled'))
            );

            CREATE INDEX IF NOT EXISTS idx_prescriptions_doctor ON prescriptions(doctor_id);
            CREATE INDEX IF NOT EXISTS idx_prescriptions_patient ON prescriptions(patient_id);
            CREATE INDEX IF NOT EXISTS idx_prescriptions_consultation ON prescriptions(consultation_id);

            -- RLS
            ALTER TABLE prescriptions ENABLE ROW LEVEL SECURITY;
            ALTER TABLE prescriptions FORCE ROW LEVEL SECURITY;

            DROP POLICY IF EXISTS prescriptions_policy ON prescriptions;
            CREATE POLICY prescriptions_policy ON prescriptions
                USING (
                    current_setting('app.current_user_role', true) = 'admin'
                    OR doctor_id = current_setting('app.current_user_id', true)::uuid
                    OR patient_id = current_setting('app.current_user_id', true)::uuid
                );

            GRANT SELECT, INSERT, UPDATE ON prescriptions TO app_runtime;
        ");
    }

    public function down(): void
    {
        DB::connection('pgsql_admin')->unprepared('DROP TABLE IF EXISTS prescriptions CASCADE;');
    }
};
