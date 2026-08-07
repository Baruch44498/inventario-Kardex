<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase17071NotaIngresoVistaRegressionTest extends TestCase
{
    public function test_formulario_de_ingreso_conserva_terminologia_y_origenes_de_entrada(): void
    {
        $vista = file_get_contents(resource_path('views/notas_ingreso/create.blade.php'));

        $this->assertStringContainsString('Nueva nota de ingreso', $vista);
        $this->assertStringContainsString('Devolución de herramienta / uso temporal', $vista);
        $this->assertStringContainsString('Reposición de préstamo de Proforma', $vista);
        $this->assertStringContainsString('motivo_ingreso', $vista);

        $this->assertStringNotContainsString('Nueva nota de salida', $vista);
        $this->assertStringNotContainsString('motivo_salida', $vista);
    }

    public function test_detalle_de_ingreso_no_usa_terminologia_de_salida(): void
    {
        $vista = file_get_contents(resource_path('views/notas_ingreso/show.blade.php'));

        $this->assertStringContainsString('Cantidad recibida:', $vista);
        $this->assertStringContainsString('Productos ingresados', $vista);
        $this->assertStringContainsString('motivo_ingreso', $vista);
        $this->assertStringContainsString('fecha_ingreso', $vista);

        $this->assertStringNotContainsString('Cantidad entregada:', $vista);
        $this->assertStringNotContainsString('Productos entregados', $vista);
        $this->assertStringNotContainsString('motivo_salida', $vista);
        $this->assertStringNotContainsString('fecha_salida', $vista);
    }
}
