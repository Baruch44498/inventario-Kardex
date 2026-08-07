<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class Fase17066PrecisionVisualDosDecimalesTest extends TestCase
{
    public function test_componente_de_cantidades_limita_la_visualizacion_a_dos_decimales(): void
    {
        $contenido = file_get_contents(resource_path('views/components/ui/quantity.blade.php'));

        $this->assertStringContainsString("'decimals' => 2", $contenido);
        $this->assertStringContainsString('min(2, max(0, (int) $decimals))', $contenido);
    }

    public function test_vistas_blade_no_formatean_mas_de_dos_decimales(): void
    {
        $violaciones = [];
        $directorio = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($directorio as $archivo) {
            if (! $archivo->isFile() || ! str_ends_with($archivo->getFilename(), '.blade.php')) {
                continue;
            }

            $contenido = file_get_contents($archivo->getPathname());

            if (preg_match('/number_format\([^\n]*,\s*[3-9]\d*/', $contenido)) {
                $violaciones[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $archivo->getPathname());
            }
        }

        $this->assertSame([], $violaciones, 'Hay vistas que todavía muestran más de 2 decimales: '.implode(', ', $violaciones));
    }

    public function test_javascript_de_interfaz_no_fuerza_mas_de_dos_decimales(): void
    {
        $archivo = public_path('js/proforma-documentos.js');
        $contenido = file_get_contents($archivo);

        $this->assertDoesNotMatchRegularExpression('/toFixed\([3-9]\d*\)/', $contenido);
    }

    public function test_textos_dinamicos_de_stock_tambien_usan_dos_decimales(): void
    {
        $catalogo = file_get_contents(app_path('Http/Controllers/CatalogoBusquedaController.php'));
        $validacion = file_get_contents(app_path('Http/Requests/GuardarProformaRequest.php'));

        $this->assertDoesNotMatchRegularExpression('/number_format\([^\n]*,\s*[3-9]\d*/', $catalogo);
        $this->assertDoesNotMatchRegularExpression('/number_format\([^\n]*,\s*[3-9]\d*/', $validacion);
    }
}
