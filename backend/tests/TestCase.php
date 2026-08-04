<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * TRUNCATE centralizado sobre todas las tablas de datos.
     *
     * Reemplaza el borrado ordenado por tabla en cada test. El orden de
     * borrado manual escala con el cuadrado de las tablas: cada FK nueva
     * agrega una restricción de orden que hay que mantener a mano. CASCADE
     * resuelve el orden solo; agregar una tabla nueva es agregar un nombre.
     *
     * Tablas EXCLUIDAS (datos de semilla, no de test):
     *   roles, permissions, role_permissions, specialties
     */
    protected const TRUNCATE_SQL = "
        TRUNCATE
            users,
            doctor_profiles,
            patient_profiles,
            schedules,
            schedule_blocks,
            appointments,
            audit_logs,
            user_roles,
            user_permissions,
            consultation_notes,
            consultation_messages,
            consultations,
            note_amendments,
            patient_allergies,
            patient_conditions,
            patient_medications,
            pre_consultation_forms,
            vital_signs,
            documents,
            payments,
            processed_stripe_events,
            commissions,
            doctor_specialties
        CASCADE
    ";

    protected function setUp(): void
    {
        parent::setUp();

        // Barrera: ningún test puede correr como superusuario.
        // Un test que corre como postgres no prueba RLS ni GRANTs.
        $isSuperuser = DB::selectOne(
            "SELECT usesuper FROM pg_user WHERE usename = current_user"
        );
        $this->assertFalse(
            $isSuperuser->usesuper ?? true,
            'La conexión por defecto de la suite (pgsql) NO debe ser superusuario. '
            . 'Si ves este error, verificá DB_CONNECTION en phpunit.xml y las credenciales '
            . 'de la conexión pgsql.'
        );

        // Limpiar ANTES de montar fixtures. Idempotente sin importar cómo
        // terminó la corrida previa: si tearDown corrió, esto es un no-op
        // sobre tablas vacías; si la corrida anterior fue interrumpida
        // (Ctrl-C, timeout, fatal), esto limpia el estado sucio que quedó.
        DB::connection('pgsql_migration')->unprepared(self::TRUNCATE_SQL);
    }

    /**
     * Limpieza post-test: deja la base ordenada para inspección manual
     * después de un test aislado con --filter. La garantía de limpieza
     * real la da setUp, no este método — tearDown no sobrevive a una
     * interrupción.
     */
    protected function tearDown(): void
    {
        DB::connection('pgsql_migration')->unprepared(self::TRUNCATE_SQL);

        parent::tearDown();
    }
}
