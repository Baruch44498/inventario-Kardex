<?php

namespace Tests\Unit;

use App\Services\Ventas\CalcularProformaService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CalcularProformaServiceTest extends TestCase
{
    private CalcularProformaService $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = new CalcularProformaService();
    }

    public function test_sugiere_precio_segun_costo_y_tipo_de_cliente(): void
    {
        $resultado = $this->servicio->calcular([[
            'cantidad' => 2,
            'costo_referencia' => 100,
            'margen_sugerido' => 20,
            'igv_modo' => 'AGREGAR',
        ]]);

        $linea = $resultado['detalles'][0];

        $this->assertSame(120.0, $linea['precio_sugerido']);
        $this->assertSame(120.0, $linea['precio_unitario']);
        $this->assertFalse($linea['precio_ajustado']);
        $this->assertSame(240.0, $linea['subtotal']);
        $this->assertSame(43.2, $linea['impuesto']);
        $this->assertSame(283.2, $linea['total']);
    }

    public function test_el_precio_negociado_reemplaza_al_sugerido(): void
    {
        $resultado = $this->servicio->calcular([[
            'cantidad' => 1,
            'costo_referencia' => 100,
            'margen_sugerido' => 20,
            'precio_unitario' => 110,
            'igv_modo' => 'AGREGAR',
        ]]);

        $linea = $resultado['detalles'][0];

        $this->assertSame(120.0, $linea['precio_sugerido']);
        $this->assertSame(110.0, $linea['precio_unitario']);
        $this->assertTrue($linea['precio_ajustado']);
        $this->assertSame(129.8, $linea['total']);
    }

    #[DataProvider('casosIgv')]
    public function test_calcula_los_tres_modos_de_igv(
        string $modo,
        float $subtotal,
        float $igv,
        float $total
    ): void {
        $resultado = $this->servicio->calcular([[
            'cantidad' => 1,
            'precio_unitario' => $modo === 'INCLUIDO' ? 118 : 100,
            'igv_modo' => $modo,
        ]]);

        $this->assertEqualsWithDelta(
            $subtotal,
            $resultado['totales']['subtotal'],
            0.0001
        );
        $this->assertEqualsWithDelta(
            $igv,
            $resultado['totales']['impuesto'],
            0.0001
        );
        $this->assertEqualsWithDelta(
            $total,
            $resultado['totales']['total'],
            0.0001
        );
    }

    public static function casosIgv(): array
    {
        return [
            'incluido' => ['INCLUIDO', 100.0, 18.0, 118.0],
            'agregado' => ['AGREGAR', 100.0, 18.0, 118.0],
            'no aplica' => ['NO_APLICA', 100.0, 0.0, 100.0],
        ];
    }

    public function test_suma_lineas_con_tratamientos_distintos(): void
    {
        $resultado = $this->servicio->calcular([
            [
                'cantidad' => 1,
                'precio_unitario' => 100,
                'igv_modo' => 'AGREGAR',
            ],
            [
                'cantidad' => 2,
                'precio_unitario' => 50,
                'igv_modo' => 'NO_APLICA',
            ],
        ]);

        $this->assertSame(200.0, $resultado['totales']['subtotal']);
        $this->assertSame(18.0, $resultado['totales']['impuesto']);
        $this->assertSame(218.0, $resultado['totales']['total']);
    }

    public function test_rechaza_una_linea_sin_precio_ni_costo(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->servicio->calcular([[
            'cantidad' => 1,
            'igv_modo' => 'AGREGAR',
        ]]);
    }
}
