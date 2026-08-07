<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase17065ClaridadResumenNotasTest extends TestCase
{
    public function test_resumen_de_salida_distingue_productos_de_cantidades(): void
    {
        $vista = file_get_contents(resource_path('views/notas_salida/show.blade.php'));

        $this->assertStringContainsString('Productos distintos', $vista);
        $this->assertStringContainsString('Cantidad entregada:', $vista);
        $this->assertStringContainsString('Ver cantidades en el detalle', $vista);
        $this->assertStringContainsString("pluck('producto_id')->filter()->unique()->count()", $vista);
        $this->assertStringNotContainsString('Cantidad total:', $vista);
        $this->assertStringContainsString('panel entry-detail-card', $vista);
    }

    public function test_resumen_de_ingreso_usa_la_misma_regla_semantica(): void
    {
        $vista = file_get_contents(resource_path('views/notas_ingreso/show.blade.php'));

        $this->assertStringContainsString('Productos distintos', $vista);
        $this->assertStringContainsString('Cantidad recibida:', $vista);
        $this->assertStringContainsString('Ver cantidades en el detalle', $vista);
        $this->assertStringContainsString("pluck('producto_id')->filter()->unique()->count()", $vista);
        $this->assertStringNotContainsString('Cantidad total:', $vista);
        $this->assertStringContainsString('panel entry-detail-card', $vista);
    }

    public function test_css_da_respiracion_a_documento_y_detalle_sin_tocar_el_wizard(): void
    {
        $css = file_get_contents(public_path('css/hidroil-admin.css'));

        $this->assertStringContainsString('.entry-document-card .panel-heading', $css);
        $this->assertStringContainsString('.entry-document-card .detail-list--entry', $css);
        $this->assertStringContainsString('.entry-detail-card .table-wrap', $css);
        $this->assertStringContainsString('padding: 24px 22px;', $css);
    }
}
