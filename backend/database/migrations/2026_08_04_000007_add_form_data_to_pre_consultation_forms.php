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
            ALTER TABLE pre_consultation_forms ADD COLUMN IF NOT EXISTS form_data jsonb NULL;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("
            ALTER TABLE pre_consultation_forms DROP COLUMN IF EXISTS form_data;
        ");
    }
};
