<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase19031DKardexMovimientosAlertasCompactosTest extends TestCase
{
    public function test_kardex_reduce_columnas_y_conserva_valorizacion_en_detalle(): void
    {
        $vista = file_get_contents(resource_path('views/kardex/index.blade.php'));

        $this->assertStringContainsString('inventory-flow-table--kardex', $vista);
        $this->assertStringContainsString('Producto / ubicación', $vista);
        $this->assertStringContainsString('Movimiento físico', $vista);
        $this->assertStringContainsString('Documento origen', $vista);
        $this->assertStringContainsString('Costo promedio anterior', $vista);
        $this->assertStringContainsString('Costo promedio nuevo', $vista);
        $this->assertStringContainsString('Saldo valorizado', $vista);
        $this->assertStringContainsString(':colspan="6"', $vista);
        $this->assertStringNotContainsString('data-table--kardex', $vista);
    }

    public function test_movimientos_reduce_nueve_columnas_y_corrige_su_marcado(): void
    {
        $vista = file_get_contents(resource_path('views/movimientos/index.blade.php'));

        $this->assertStringContainsString('inventory-flow-table--movements', $vista);
        $this->assertStringContainsString('Cantidad / stock', $vista);
        $this->assertStringContainsString('Documento origen', $vista);
        $this->assertStringContainsString('Usuario de registro', $vista);
        $this->assertStringContainsString(':colspan="5"', $vista);
        $this->assertStringNotContainsString('data-table--responsive}"', $vista);
        $this->assertStringNotContainsString('data-table--movements', $vista);
    }

    public function test_alertas_conserva_reposicion_masiva_y_extrae_javascript(): void
    {
        $vista = file_get_contents(resource_path('views/alertas/index.blade.php'));
        $script = file_get_contents(public_path('js/alertas-stock.js'));

        $this->assertStringContainsString('inventory-alert-table', $vista);
        $this->assertStringContainsString('data-alert-bulk-form', $vista);
        $this->assertStringContainsString('data-select-visible-alerts', $vista);
        $this->assertStringContainsString('Estado / reposición', $vista);
        $this->assertStringContainsString('Responsable', $vista);
        $this->assertStringContainsString("asset('js/alertas-stock.js')", $vista);
        $this->assertStringNotContainsString('<script>', $vista);
        $this->assertStringContainsString("querySelector('[data-alert-bulk-form]')", $script);
        $this->assertStringContainsString('master.indeterminate', $script);
    }

    public function test_inventarios_periodicos_compacta_listado_y_detalle_sin_mover_campos(): void
    {
        $listado = file_get_contents(resource_path('views/inventarios_periodicos/index.blade.php'));
        $detalle = file_get_contents(resource_path('views/inventarios_periodicos/show.blade.php'));

        $this->assertStringContainsString('periodic-inventory-list', $listado);
        $this->assertStringContainsString('Conteo / repisa', $listado);
        $this->assertStringContainsString('Ver auditoría del conteo', $listado);
        $this->assertStringContainsString('periodic-inventory-detail', $detalle);
        $this->assertStringContainsString('Diferencia / valor', $detalle);
        $this->assertStringContainsString('Ver costo, ubicación y observación', $detalle);
        $this->assertStringContainsString('name="detalles[{{ $detalle->id }}][stock_contado]"', $detalle);
        $this->assertStringContainsString('name="detalles[{{ $detalle->id }}][observacion]"', $detalle);
    }

    public function test_estilos_del_bloque_estan_en_inventario_y_no_en_legacy(): void
    {
        $inventario = file_get_contents(public_path('css/hidroil/inventario.css'));
        $legacy = file_get_contents(public_path('css/hidroil-admin.css'));

        $this->assertStringContainsString('19.0.31D — Kardex, movimientos, alertas e inventarios periódicos', $inventario);
        $this->assertStringContainsString('.inventory-flow-table', $inventario);
        $this->assertStringContainsString('.inventory-alert-table', $inventario);
        $this->assertStringContainsString('.periodic-inventory-detail', $inventario);
        $this->assertStringNotContainsString('.data-table--kardex {', $legacy);
        $this->assertStringNotContainsString('.data-table--movements {', $legacy);
        $this->assertStringNotContainsString('.data-table--alerts {', $legacy);
    }
}
