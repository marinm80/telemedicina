<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
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
    }
}
