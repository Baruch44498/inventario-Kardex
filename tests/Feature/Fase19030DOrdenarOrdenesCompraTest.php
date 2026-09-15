<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase19030DOrdenarOrdenesCompraTest extends TestCase
{
    public function test_el_detalle_se_divide_en_cuatro_secciones_operativas(): void
    {
        $vista = file_get_contents(resource_path('views/ordenes_compra/show.blade.php'));

        foreach ([
            'resumen' => '_show_resumen',
            'productos' => '_show_productos_entregas',
            'documentos' => '_show_recepciones_facturas',
            'historial' => '_show_historial',
        ] as $pestana => $parcial) {
            $this->assertStringContainsString("'{$pestana}' => '{$parcial}'", $vista);

            $contenido = file_get_contents(
                resource_path("views/ordenes_compra/partials/{$parcial}.blade.php")
            );

            $this->assertLessThanOrEqual(200, substr_count($contenido, "\n") + 1);
        }

        $this->assertLessThanOrEqual(140, substr_count($vista, "\n") + 1);
        $this->assertStringContainsString('role="tablist"', $vista);
        $this->assertStringContainsString('Productos y entregas', $vista);
        $this->assertStringContainsString('Recepciones y facturas', $vista);
        $this->assertStringContainsString('js/purchase-order-tabs.js', $vista);
    }

    public function test_conserva_recepcion_facturacion_trazabilidad_y_anulacion(): void
    {
        $vista = file_get_contents(resource_path('views/ordenes_compra/show.blade.php'));
        $productos = file_get_contents(resource_path(
            'views/ordenes_compra/partials/_show_productos_entregas.blade.php'
        ));
        $documentos = file_get_contents(resource_path(
            'views/ordenes_compra/partials/_show_recepciones_facturas.blade.php'
        ));
        $historial = file_get_contents(resource_path(
            'views/ordenes_compra/partials/_show_historial.blade.php'
        ));

        $this->assertStringContainsString('notas-ingreso.create', $vista);
        $this->assertStringContainsString('facturas-proveedor.create', $vista);
        $this->assertStringContainsString('cantidadPendiente()', $productos);
        $this->assertStringContainsString('porcentajeRecibido()', $productos);
        $this->assertStringContainsString('Recepciones registradas', $documentos);
        $this->assertStringContainsString('Facturas registradas', $documentos);
        $this->assertStringContainsString('solicitudes-compra.show', $historial);
        $this->assertStringContainsString('cotizaciones-proveedor.show', $historial);
        $this->assertStringContainsString('ordenes-compra.anular', $historial);
        $this->assertStringContainsString('data-file-download', $historial);
    }

    public function test_las_pestanas_conservan_url_teclado_y_abren_errores(): void
    {
        $script = file_get_contents(public_path('js/purchase-order-tabs.js'));

        foreach (['ArrowRight', 'ArrowLeft', 'Home', 'End', 'hashchange'] as $comportamiento) {
            $this->assertStringContainsString($comportamiento, $script);
        }

        $this->assertStringContainsString('window.history.replaceState', $script);
        $this->assertStringContainsString('.field-error, .is-invalid, [aria-invalid="true"]', $script);
        $this->assertStringContainsString('workspace.dataset.defaultTab', $script);
    }

    public function test_la_bandeja_deja_siete_columnas_y_detalle_expandible(): void
    {
        $vista = file_get_contents(resource_path('views/ordenes_compra/index.blade.php'));
        $css = file_get_contents(public_path('css/hidroil-admin.css'));

        $this->assertSame(7, preg_match_all('/<th(?:\\s|>)/', $vista));
        $this->assertStringContainsString('<x-ui.table-details-toggle', $vista);
        $this->assertStringContainsString('<x-ui.table-row-details', $vista);
        $this->assertStringContainsString(':colspan="7"', $vista);

        foreach (['Origen', 'Emisión', 'Productos', 'Saldo por recibir', 'Moneda'] as $dato) {
            $this->assertStringContainsString("<dt>{$dato}</dt>", $vista);
        }

        $this->assertStringContainsString('.data-table--responsive.purchase-order-list-table {', $css);
        $this->assertStringContainsString('min-width: 0 !important;', $css);
        $this->assertStringContainsString('.purchase-order-list-table .purchase-order-list-row {', $css);
    }
}
