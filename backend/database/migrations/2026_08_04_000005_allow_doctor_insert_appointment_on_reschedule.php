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
            DROP POLICY IF EXISTS appointments_insert ON appointments;
            CREATE POLICY appointments_insert ON appointments
                FOR INSERT WITH CHECK (
                    current_setting('app.current_user_role', true) = 'admin' OR
                    current_setting('app.current_user_role', true) = 'agent' OR
                    patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid OR
                    doctor_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                );
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("
            DROP POLICY IF EXISTS appointments_insert ON appointments;
            CREATE POLICY appointments_insert ON appointments
                FOR INSERT WITH CHECK (
                    current_setting('app.current_user_role', true) = 'admin' OR
                    current_setting('app.current_user_role', true) = 'agent' OR
                    patient_id = NULLIF(current_setting('app.current_user_id', true), '')::uuid
                );
        ");
    }
};
