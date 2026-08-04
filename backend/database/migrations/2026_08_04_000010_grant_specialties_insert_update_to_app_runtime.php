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
        DB::connection('pgsql_migration')->unprepared("
            GRANT SELECT, INSERT, UPDATE ON specialties TO app_runtime;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection('pgsql_migration')->unprepared("
            REVOKE INSERT, UPDATE ON specialties FROM app_runtime;
        ");
    }
};
