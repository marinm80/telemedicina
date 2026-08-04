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
            INSERT INTO roles (id, name, description) VALUES
                ('00000000-0000-0000-0000-000000000001', 'admin', 'Administrador del sistema'),
                ('00000000-0000-0000-0000-000000000002', 'doctor', 'Médico especialista'),
                ('00000000-0000-0000-0000-000000000003', 'patient', 'Paciente'),
                ('00000000-0000-0000-0000-000000000004', 'agent', 'Recepcionista')
            ON CONFLICT (name) DO NOTHING;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("
            DELETE FROM roles WHERE id IN (
                '00000000-0000-0000-0000-000000000001',
                '00000000-0000-0000-0000-000000000002',
                '00000000-0000-0000-0000-000000000003',
                '00000000-0000-0000-0000-000000000004'
            );
        ");
    }
};
