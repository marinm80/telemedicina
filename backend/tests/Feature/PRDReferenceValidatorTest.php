<?php
declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class PRDReferenceValidatorTest extends TestCase
{
    private static array $prdRequirements = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!empty(self::$prdRequirements)) {
            return;
        }

        $baseDir = dirname(__DIR__, 2);
        $possiblePrdPaths = [
            '/var/www/docs/PRD.md',
            dirname($baseDir) . '/docs/PRD.md',
            $baseDir . '/docs/PRD.md',
        ];

        $prdPath = null;
        foreach ($possiblePrdPaths as $path) {
            if (file_exists($path)) {
                $prdPath = $path;
                break;
            }
        }

        if (!$prdPath) {
            $this->fail('No se encontró el archivo PRD.md para validar los requerimientos.');
        }

        $lines = file($prdPath, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            if (preg_match('/^\|\s*(RF-\d+)\s*\|\s*([^\|]+)\s*\|/', $line, $matches)) {
                $id = trim($matches[1]);
                $name = trim($matches[2]);
                if ($id !== 'ID' && str_starts_with($id, 'RF-')) {
                    self::$prdRequirements[$id] = $name;
                }
            }
        }
    }

    public function test_prd_table_of_requirements_is_not_empty(): void
    {
        $this->assertNotEmpty(self::$prdRequirements, 'El PRD debe contener una tabla de requerimientos en la sección 4.');
        $this->assertArrayHasKey('RF-01', self::$prdRequirements);
        $this->assertArrayHasKey('RF-12', self::$prdRequirements);
        $this->assertArrayHasKey('RF-25', self::$prdRequirements);
    }

    public function test_all_rf_references_in_docs_are_valid(): void
    {
        $possibleDocsDirs = [
            '/var/www/docs',
            dirname(dirname(__DIR__, 2)) . '/docs',
            dirname(__DIR__, 2) . '/docs',
        ];

        $docsDir = null;
        foreach ($possibleDocsDirs as $dir) {
            if (is_dir($dir)) {
                $docsDir = $dir;
                break;
            }
        }

        $files = glob($docsDir . '/*.md') ?: [];
        $rfPattern = '/RF-\d+/';

        foreach ($files as $file) {
            $content = file_get_contents($file);
            preg_match_all($rfPattern, $content, $matches);

            foreach ($matches[0] as $rfRef) {
                $this->assertArrayHasKey(
                    $rfRef,
                    self::$prdRequirements,
                    sprintf('La referencia "%s" en [%s] no existe en el PRD.md.', $rfRef, basename($file))
                );
            }
        }
    }

    public function test_no_invented_rf_subnumberings_in_code_or_docs(): void
    {
        $baseDir = dirname(__DIR__, 2);
        $possibleDocsDirs = [
            '/var/www/docs',
            dirname($baseDir) . '/docs',
            $baseDir . '/docs',
        ];

        $docsDir = null;
        foreach ($possibleDocsDirs as $dir) {
            if (is_dir($dir)) {
                $docsDir = $dir;
                break;
            }
        }

        $files = array_merge(
            glob($docsDir . '/*.md') ?: [],
            glob($baseDir . '/tests/Feature/*.php') ?: []
        );

        // Detectar subnúmeros no autorizados como RF seguido de sufijos de letras o puntos (ejemplo: RF con sufijo a/b)
        $invalidSubPattern = '/RF-\d+[a-zA-Z\.\_]+/';

        foreach ($files as $file) {
            $content = file_get_contents($file);
            preg_match_all($invalidSubPattern, $content, $matches);

            foreach ($matches[0] as $invalidRef) {
                $cleanRef = rtrim($invalidRef, '.,:;)]"\'');
                if (!preg_match('/^RF-\d+$/', $cleanRef)) {
                    $this->fail(sprintf('Se detectó una subnumeración de RF no autorizada "%s" en [%s]. Usar el número exacto del PRD.', $invalidRef, basename($file)));
                }
            }
        }
    }
}
