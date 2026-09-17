<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase19030COrdenarCotizacionProveedorTest extends TestCase
{
    public function test_el_formulario_se_divide_en_tres_pasos_y_el_controlador_sale_de_blade(): void
    {
        $vista = file_get_contents(resource_path('views/cotizaciones_proveedor/_form.blade.php'));

        foreach ([
            '_form_paso_documento',
            '_form_paso_productos',
            '_form_paso_revision',
        ] as $parcial) {
            $this->assertStringContainsString(
                "cotizaciones_proveedor.partials.{$parcial}",
                $vista
            );

            $contenido = file_get_contents(
                resource_path("views/cotizaciones_proveedor/partials/{$parcial}.blade.php")
            );

            $this->assertLessThanOrEqual(200, substr_count($contenido, "\n") + 1);
        }

        $this->assertLessThanOrEqual(200, substr_count($vista, "\n") + 1);
        $this->assertStringContainsString('js/cotizacion-proveedor-wizard.js', $vista);
        $this->assertStringContainsString('js/cotizacion-productos.js', $vista);
        $this->assertSame(2, substr_count($vista, '<script src='));
        $this->assertStringNotContainsString('<script>', $vista);
    }

    public function test_el_detalle_ofrece_cuatro_pestanas_y_parciales_pequenos(): void
    {
        $vista = file_get_contents(resource_path('views/cotizaciones_proveedor/show.blade.php'));

        foreach ([
            'resumen' => '_show_resumen',
            'productos' => '_show_productos',
            'compra' => '_show_decision_compra',
            'control' => '_show_control',
        ] as $pestana => $parcial) {
            $this->assertStringContainsString("data-supplier-quote-tab=\"{{ \$pestana }}\"", $vista);
            $this->assertStringContainsString(
                "'{$pestana}' => '{$parcial}'",
                $vista
            );

            $contenido = file_get_contents(
                resource_path("views/cotizaciones_proveedor/partials/{$parcial}.blade.php")
            );

            $this->assertLessThanOrEqual(200, substr_count($contenido, "\n") + 1);
        }

        $this->assertLessThanOrEqual(120, substr_count($vista, "\n") + 1);
        $this->assertStringContainsString('role="tablist"', $vista);
        $this->assertStringContainsString('js/supplier-quote-tabs.js', $vista);
    }

    public function test_las_pestanas_conservan_url_teclado_y_abren_validaciones(): void
    {
        $script = file_get_contents(public_path('js/supplier-quote-tabs.js'));

        foreach (['ArrowRight', 'ArrowLeft', 'Home', 'End', 'hashchange'] as $comportamiento) {
            $this->assertStringContainsString($comportamiento, $script);
        }

        $this->assertStringContainsString('window.history.replaceState', $script);
        $this->assertStringContainsString('.field-error, .is-invalid, [aria-invalid="true"]', $script);
        $this->assertStringContainsString('workspace.dataset.defaultTab', $script);
    }

    public function test_la_bandeja_deja_siete_columnas_y_datos_secundarios_expandibles(): void
    {
        $vista = file_get_contents(resource_path('views/cotizaciones_proveedor/index.blade.php'));
        $css = file_get_contents(public_path('css/hidroil/compras.css'));

        $this->assertSame(7, preg_match_all('/<th(?:\\s|>)/', $vista));
        $this->assertStringContainsString('<x-ui.table-details-toggle', $vista);
        $this->assertStringContainsString('<x-ui.table-row-details', $vista);
        $this->assertStringContainsString(':colspan="7"', $vista);

        foreach (['Fecha de cotización', 'Subtotal sin IGV', 'Documento externo', 'Registrado por'] as $dato) {
            $this->assertStringContainsString("<dt>{$dato}</dt>", $vista);
        }

        $this->assertStringContainsString('.data-table--responsive.supplier-quote-list-table {', $css);
        $this->assertStringContainsString('min-width: 0 !important;', $css);
        $this->assertStringContainsString('.supplier-quote-list-table .supplier-quote-list-row {', $css);
    }
}
