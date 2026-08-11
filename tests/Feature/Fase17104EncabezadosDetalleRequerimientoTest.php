<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase17104EncabezadosDetalleRequerimientoTest extends TestCase
{
    public function test_detalle_usa_encabezado_integrado_para_productos_proveedores_y_cotizaciones(): void
    {
        $vista = file_get_contents(resource_path('views/requerimientos_compra/show.blade.php'));

        $this->assertStringContainsString('purchase-requirement-section-heading', $vista);
        $this->assertStringContainsString('purchase-requirement-section-heading__title-row', $vista);
        $this->assertStringContainsString('purchase-requirement-section-heading__meta', $vista);
        $this->assertStringContainsString('Necesidad enviada', $vista);
        $this->assertStringContainsString('Contactos de proveedores sugeridos', $vista);
        $this->assertStringContainsString('Cotizaciones vinculadas', $vista);
    }

    public function test_ayuda_del_borrador_esta_integrada_en_el_encabezado_de_productos(): void
    {
        $vista = file_get_contents(resource_path('views/requerimientos_compra/show.blade.php'));

        $inicioPanel = strpos($vista, 'purchase-requirement-detail-panel');
        $ayuda = strpos($vista, 'Todavía es un borrador de Almacén');
        $finPanel = strpos($vista, '<div class="table-wrap', $inicioPanel);

        $this->assertNotFalse($inicioPanel);
        $this->assertNotFalse($ayuda);
        $this->assertNotFalse($finPanel);
        $this->assertGreaterThan($inicioPanel, $ayuda);
        $this->assertLessThan($finPanel, $ayuda);
        $this->assertStringContainsString('purchase-requirement-section-heading__help', $vista);
    }

    public function test_css_alinea_y_encapsula_los_encabezados_del_detalle(): void
    {
        $css = file_get_contents(public_path('css/hidroil-admin.css'));

        $this->assertStringContainsString('.purchase-requirement-section-heading {', $css);
        $this->assertStringContainsString('.purchase-requirement-section-heading__title-row {', $css);
        $this->assertStringContainsString('.purchase-requirement-section-heading__meta {', $css);
        $this->assertStringContainsString('.purchase-requirement-section-heading__help.ui-collapsible-notice {', $css);
    }
}
