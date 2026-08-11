<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase17103AjusteVisualPersistenciaCapturaTest extends TestCase
{
    public function test_requerimiento_encapsula_origen_y_productos_en_paneles_visuales(): void
    {
        $vista = file_get_contents(resource_path('views/requerimientos_compra/_form.blade.php'));
        $css = file_get_contents(public_path('css/hidroil-admin.css'));

        $this->assertStringContainsString('purchase-requirement-form-section', $vista);
        $this->assertStringContainsString('purchase-requirement-products', $vista);
        $this->assertStringContainsString('.purchase-requirement-form-section,', $css);
        $this->assertStringContainsString('background: var(--color-surface, #fff);', $css);
    }

    public function test_ayuda_de_compra_sugerida_se_alinea_con_el_encabezado_de_productos(): void
    {
        $vista = file_get_contents(resource_path('views/requerimientos_compra/_form.blade.php'));

        $this->assertStringContainsString('purchase-requirement-products__heading', $vista);
        $this->assertStringContainsString('purchase-requirement-products__help', $vista);
        $this->assertStringContainsString('Cómo se calcula la sugerencia', $vista);

        $heading = strpos($vista, 'purchase-requirement-products__heading');
        $help = strpos($vista, 'purchase-requirement-products__help');
        $search = strpos($vista, 'purchase-requirement-product-search');

        $this->assertNotFalse($heading);
        $this->assertNotFalse($help);
        $this->assertNotFalse($search);
        $this->assertLessThan($search, $help);
    }

    public function test_cantidades_y_observaciones_se_sincronizan_antes_de_re_renderizar_la_tabla(): void
    {
        $vista = file_get_contents(resource_path('views/requerimientos_compra/_form.blade.php'));

        $this->assertStringContainsString('const syncCurrentLineValues = () =>', $vista);
        $this->assertStringContainsString("body.addEventListener('input'", $vista);
        $this->assertStringContainsString('data-line-quantity', $vista);
        $this->assertStringContainsString('data-line-observation', $vista);
        $this->assertStringContainsString('lines[index].cantidad_solicitada = quantity.value;', $vista);
        $this->assertStringContainsString('lines[index].observacion = observation.value;', $vista);
        $this->assertStringContainsString("const addLine = item => {\n        syncCurrentLineValues();", $vista);
    }

    public function test_usar_sugerido_actualiza_tambien_el_estado_interno_de_la_linea(): void
    {
        $vista = file_get_contents(resource_path('views/requerimientos_compra/_form.blade.php'));

        $this->assertStringContainsString('input.value = lines[index].cantidad_sugerida;', $vista);
        $this->assertStringContainsString('lines[index].cantidad_solicitada = input.value;', $vista);
    }
}
