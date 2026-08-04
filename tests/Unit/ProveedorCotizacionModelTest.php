<?php

namespace Tests\Unit;

use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\Proveedor;
use PHPUnit\Framework\TestCase;

class ProveedorCotizacionModelTest extends TestCase
{
    public function test_muestra_el_nombre_y_la_ubicacion_del_proveedor(): void
    {
        $proveedor = new Proveedor([
            'razon_social' => 'Proveedor Industrial SAC',
            'nombre_comercial' => 'Proveedor Uno',
            'ciudad' => 'Trujillo',
            'departamento' => 'La Libertad',
        ]);

        $this->assertSame('Proveedor Uno', $proveedor->nombreVisible());
        $this->assertSame('Trujillo, La Libertad', $proveedor->ubicacionVisible());
    }

    public function test_controla_el_estado_y_el_igv_de_la_cotizacion(): void
    {
        $cotizacion = new Cotizacion([
            'estado' => 'REGISTRADA',
            'moneda' => 'USD',
            'impuesto' => 18,
        ]);
        $cotizacion->setRelation('solicitudCompra', null);

        $this->assertTrue($cotizacion->puedeEditar());
        $this->assertTrue($cotizacion->incluyeIgv());
        $this->assertSame('US$', $cotizacion->simboloMoneda());

        $cotizacion->estado = 'SELECCIONADA';

        $this->assertTrue($cotizacion->estaUtilizada());
        $this->assertFalse($cotizacion->puedeEditar());
    }

    public function test_calcula_el_precio_neto_y_su_equivalente_en_soles(): void
    {
        $cotizacion = new Cotizacion([
            'moneda' => 'USD',
            'tipo_cambio' => 3.75,
        ]);

        $detalle = new CotizacionDetalle([
            'precio_unitario' => 20,
            'descuento_porcentaje' => 10,
        ]);
        $detalle->setRelation('cotizacion', $cotizacion);

        $this->assertSame(18.0, $detalle->precioNetoUnitario());
        $this->assertSame(67.5, $detalle->precioEquivalentePen());
    }
}
