<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase19031BNotasIngresoOrdenadasTest extends TestCase
{
    public function test_formulario_se_divide_en_cuatro_pasos_semanticos(): void
    {
        $vista = file_get_contents(resource_path('views/notas_ingreso/create.blade.php'));
        $parciales = [
            '_create_origen' => 'data-flow-step-section="1"',
            '_create_datos' => 'data-flow-step-section="2"',
            '_create_productos' => 'data-flow-step-section="3"',
            '_create_confirmacion' => 'data-flow-step-section="4"',
        ];

        foreach ($parciales as $parcial => $marcador) {
            $ruta = resource_path("views/notas_ingreso/partials/{$parcial}.blade.php");
            $this->assertFileExists($ruta);
            $this->assertStringContainsString("notas_ingreso.partials.{$parcial}", $vista);
            $this->assertStringContainsString($marcador, file_get_contents($ruta));
        }

        $this->assertLessThan(120, count(file(resource_path('views/notas_ingreso/create.blade.php'))));
        $this->assertStringContainsString("asset('js/document-note-wizard.js')", $vista);
        $this->assertStringContainsString("asset('js/nota-ingreso-form.js')", $vista);
        $this->assertStringNotContainsString('<script>', $vista);
    }

    public function test_productos_conservan_presentaciones_conversion_y_ubicacion(): void
    {
        $vista = file_get_contents(resource_path('views/notas_ingreso/partials/_create_productos.blade.php'));
        $script = file_get_contents(public_path('js/nota-ingreso-form.js'));

        $this->assertStringContainsString('data-reception-measure', $vista);
        $this->assertStringContainsString('factor_conversion', $vista);
        $this->assertStringContainsString('unidad_recepcion', $vista);
        $this->assertStringContainsString('[repisa_id]', $vista);
        $this->assertStringContainsString('data-entry-quantity', $vista);
        $this->assertStringContainsString('baseQuantity.toFixed(2)', $script);
        $this->assertStringContainsString('pendingPresentation', $script);
        $this->assertStringContainsString('pendingBase', $script);
        $this->assertStringContainsString('al Kardex', $script);
    }

    public function test_detalle_separa_verdad_fisica_documento_trazabilidad_y_anulacion(): void
    {
        $vista = file_get_contents(resource_path('views/notas_ingreso/show.blade.php'));

        foreach (['Verdad física', 'Documento adjunto', 'Trazabilidad', 'Anulación'] as $seccion) {
            $this->assertStringContainsString($seccion, $vista);
        }

        foreach (['_show_fisico', '_show_documento', '_show_trazabilidad', '_show_anulacion'] as $parcial) {
            $this->assertStringContainsString("'{$parcial}'", $vista);
            $this->assertFileExists(resource_path("views/notas_ingreso/partials/{$parcial}.blade.php"));
        }

        $this->assertLessThan(150, count(file(resource_path('views/notas_ingreso/show.blade.php'))));
        $this->assertStringContainsString('role="tablist"', $vista);
        $this->assertStringContainsString('role="tabpanel"', $vista);
        $this->assertStringContainsString("asset('js/nota-ingreso-detail.js')", $vista);
    }

    public function test_verdad_fisica_usa_cinco_columnas_y_preserva_conversion(): void
    {
        $vista = file_get_contents(resource_path('views/notas_ingreso/partials/_show_fisico.blade.php'));

        preg_match('/<thead>\s*<tr>(.*?)<\/tr>\s*<\/thead>/s', $vista, $cabecera);

        $this->assertArrayHasKey(1, $cabecera);
        $this->assertSame(5, substr_count($cabecera[1], '<th'));
        $this->assertStringContainsString('Producto', $cabecera[1]);
        $this->assertStringContainsString('Cantidad', $cabecera[1]);
        $this->assertStringContainsString('Ubicación', $cabecera[1]);
        $this->assertStringContainsString('Valorización', $cabecera[1]);
        $this->assertStringContainsString('Referencia física', $cabecera[1]);
        $this->assertStringContainsString('cantidad_presentacion', $vista);
        $this->assertStringContainsString('factor_conversion', $vista);
        $this->assertStringContainsString('afecta_stock', $vista);
    }

    public function test_detalle_admite_url_teclado_y_modal_de_anulacion(): void
    {
        $script = file_get_contents(public_path('js/nota-ingreso-detail.js'));

        $this->assertStringContainsString('window.location.hash', $script);
        $this->assertStringContainsString('window.history.replaceState', $script);
        $this->assertStringContainsString("'ArrowRight'", $script);
        $this->assertStringContainsString("'ArrowLeft'", $script);
        $this->assertStringContainsString("'Home'", $script);
        $this->assertStringContainsString("'End'", $script);
        $this->assertStringContainsString('[data-entry-cancel-modal]', $script);
        $this->assertStringContainsString("'Escape'", $script);
    }

    public function test_estilos_nuevos_viven_en_inventario_css(): void
    {
        $cssGeneral = file_get_contents(public_path('css/hidroil-admin.css'));
        $cssInventario = file_get_contents(public_path('css/hidroil/inventario.css'));

        $this->assertStringContainsString('.entry-detail-tabs', $cssInventario);
        $this->assertStringContainsString('.entry-truth-summary', $cssInventario);
        $this->assertStringContainsString('.entry-cancellation-state', $cssInventario);
        $this->assertStringContainsString('.entry-lot-fields', $cssInventario);
        $this->assertStringContainsString('.entry-lines-table .table-input--readonly', $cssInventario);
        $this->assertStringNotContainsString('.entry-lot-fields', $cssGeneral);
        $this->assertStringNotContainsString('.entry-lines-table .table-input--readonly', $cssGeneral);
    }
}
