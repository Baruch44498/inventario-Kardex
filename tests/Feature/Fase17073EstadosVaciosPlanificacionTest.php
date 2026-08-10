<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase17073EstadosVaciosPlanificacionTest extends TestCase
{
    public function test_contadores_quedan_integrados_al_titulo_de_cada_bloque(): void
    {
        $vista = file_get_contents(resource_path('views/ordenes_operacion/show.blade.php'));

        $this->assertStringContainsString('operation-section-heading__title-row', $vista);
        $this->assertStringContainsString('count-chip--neutral', $vista);
        $this->assertStringContainsString("? 'activa' : 'activas'", $vista);
        $this->assertStringContainsString("? 'pendiente' : 'pendientes'", $vista);
    }

    public function test_estados_vacios_de_reservas_y_herramientas_son_compactos_y_horizontales(): void
    {
        $css = file_get_contents(public_path('css/hidroil-admin.css'));

        $this->assertStringContainsString('grid-template-columns: 44px minmax(0, 1fr);', $css);
        $this->assertStringContainsString('min-height: 0;', $css);
        $this->assertStringContainsString('padding: 14px 16px;', $css);
        $this->assertStringContainsString('text-align: left;', $css);
        $this->assertStringContainsString('.count-chip--neutral', $css);
    }

    public function test_no_se_eliminan_los_mensajes_funcionales_de_estado_vacio(): void
    {
        $vista = file_get_contents(resource_path('views/ordenes_operacion/show.blade.php'));

        $this->assertStringContainsString('Sin materiales reservados', $vista);
        $this->assertStringContainsString('Sin herramientas pendientes', $vista);
        $this->assertStringContainsString('La orden todavía no está activa. La reserva se generará automáticamente al activarla.', $vista);
        $this->assertStringContainsString('No hay materiales pendientes de reserva para esta orden.', $vista);
        $this->assertStringContainsString('No hay salidas de uso temporal pendientes de retorno', $vista);
    }
}
