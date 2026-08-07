<?php

namespace Tests\Feature;

use Tests\TestCase;

class Fase17062NotaSalidaShowRegressionTest extends TestCase
{
    public function test_vista_de_nota_salida_no_conserva_bloque_antiguo_con_variables_no_definidas(): void
    {
        $contenido = file_get_contents(resource_path('views/notas_salida/show.blade.php'));

        $this->assertIsString($contenido);

        // Regresión original: estas variables sueltas provocaban el error 500.
        $this->assertStringNotContainsString('{{ $cliente }}', $contenido);
        $this->assertStringNotContainsString('{{ $vehiculo }}', $contenido);

        // 17.0.6.3 sustituyó la composición visual antigua por el grid de documento.
        $this->assertSame(1, substr_count($contenido, 'entry-document-grid output-show-grid'));
        $this->assertStringNotContainsString('entry-show-grid output-show-grid', $contenido);

        // Cliente y vehículo se resuelven ahora desde sus relaciones reales.
        $this->assertStringContainsString('$nota->proforma?->cliente?->razon_social', $contenido);
        $this->assertStringContainsString('$nota->ordenOperacion?->cliente?->razon_social', $contenido);
        $this->assertStringContainsString('$nota->ordenOperacion?->vehiculo', $contenido);
    }
}
