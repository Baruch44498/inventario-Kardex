<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase19032CierreEstilosModularesTest extends TestCase
{
    public function test_layout_carga_hojas_modulares_despues_del_css_general(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $general = strpos($layout, "asset('css/hidroil-admin.css')");
        $inventario = strpos($layout, "asset('css/hidroil/inventario.css')");
        $ordenes = strpos($layout, "asset('css/hidroil/ordenes.css')");

        $this->assertNotFalse($general);
        $this->assertNotFalse($inventario);
        $this->assertNotFalse($ordenes);
        $this->assertTrue($general < $inventario && $inventario < $ordenes);
        $this->assertFileExists(public_path('css/hidroil/ordenes.css'));
    }

    public function test_pestanas_se_conservan_en_css_cargado_y_no_se_duplican_en_heredado(): void
    {
        $modulo = file_get_contents(public_path('css/hidroil/ordenes.css'));
        $heredado = file_get_contents(public_path('css/hidroil-admin.css'));

        foreach ([
            '.operation-page--show:not(.operation-page--tabbed) .operation-detail-tabs',
            '.operation-tab-panel[hidden]',
            '.commercial-quote-detail:not(.commercial-quote-detail--tabbed) .commercial-quote-detail-tabs',
            '.budget-review-table--detail td:last-child form',
        ] as $selector) {
            $this->assertStringContainsString($selector, $modulo);
            $this->assertStringNotContainsString($selector, $heredado);
        }
    }
}
