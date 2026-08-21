<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase171231AjusteVisualDetallePreciosCotizacionProveedorTest extends TestCase
{
    public function test_detalle_de_precios_usa_una_tabla_dedicada_y_desplazable(): void
    {
        $vista = file_get_contents(resource_path('views/cotizaciones_proveedor/show.blade.php'));

        $this->assertStringContainsString('supplier-quote-detail-table-wrap', $vista);
        $this->assertStringContainsString('supplier-quote-detail-table', $vista);
        $this->assertStringContainsString('aria-label="Detalle de productos cotizados"', $vista);
        $this->assertStringContainsString('tabindex="0"', $vista);
    }

    public function test_importes_de_linea_usan_el_componente_monetario_no_fragmentable(): void
    {
        $vista = file_get_contents(resource_path('views/cotizaciones_proveedor/show.blade.php'));

        $this->assertMatchesRegularExpression(
            '/<table[^>]*supplier-quote-detail-table[^>]*>(.*?)<\/table>/su',
            $vista
        );
        preg_match('/<table[^>]*supplier-quote-detail-table[^>]*>(.*?)<\/table>/su', $vista, $tabla);

        $contenidoTabla = $tabla[1];

        $this->assertSame(4, substr_count($contenidoTabla, '<x-ui.money'));
        $this->assertStringContainsString(
            ':value="$detalle->precio_presentacion ?? $detalle->precio_unitario"',
            $contenidoTabla
        );
        $this->assertStringContainsString(':value="$detalle->subtotal"', $contenidoTabla);
        $this->assertStringContainsString(':value="$detalle->impuesto"', $contenidoTabla);
        $this->assertStringContainsString(':value="$detalle->total"', $contenidoTabla);
    }

    public function test_estilos_reservan_ancho_y_evitan_partir_palabras_o_importes(): void
    {
        $css = file_get_contents(public_path('css/hidroil-admin.css'));

        $this->assertStringContainsString('.supplier-quote-detail-table {', $css);
        $this->assertStringContainsString('min-width: 1465px;', $css);
        $this->assertStringContainsString('word-break: normal;', $css);
        $this->assertStringContainsString('.supplier-quote-detail-table__money .ui-money', $css);
        $this->assertStringContainsString('white-space: nowrap;', $css);
    }
}
