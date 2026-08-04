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
            GRANT SELECT, INSERT ON processed_stripe_events TO app_runtime;

            DROP POLICY IF EXISTS processed_stripe_events_insert ON processed_stripe_events;
            CREATE POLICY processed_stripe_events_insert ON processed_stripe_events
                FOR INSERT WITH CHECK (
                    current_user = 'app_worker' OR
                    current_setting('app.current_user_role', true) = 'admin' OR
                    current_setting('app.current_user_role', true) = 'system'
                );
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("
            REVOKE INSERT ON processed_stripe_events FROM app_runtime;
            DROP POLICY IF EXISTS processed_stripe_events_insert ON processed_stripe_events;
            CREATE POLICY processed_stripe_events_insert ON processed_stripe_events
                FOR INSERT WITH CHECK (
                    current_user = 'app_worker' OR
                    current_setting('app.current_user_role', true) = 'admin'
                );
        ");
    }
};
