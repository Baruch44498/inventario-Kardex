<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase19030ADivisionDetalleRequerimientoTest extends TestCase
{
    public function test_la_vista_principal_coordina_parciales_pequenos(): void
    {
        $rutaPrincipal = resource_path('views/requerimientos_compra/show.blade.php');
        $vistaPrincipal = file_get_contents($rutaPrincipal);
        $parciales = [
            '_show_encabezado',
            '_show_abastecimiento',
            '_show_gestion',
            '_show_historial',
            '_show_productos',
            '_show_proveedores',
            '_show_cotizaciones',
        ];

        $this->assertLessThanOrEqual(70, count(file($rutaPrincipal)));

        foreach ($parciales as $parcial) {
            $this->assertStringContainsString(
                "@include('requerimientos_compra.partials.{$parcial}')",
                $vistaPrincipal
            );

            $rutaParcial = resource_path("views/requerimientos_compra/partials/{$parcial}.blade.php");
            $this->assertFileExists($rutaParcial);
            $this->assertLessThanOrEqual(150, count(file($rutaParcial)));
        }
    }

    public function test_las_tarjetas_de_proveedor_comparten_un_solo_parcial(): void
    {
        $rutaTarjeta = resource_path(
            'views/requerimientos_compra/partials/_tarjeta_proveedor.blade.php'
        );
        $secciones = file_get_contents(resource_path(
            'views/requerimientos_compra/partials/_show_proveedores.blade.php'
        ));
        $tarjeta = file_get_contents($rutaTarjeta);

        $this->assertLessThanOrEqual(150, count(file($rutaTarjeta)));
        $this->assertSame(2, substr_count(
            $secciones,
            "@include('requerimientos_compra.partials._tarjeta_proveedor'"
        ));
        $this->assertSame(1, substr_count(
            $tarjeta,
            'class="prc-supplier-card prc-supplier-card--confirmed"'
        ));
        $this->assertStringContainsString('Cotizar esta lista', $tarjeta);
        $this->assertStringContainsString('Cotizar con este', $tarjeta);
        $this->assertStringContainsString('Descargar solicitud Excel', $tarjeta);
    }

    public function test_el_detalle_reutiliza_el_componente_de_iconos(): void
    {
        $archivos = array_merge(
            [resource_path('views/requerimientos_compra/show.blade.php')],
            glob(resource_path('views/requerimientos_compra/partials/*.blade.php')) ?: []
        );
        $contenido = collect($archivos)
            ->map(fn (string $archivo): string => file_get_contents($archivo))
            ->implode("\n");

        $this->assertStringNotContainsString('<svg', $contenido);
        $this->assertStringContainsString('<x-ui.icon', $contenido);
    }
}
