<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase17072AjusteVisualReservasTablasTest extends TestCase
{
    public function test_inventario_usa_tabla_compacta_con_cinco_columnas_principales(): void
    {
        $vista = file_get_contents(resource_path('views/inventario/index.blade.php'));
        $css = file_get_contents(public_path('css/hidroil/inventario.css'));
        $cssGeneral = file_get_contents(public_path('css/hidroil-admin.css'));

        $this->assertStringContainsString('inventory-compact-table-wrap', $vista);
        $this->assertStringContainsString('inventory-compact-table', $vista);
        $this->assertStringContainsString('<th>Disponibilidad</th>', $vista);
        $this->assertStringContainsString('<th>Ubicación</th>', $vista);
        $this->assertStringContainsString('<th>Alerta</th>', $vista);
        $this->assertStringContainsString('inventory-stock-cluster', $vista);
        $this->assertStringContainsString(':colspan="5"', $vista);
        $this->assertStringContainsString('.inventory-compact-table {', $css);
        $this->assertStringNotContainsString('inventory-planning-table', $cssGeneral);
        $this->assertStringNotContainsString('min-width: 1420px;', $cssGeneral);
    }

    public function test_reservas_conservan_informacion_pero_con_scroll_controlado(): void
    {
        $vista = file_get_contents(resource_path('views/ordenes_operacion/partials/_show_reservas.blade.php'));
        $css = file_get_contents(public_path('css/hidroil-admin.css'));

        $this->assertStringContainsString('reservation-table-wrap', $vista);
        $this->assertStringContainsString('Reservado', $vista);
        $this->assertStringContainsString('Atendido', $vista);
        $this->assertStringContainsString('Liberado', $vista);
        $this->assertStringContainsString('Pendiente', $vista);
        $this->assertStringContainsString('Compra sug.', $vista);
        $this->assertStringContainsString('min-width: 1360px;', $css);
    }

    public function test_contadores_tienen_contexto_y_vacio_de_herramientas_es_compacto(): void
    {
        $vista = file_get_contents(resource_path('views/ordenes_operacion/partials/_show_reservas.blade.php'))
            .file_get_contents(resource_path('views/ordenes_operacion/partials/_show_herramientas.blade.php'));
        $css = file_get_contents(public_path('css/hidroil-admin.css'));

        $this->assertStringContainsString("? 'activa' : 'activas'", $vista);
        $this->assertStringContainsString("? 'pendiente' : 'pendientes'", $vista);
        $this->assertStringContainsString('operation-embedded-empty--compact', $vista);
        $this->assertStringContainsString('min-height: 132px;', $css);
    }
}
