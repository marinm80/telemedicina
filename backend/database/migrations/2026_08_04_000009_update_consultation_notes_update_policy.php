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
            DROP POLICY IF EXISTS consultation_notes_update ON consultation_notes;
            CREATE POLICY consultation_notes_update ON consultation_notes
                FOR UPDATE USING (
                    current_user = 'app_worker' OR
                    current_setting('app.current_user_role', true) = 'admin' OR
                    EXISTS (
                        SELECT 1 FROM consultations c JOIN appointments a ON a.id = c.appointment_id
                        WHERE c.id = consultation_notes.consultation_id AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    ) OR
                    EXISTS (
                        SELECT 1 FROM consultations c JOIN appointments a ON a.id = c.appointment_id
                        WHERE c.id = consultation_notes.consultation_id AND a.patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    )
                ) WITH CHECK (
                    current_user = 'app_worker' OR
                    current_setting('app.current_user_role', true) = 'admin' OR
                    (status = 'draft') OR
                    (status = 'signed')
                );
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("
            DROP POLICY IF EXISTS consultation_notes_update ON consultation_notes;
            CREATE POLICY consultation_notes_update ON consultation_notes
                FOR UPDATE USING (
                    status = 'draft' AND EXISTS (
                        SELECT 1 FROM consultations c JOIN appointments a ON a.id = c.appointment_id
                        WHERE c.id = consultation_notes.consultation_id AND a.doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                    )
                ) WITH CHECK (
                    (status = 'draft') OR (status = 'signed' AND signed_by = NULLIF(current_setting('app.current_user_id', true), '')::uuid)
                );
        ");
    }
};
