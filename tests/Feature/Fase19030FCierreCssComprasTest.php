<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase19030FCierreCssComprasTest extends TestCase
{
    public function test_el_layout_carga_el_modulo_de_compras_en_el_orden_previsto(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $admin = strpos($layout, "asset('css/hidroil-admin.css')");
        $compras = strpos($layout, "asset('css/hidroil/compras.css')");
        $base = strpos($layout, "asset('css/hidroil/base.css')");

        $this->assertNotFalse($admin);
        $this->assertNotFalse($compras);
        $this->assertNotFalse($base);
        $this->assertLessThan($compras, $admin);
        $this->assertLessThan($base, $compras);
    }

    public function test_los_estilos_compactos_de_compras_quedan_fuera_del_css_heredado(): void
    {
        $admin = file_get_contents(public_path('css/hidroil-admin.css'));
        $compras = file_get_contents(public_path('css/hidroil/compras.css'));

        foreach ([
            '.purchase-requirement-page--show',
            '.data-table--responsive.purchase-requirement-list-table',
            '.supplier-quote-workspace',
            '.purchase-order-workspace',
            '.supplier-invoice-workspace',
        ] as $selector) {
            $this->assertStringNotContainsString($selector, $admin);
            $this->assertStringContainsString($selector, $compras);
        }
    }
}
