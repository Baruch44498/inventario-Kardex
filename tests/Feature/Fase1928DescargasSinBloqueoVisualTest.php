<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase1928DescargasSinBloqueoVisualTest extends TestCase
{
    public function test_el_indicador_global_no_intercepta_enlaces_de_descarga(): void
    {
        $script = file_get_contents(public_path('js/hidroil-ui.js'));

        $this->assertStringContainsString(
            "link.hasAttribute('data-file-download')",
            $script
        );
    }

    public function test_las_descargas_del_sistema_estan_identificadas_como_archivos(): void
    {
        $vistas = [
            'requerimientos_compra/show.blade.php' => 2,
            'cotizaciones_cliente/presupuesto.blade.php' => 1,
            'ordenes_operacion/gasto_real.blade.php' => 1,
            'facturas_proveedor/show.blade.php' => 1,
            'ordenes_compra/show.blade.php' => 1,
            'solicitudes_compra/show.blade.php' => 1,
            'cotizaciones_proveedor/show.blade.php' => 1,
        ];

        foreach ($vistas as $vista => $cantidadMinima) {
            $contenido = file_get_contents(resource_path('views/'.$vista));

            $this->assertGreaterThanOrEqual(
                $cantidadMinima,
                substr_count($contenido, 'data-file-download'),
                "La vista {$vista} tiene descargas sin identificar."
            );
        }
    }
}
