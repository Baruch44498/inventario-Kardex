<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase19242PresentacionesProductoIntuitivasTest extends TestCase
{
    public function test_formulario_explica_presentaciones_sin_un_banner_permanente(): void
    {
        $vista = file_get_contents(resource_path('views/productos/_form.blade.php'));
        $script = file_get_contents(public_path('js/product-form.js'));

        $this->assertStringContainsString('Presentaciones de compra (opcional)', $vista);
        $this->assertStringContainsString('product-presentations__help', $vista);
        $this->assertStringContainsString('Tipo de empaque', $vista);
        $this->assertStringContainsString('no repitas el nombre del producto', $vista);
        $this->assertStringContainsString('Contenido por empaque', $vista);
        $this->assertStringContainsString('data-presentation-preview', $vista);
        $this->assertStringContainsString("asset('js/product-form.js')", $vista);
        $this->assertStringNotContainsString('<script>', $vista);
        $this->assertStringContainsString('1 ${name} = ${factor.toFixed(2)} ${baseUnit()}', $script);
        $this->assertStringContainsString('factor_conversion', $script);
        $this->assertStringContainsString('data-presentation-default', $script);
        $this->assertStringNotContainsString('El stock siempre se guarda en la unidad base', $vista);
    }
}
