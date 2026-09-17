<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase19031CNotasSalidaOrdenadasTest extends TestCase
{
    public function test_formulario_se_divide_en_origen_datos_productos_y_confirmacion(): void
    {
        $vista = file_get_contents(resource_path('views/notas_salida/create.blade.php'));
        $productos = file_get_contents(resource_path('views/notas_salida/partials/_create_productos.blade.php'));

        $this->assertStringContainsString("'notas_salida.partials._create_origen'", $vista);
        $this->assertStringContainsString("'notas_salida.partials._create_datos'", $vista);
        $this->assertStringContainsString("'notas_salida.partials._create_productos'", $vista);
        $this->assertStringContainsString("'notas_salida.partials._create_confirmacion'", $vista);
        $this->assertStringContainsString('Producto / repisa', $productos);
        $this->assertStringContainsString('Disponibilidad', $productos);
        $this->assertStringContainsString('data-output-excess-reason', $productos);
        $this->assertStringContainsString('data-reserva-orden', $productos);
        $this->assertStringContainsString('output-row-details', $productos);
        $this->assertStringContainsString("asset('js/nota-salida-form.js')", $vista);
        $this->assertStringNotContainsString('<script>', $vista);
    }

    public function test_listado_y_detalle_son_compactos_y_separan_la_verdad_operativa(): void
    {
        $listado = file_get_contents(resource_path('views/notas_salida/index.blade.php'));
        $detalle = file_get_contents(resource_path('views/notas_salida/show.blade.php'));

        $this->assertStringContainsString('output-list-table--compact', $listado);
        $this->assertStringContainsString('Más datos', $listado);
        $this->assertStringContainsString('data-output-detail-tabs', $detalle);
        $this->assertStringContainsString("'notas_salida.partials._show_planificados'", $detalle);
        $this->assertStringContainsString("'notas_salida.partials._show_adicionales'", $detalle);
        $this->assertStringContainsString("'notas_salida.partials._show_devoluciones'", $detalle);
        $this->assertStringContainsString("'notas_salida.partials._show_malogrados'", $detalle);
        $this->assertStringContainsString("asset('js/nota-salida-detail.js')", $detalle);
        $this->assertStringNotContainsString('<script>', $detalle);
    }

    public function test_retornos_confirmados_conservan_diferencia_entre_utilizable_y_malogrado(): void
    {
        $controlador = file_get_contents(app_path('Http/Controllers/NotaSalidaController.php'));
        $modelo = file_get_contents(app_path('Models/NotaSalidaDetalle.php'));
        $devoluciones = file_get_contents(resource_path('views/notas_salida/partials/_show_devoluciones.blade.php'));
        $malogrados = file_get_contents(resource_path('views/notas_salida/partials/_show_malogrados.blade.php'));

        $this->assertStringContainsString('detalles.retornos.notaIngreso', $controlador);
        $this->assertStringContainsString("where('condicion_retorno', 'UTILIZABLE')", $controlador);
        $this->assertStringContainsString("where('condicion_retorno', 'MALOGRADO')", $controlador);
        $this->assertStringContainsString('public function retornos(): HasMany', $modelo);
        $this->assertStringContainsString('Incrementó stock', $devoluciones);
        $this->assertStringContainsString('No incrementó stock', $malogrados);
    }

    public function test_estilos_de_salida_viven_en_inventario_y_no_en_legacy(): void
    {
        $inventario = file_get_contents(public_path('css/hidroil/inventario.css'));
        $legacy = file_get_contents(public_path('css/hidroil-admin.css'));

        $this->assertStringContainsString('19.0.31C — Notas de salida compactas', $inventario);
        $this->assertStringContainsString('.output-lines-table--compact', $inventario);
        $this->assertStringContainsString('.output-detail-tabs', $inventario);
        $this->assertStringNotContainsString('.output-lines-table {', $legacy);
        $this->assertStringNotContainsString('.output-stock-heading {', $legacy);
    }
}
