<?php

namespace Tests\Unit;

use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Services\Compras\CalcularCotizacionProveedorService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CalcularCotizacionProveedorServiceTest extends TestCase
{
    private CalcularCotizacionProveedorService $calculador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculador = new CalcularCotizacionProveedorService();
    }

    #[DataProvider('casosIgv')]
    public function test_calcula_los_tres_tratamientos_de_igv(
        string $modoIgv,
        float $baseEsperada,
        float $igvEsperado,
        float $totalEsperado
    ): void {
        [$lineas, $totales] = $this->calculador->calcular([
            $this->linea(precio: 100, igvModo: $modoIgv),
        ]);

        $this->assertEqualsWithDelta($baseEsperada, $lineas[0]['subtotal'], 0.0001);
        $this->assertEqualsWithDelta($igvEsperado, $lineas[0]['impuesto'], 0.0001);
        $this->assertEqualsWithDelta($totalEsperado, $lineas[0]['total'], 0.0001);
        $this->assertEqualsWithDelta($baseEsperada, $totales['subtotal'], 0.0001);
        $this->assertEqualsWithDelta($igvEsperado, $totales['impuesto'], 0.0001);
        $this->assertEqualsWithDelta($totalEsperado, $totales['total'], 0.0001);
    }

    public static function casosIgv(): array
    {
        return [
            'precio ya incluye IGV' => ['INCLUIDO', 84.7458, 15.2542, 100.0],
            'se agrega IGV al precio' => ['AGREGAR', 100.0, 18.0, 118.0],
            'operación sin IGV' => ['NO_APLICA', 100.0, 0.0, 100.0],
        ];
    }

    public function test_aplica_descuento_porcentual_antes_del_igv(): void
    {
        [$lineas, $totales] = $this->calculador->calcular([
            $this->linea(
                precio: 100,
                igvModo: 'AGREGAR',
                descuentoModo: 'APLICAR',
                descuentoTipo: 'PORCENTAJE',
                descuentoValor: 10
            ),
        ]);

        $this->assertSame(10.0, $lineas[0]['descuento_porcentaje']);
        $this->assertEqualsWithDelta(90.0, $totales['subtotal'], 0.0001);
        $this->assertEqualsWithDelta(16.2, $totales['impuesto'], 0.0001);
        $this->assertEqualsWithDelta(106.2, $totales['total'], 0.0001);
    }

    public function test_no_vuelve_a_aplicar_un_descuento_ya_incluido(): void
    {
        [, $totales] = $this->calculador->calcular([
            $this->linea(
                precio: 90,
                igvModo: 'AGREGAR',
                descuentoModo: 'INCLUIDO',
                descuentoTipo: 'PORCENTAJE',
                descuentoValor: 10
            ),
        ]);

        $this->assertEqualsWithDelta(90.0, $totales['subtotal'], 0.0001);
        $this->assertEqualsWithDelta(16.2, $totales['impuesto'], 0.0001);
        $this->assertEqualsWithDelta(106.2, $totales['total'], 0.0001);
    }

    public function test_un_precio_sin_descuento_detallado_se_conserva_completo(): void
    {
        [$lineas, $totales] = $this->calculador->calcular([
            $this->linea(
                precio: 90,
                igvModo: 'AGREGAR',
                descuentoModo: 'SIN_DESCUENTO',
                descuentoTipo: 'PORCENTAJE',
                descuentoValor: 25
            ),
        ]);

        $this->assertEqualsWithDelta(90.0, $lineas[0]['subtotal'], 0.0001);
        $this->assertEqualsWithDelta(16.2, $lineas[0]['impuesto'], 0.0001);
        $this->assertEqualsWithDelta(106.2, $totales['total'], 0.0001);
    }

    public function test_los_registros_anteriores_no_exponen_un_descuento_desconocido(): void
    {
        $cotizacion = new Cotizacion([
            'descuento_global_modo' => 'INCLUIDO',
            'descuento_global_tipo' => 'PORCENTAJE',
            'descuento_global_valor' => 10,
        ]);
        $detalle = new CotizacionDetalle([
            'descuento_modo' => 'INCLUIDO',
            'descuento_tipo' => 'PORCENTAJE',
            'descuento_valor' => 10,
        ]);

        $this->assertSame(
            'Precio final informado por el proveedor',
            $cotizacion->descuentoGlobalVisible()
        );
        $this->assertSame('Precio final informado', $detalle->descuentoVisible());
    }

    public function test_aplica_descuento_general_y_reduce_el_igv_proporcionalmente(): void
    {
        [, $totales] = $this->calculador->calcular(
            [$this->linea(precio: 100, igvModo: 'AGREGAR')],
            'APLICAR',
            'PORCENTAJE',
            10
        );

        $this->assertEqualsWithDelta(100.0, $totales['subtotal'], 0.0001);
        $this->assertEqualsWithDelta(10.0, $totales['descuento_global_monto'], 0.0001);
        $this->assertEqualsWithDelta(16.2, $totales['impuesto'], 0.0001);
        $this->assertEqualsWithDelta(106.2, $totales['total'], 0.0001);
    }

    public function test_el_descuento_general_se_refleja_en_el_precio_historico(): void
    {
        $cotizacion = new Cotizacion([
            'moneda' => 'USD',
            'tipo_cambio' => 3.75,
            'subtotal' => 100,
            'descuento_global_monto' => 10,
        ]);

        $detalle = new CotizacionDetalle([
            'cantidad' => 1,
            'total' => 118,
        ]);
        $detalle->setRelation('cotizacion', $cotizacion);

        $this->assertEqualsWithDelta(106.2, $detalle->precioFinalUnitario(), 0.0001);
        $this->assertEqualsWithDelta(398.25, $detalle->precioEquivalentePen(), 0.0001);
    }

    public function test_rechaza_un_descuento_mayor_que_el_precio(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculador->calcular([
            $this->linea(
                precio: 50,
                descuentoModo: 'APLICAR',
                descuentoTipo: 'MONTO',
                descuentoValor: 60
            ),
        ]);
    }

    private function linea(
        float $precio,
        string $igvModo = 'AGREGAR',
        string $descuentoModo = 'SIN_DESCUENTO',
        ?string $descuentoTipo = null,
        float $descuentoValor = 0
    ): array {
        return [
            'producto_id' => 1,
            'cantidad' => 1,
            'precio_unitario' => $precio,
            'descuento_modo' => $descuentoModo,
            'descuento_tipo' => $descuentoTipo,
            'descuento_valor' => $descuentoValor,
            'igv_modo' => $igvModo,
            'marca_ofertada' => null,
            'observacion' => null,
        ];
    }
}
