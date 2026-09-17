<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase19031AInventarioProductosCompactosTest extends TestCase
{
    public function test_inventario_reduce_la_tabla_a_cinco_columnas_principales(): void
    {
        $vista = file_get_contents(resource_path('views/inventario/index.blade.php'));

        preg_match(
            '/<table class="[^"]*inventory-compact-table[^"]*">.*?<thead>(.*?)<\/thead>/s',
            $vista,
            $cabecera
        );

        $this->assertArrayHasKey(1, $cabecera);
        $this->assertSame(5, substr_count($cabecera[1], '<th'));
        $this->assertStringContainsString('Producto', $cabecera[1]);
        $this->assertStringContainsString('Disponibilidad', $cabecera[1]);
        $this->assertStringContainsString('Ubicación', $cabecera[1]);
        $this->assertStringContainsString('Alerta', $cabecera[1]);
        $this->assertStringContainsString('Acción', $cabecera[1]);
        $this->assertStringContainsString('inventory-stock-cluster', $vista);
        $this->assertStringContainsString('Físico', $vista);
        $this->assertStringContainsString('Reservado', $vista);
        $this->assertStringContainsString('Disponible', $vista);
        $this->assertStringContainsString('Mínimo', $vista);
        $this->assertStringContainsString('Objetivo', $vista);
        $this->assertStringContainsString(':colspan="5"', $vista);
    }

    public function test_detalle_producto_se_divide_en_cinco_pestanas_y_parciales(): void
    {
        $vista = file_get_contents(resource_path('views/productos/show.blade.php'));

        foreach (['existencias', 'presentaciones', 'ubicaciones', 'precios', 'movimientos'] as $tab) {
            $this->assertStringContainsString("'{$tab}' => [", $vista);
            $this->assertStringContainsString("'_show_{$tab}'", $vista);
            $this->assertFileExists(resource_path("views/productos/partials/_show_{$tab}.blade.php"));
        }

        $this->assertStringContainsString('role="tablist"', $vista);
        $this->assertStringContainsString('role="tabpanel"', $vista);
        $this->assertStringContainsString("asset('js/product-detail-tabs.js')", $vista);
    }

    public function test_formulario_producto_externaliza_su_javascript_sin_perder_conversiones(): void
    {
        $vista = file_get_contents(resource_path('views/productos/_form.blade.php'));
        $script = file_get_contents(public_path('js/product-form.js'));

        $this->assertStringContainsString("asset('js/product-form.js')", $vista);
        $this->assertStringNotContainsString('<script>', $vista);
        $this->assertStringContainsString('data-product-presentations', $vista);
        $this->assertStringContainsString('factor_conversion', $vista);
        $this->assertStringContainsString('1 ${name} = ${factor.toFixed(2)} ${baseUnit()}', $script);
        $this->assertStringContainsString("['MTS', 'GLN', 'LT']", $script);
        $this->assertStringContainsString('data-presentation-default', $script);
    }

    public function test_estilos_de_inventario_se_separan_del_css_general(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $cssGeneral = file_get_contents(public_path('css/hidroil-admin.css'));
        $cssInventario = file_get_contents(public_path('css/hidroil/inventario.css'));

        $this->assertStringContainsString("asset('css/hidroil/inventario.css')", $layout);
        $this->assertStringNotContainsString('.inventory-planning-table', $cssGeneral);
        $this->assertStringNotContainsString('.product-presentations', $cssGeneral);
        $this->assertStringContainsString('.inventory-compact-table', $cssInventario);
        $this->assertStringContainsString('.product-detail-tabs', $cssInventario);
        $this->assertStringContainsString('.product-presentations', $cssInventario);
    }

    public function test_pestanas_admiten_hash_y_navegacion_por_teclado(): void
    {
        $script = file_get_contents(public_path('js/product-detail-tabs.js'));

        $this->assertStringContainsString('window.location.hash', $script);
        $this->assertStringContainsString('window.history.replaceState', $script);
        $this->assertStringContainsString("'ArrowRight'", $script);
        $this->assertStringContainsString("'ArrowLeft'", $script);
        $this->assertStringContainsString("'Home'", $script);
        $this->assertStringContainsString("'End'", $script);
        $this->assertStringContainsString("'hashchange'", $script);
    }

    public function test_datos_sensibles_del_detalle_respetan_permisos_y_limites(): void
    {
        $controlador = file_get_contents(app_path('Http/Controllers/ProductoController.php'));

        $this->assertStringContainsString("puede('compras.gestionar')", $controlador);
        $this->assertStringContainsString("puede('movimientos.ver')", $controlador);
        $this->assertStringContainsString('->limit(8)', $controlador);
        $this->assertStringContainsString('->limit(10)', $controlador);
    }
}
