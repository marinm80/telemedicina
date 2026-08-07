<?php
/**
 * AUTHOR: Rafael Marín · PORTFOLIO: https://rafaelmarin.dev
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql_admin')->unprepared("
            ALTER TABLE doctor_profiles ADD COLUMN photo_path varchar(255) NULL;

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
                dp.university,
                dp.photo_path
            FROM users u
            JOIN doctor_profiles dp ON dp.user_id = u.id
            WHERE dp.status = 'approved'
              AND dp.deleted_at IS NULL
              AND u.is_active = true;
        ");
    }

    public function down(): void
    {
        DB::connection('pgsql_admin')->unprepared("
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

            ALTER TABLE doctor_profiles DROP COLUMN IF EXISTS photo_path;
        ");
    }
};
