<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase19031CierreInventarioCompactoTest extends TestCase
{
    public function test_layout_carga_los_estilos_de_inventario_despues_del_legacy(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $legacy = strpos($layout, "asset('css/hidroil-admin.css')");
        $inventario = strpos($layout, "asset('css/hidroil/inventario.css')");

        $this->assertNotFalse($legacy);
        $this->assertNotFalse($inventario);
        $this->assertGreaterThan($legacy, $inventario);
    }

    public function test_estilos_exclusivos_del_modulo_ya_no_viven_en_el_css_legacy(): void
    {
        $legacy = file_get_contents(public_path('css/hidroil-admin.css'));
        $inventario = file_get_contents(public_path('css/hidroil/inventario.css'));
        $selectores = [
            '.filter-grid--inventory {',
            '.detail-grid--inventory {',
            '.detail-card {',
            '.mini-metric-grid {',
            '.inventory-tools-panel {',
            '.output-cancel-modal {',
        ];

        foreach ($selectores as $selector) {
            $this->assertStringContainsString($selector, $inventario);
            $this->assertStringNotContainsString($selector, $legacy);
        }
    }

    public function test_bloques_a_b_c_y_d_conservan_sus_pruebas_de_regresion(): void
    {
        foreach ([
            'Fase19031AInventarioProductosCompactosTest.php',
            'Fase19031BNotasIngresoOrdenadasTest.php',
            'Fase19031CNotasSalidaOrdenadasTest.php',
            'Fase19031DKardexMovimientosAlertasCompactosTest.php',
        ] as $archivo) {
            $this->assertFileExists(base_path('tests/Feature/'.$archivo));
        }
    }

    public function test_scripts_de_las_pantallas_reordenadas_siguen_externalizados(): void
    {
        foreach ([
            'views/productos/_form.blade.php',
            'views/productos/show.blade.php',
            'views/notas_ingreso/create.blade.php',
            'views/notas_ingreso/show.blade.php',
            'views/notas_salida/create.blade.php',
            'views/notas_salida/show.blade.php',
            'views/alertas/index.blade.php',
        ] as $archivo) {
            $this->assertStringNotContainsString('<script>', file_get_contents(resource_path($archivo)));
        }
    }

    public function test_flujos_criticos_conservan_campos_y_acciones_de_negocio(): void
    {
        $producto = file_get_contents(resource_path('views/productos/_form.blade.php'));
        $ingreso = file_get_contents(resource_path('views/notas_ingreso/partials/_create_productos.blade.php'));
        $salida = file_get_contents(resource_path('views/notas_salida/partials/_create_productos.blade.php'));
        $periodico = file_get_contents(resource_path('views/inventarios_periodicos/show.blade.php'));
        $alertas = file_get_contents(resource_path('views/alertas/index.blade.php'));

        $this->assertStringContainsString('factor_conversion', $producto);
        $this->assertStringContainsString('unidad_recepcion', $ingreso);
        $this->assertStringContainsString('[repisa_id]', $ingreso);
        $this->assertStringContainsString('data-output-quantity', $salida);
        $this->assertStringContainsString('data-output-excess-reason', $salida);
        $this->assertStringContainsString('name="detalles[{{ $detalle->id }}][stock_contado]"', $periodico);
        $this->assertStringContainsString('data-alert-bulk-form', $alertas);
    }
}

