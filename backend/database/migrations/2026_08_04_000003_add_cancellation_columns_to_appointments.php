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
            ALTER TABLE appointments
                ADD COLUMN IF NOT EXISTS cancelled_at timestamptz NULL,
                ADD COLUMN IF NOT EXISTS refund_amount numeric(10,2) NULL,
                ADD COLUMN IF NOT EXISTS refund_status varchar(50) NULL;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("
            ALTER TABLE appointments
                DROP COLUMN IF EXISTS cancelled_at,
                DROP COLUMN IF EXISTS refund_amount,
                DROP COLUMN IF EXISTS refund_status;
        ");
    }
};
