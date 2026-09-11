<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase1920DetalleRequerimientoCompactoTest extends TestCase
{
    public function test_el_detalle_define_la_pestana_inicial_segun_la_etapa(): void
    {
        $vista = file_get_contents(resource_path('views/requerimientos_compra/show.blade.php'));

        $this->assertStringContainsString('data-purchase-requirement-tabs', $vista);
        $this->assertStringContainsString("=> 'productos'", $vista);
        $this->assertStringContainsString("=> 'gestion'", $vista);
        $this->assertStringContainsString("=> 'abastecimiento'", $vista);
        $this->assertStringContainsString("asset('js/purchase-requirement-tabs.js')", $vista);
        $this->assertStringContainsString("! \$requerimiento->esBorrador() && \$seguimientoAbastecimiento['total_lineas'] > 0", $vista);
    }

    public function test_las_pestanas_son_accesibles_y_respetan_enlaces_a_la_accion(): void
    {
        $script = file_get_contents(public_path('js/purchase-requirement-tabs.js'));

        $this->assertStringContainsString("setAttribute('role', 'tablist')", $script);
        $this->assertStringContainsString("setAttribute('aria-selected'", $script);
        $this->assertStringContainsString("event.key === 'ArrowRight'", $script);
        $this->assertStringContainsString('panel.contains(target)', $script);
        $this->assertStringContainsString("'.field-error, .is-invalid, [aria-invalid=\"true\"]'", $script);
    }

    public function test_la_tabla_de_productos_agrupa_la_foto_de_stock(): void
    {
        $vista = file_get_contents(resource_path('views/requerimientos_compra/show.blade.php'));

        $this->assertStringContainsString('<th>Stock al registrar</th>', $vista);
        $this->assertStringContainsString('purchase-requirement-stock-snapshot', $vista);
        $this->assertStringNotContainsString('<th>Sugerido al registrar</th>', $vista);
        $this->assertStringNotContainsString('<th>Físico</th>', $vista);
        $this->assertStringNotContainsString('<th>Reservado</th>', $vista);
    }
}
