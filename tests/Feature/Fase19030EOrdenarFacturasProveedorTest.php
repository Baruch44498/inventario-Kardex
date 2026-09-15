<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase19030EOrdenarFacturasProveedorTest extends TestCase
{
    public function test_el_listado_conserva_solo_la_informacion_primaria_visible(): void
    {
        $vista = file_get_contents(resource_path('views/facturas_proveedor/index.blade.php'));

        $this->assertStringContainsString('supplier-invoice-list-table', $vista);
        $this->assertStringContainsString('data-responsive-table', $vista);
        $this->assertStringContainsString('table-details-toggle', $vista);
        $this->assertStringContainsString('table-row-details', $vista);
        $this->assertSame(7, substr_count($this->encabezadoTabla($vista), '<th'));
        $this->assertStringNotContainsString('<th>Orden</th>', $this->encabezadoTabla($vista));
        $this->assertStringNotContainsString('<th>Recepción</th>', $this->encabezadoTabla($vista));
    }

    public function test_el_detalle_usa_pestanas_accesibles_y_parciales(): void
    {
        $vista = file_get_contents(resource_path('views/facturas_proveedor/show.blade.php'));
        $script = file_get_contents(public_path('js/supplier-invoice-tabs.js'));

        foreach (['resumen', 'productos', 'recepciones', 'control'] as $pestana) {
            $this->assertStringContainsString("data-supplier-invoice-tab=\"{{ \$pestana }}\"", $vista);
            $this->assertStringContainsString("'{$pestana}' =>", $vista);
        }

        $this->assertStringContainsString('role="tablist"', $vista);
        $this->assertStringContainsString('role="tabpanel"', $vista);
        $this->assertStringContainsString("event.key === 'ArrowRight'", $script);
        $this->assertStringContainsString("event.key === 'ArrowLeft'", $script);
        $this->assertStringContainsString("window.location.hash", $script);
        $this->assertStringContainsString("'.field-error, .is-invalid, [aria-invalid=\"true\"]'", $script);
    }

    public function test_la_facturacion_total_es_predeterminada_y_la_parcial_es_explicita(): void
    {
        $vista = file_get_contents(resource_path('views/facturas_proveedor/create.blade.php'));
        $script = file_get_contents(public_path('js/supplier-invoice-form.js'));

        $this->assertStringContainsString('Facturación parcial', $vista);
        $this->assertStringContainsString('data-partial-billing-toggle', $vista);
        $this->assertStringContainsString('data-full-quantity', $vista);
        $this->assertStringContainsString('@readonly(! $facturacionParcialActiva)', $vista);
        $this->assertStringNotContainsString('<script>', $vista);
        $this->assertStringContainsString('input.readOnly = !partial', $script);
        $this->assertStringContainsString("input.value = input.dataset.fullQuantity", $script);
        $this->assertStringContainsString('data-invoice-base', $vista);
        $this->assertStringContainsString('data-invoice-igv', $vista);
        $this->assertStringContainsString('data-invoice-total', $vista);
    }

    public function test_costos_y_documento_original_permanecen_protegidos(): void
    {
        $formulario = file_get_contents(resource_path('views/facturas_proveedor/create.blade.php'));
        $control = file_get_contents(resource_path('views/facturas_proveedor/partials/_show_control.blade.php'));

        $this->assertStringContainsString('Protegidos por la OC aprobada', $formulario);
        $this->assertStringContainsString('Según OC', $formulario);
        $this->assertStringNotContainsString('name="tipo_cambio"', $formulario);
        $this->assertStringNotContainsString('name="costo_unitario_total"', $formulario);
        $this->assertStringContainsString('Descargar comprobante original', $control);
        $this->assertStringContainsString('data-file-download', $control);
        $this->assertStringContainsString("route('facturas-proveedor.documento-original'", $control);
    }

    private function encabezadoTabla(string $vista): string
    {
        preg_match('/<thead>(.*?)<\/thead>/s', $vista, $coincidencias);

        return $coincidencias[1] ?? '';
    }
}
