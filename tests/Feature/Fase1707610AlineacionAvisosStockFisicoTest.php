<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase1707610AlineacionAvisosStockFisicoTest extends TestCase
{
    public function test_las_ayudas_de_stock_fisico_estan_en_el_encabezado_y_en_una_sola_fila(): void
    {
        $vista = file_get_contents(resource_path('views/notas_salida/create.blade.php'));
        $css = file_get_contents(public_path('css/hidroil-admin.css'));

        $this->assertStringContainsString('panel-heading panel-heading--split output-stock-heading', $vista);
        $this->assertStringContainsString('ui-collapsible-notice-cluster output-stock-heading__notices', $vista);
        $this->assertStringContainsString('title="Productos adicionales"', $vista);
        $this->assertStringNotContainsString('class="output-extra-help"', $vista);
        $this->assertStringContainsString('.output-stock-heading__notices', $css);
        $this->assertStringContainsString('justify-content: flex-end;', $css);
    }
}
