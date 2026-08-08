<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase17072AjusteVisualReservasTablasTest extends TestCase
{
    public function test_inventario_usa_tabla_ancha_sin_encabezados_verticales(): void
    {
        $vista = file_get_contents(resource_path('views/inventario/index.blade.php'));
        $css = file_get_contents(public_path('css/hidroil-admin.css'));

        $this->assertStringContainsString('inventory-planning-table-wrap', $vista);
        $this->assertStringContainsString('inventory-planning-table', $vista);
        $this->assertStringContainsString('Costo prom.', $vista);
        $this->assertStringContainsString('Compra sug.', $vista);
        $this->assertStringContainsString('.inventory-planning-table {', $css);
        $this->assertStringContainsString('min-width: 1420px;', $css);
        $this->assertStringContainsString('word-break: normal;', $css);
    }

    public function test_reservas_conservan_informacion_pero_con_scroll_controlado(): void
    {
        $vista = file_get_contents(resource_path('views/ordenes_operacion/show.blade.php'));
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
        $vista = file_get_contents(resource_path('views/ordenes_operacion/show.blade.php'));
        $css = file_get_contents(public_path('css/hidroil-admin.css'));

        $this->assertStringContainsString("? 'activa' : 'activas'", $vista);
        $this->assertStringContainsString("? 'pendiente' : 'pendientes'", $vista);
        $this->assertStringContainsString('operation-embedded-empty--compact', $vista);
        $this->assertStringContainsString('min-height: 132px;', $css);
    }
}
