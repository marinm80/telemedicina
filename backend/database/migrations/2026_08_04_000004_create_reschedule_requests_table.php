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
            CREATE TABLE reschedule_requests (
                id                  uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                appointment_id      uuid NOT NULL REFERENCES appointments(id) ON DELETE RESTRICT,
                doctor_id           uuid NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
                requested_by        uuid NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
                requested_franja    tstzrange NOT NULL,
                reason              text NULL,
                status              varchar(20) NOT NULL DEFAULT 'pending',
                rejection_reason    text NULL,
                resolved_by         uuid NULL REFERENCES users(id) ON DELETE RESTRICT,
                resolved_at         timestamptz NULL,
                created_at          timestamptz NOT NULL DEFAULT now(),
                updated_at          timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT reschedule_requests_status_valido CHECK (status IN ('pending', 'approved', 'rejected')),
                CONSTRAINT reschedule_requests_sin_solapamiento
                    EXCLUDE USING gist (doctor_id WITH =, requested_franja WITH &&)
                    WHERE (status = 'pending')
            );

            CREATE UNIQUE INDEX reschedule_requests_unique_pending ON reschedule_requests (appointment_id) WHERE (status = 'pending');

            ALTER TABLE reschedule_requests ENABLE ROW LEVEL SECURITY;
            ALTER TABLE reschedule_requests FORCE ROW LEVEL SECURITY;

            CREATE POLICY reschedule_requests_select ON reschedule_requests FOR SELECT USING (
                current_setting('app.current_user_role', true) = 'admin' OR
                requested_by = NULLIF(current_setting('app.current_user_id', true), '')::uuid OR
                doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid OR
                current_setting('app.current_user_role', true) = 'agent'
            );

            CREATE POLICY reschedule_requests_insert ON reschedule_requests FOR INSERT WITH CHECK (
                current_setting('app.current_user_role', true) = 'admin' OR
                requested_by = NULLIF(current_setting('app.current_user_id', true), '')::uuid OR
                current_setting('app.current_user_role', true) = 'agent'
            );

            CREATE POLICY reschedule_requests_update ON reschedule_requests FOR UPDATE USING (
                current_setting('app.current_user_role', true) = 'admin' OR
                doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
            );

            GRANT SELECT, INSERT, UPDATE ON reschedule_requests TO app_runtime;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("
            DROP TABLE IF EXISTS reschedule_requests CASCADE;
        ");
    }
};
