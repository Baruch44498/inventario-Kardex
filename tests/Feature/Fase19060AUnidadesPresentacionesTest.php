<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\UnidadMedida;
use App\Services\Compras\CalcularCotizacionProveedorService;
use App\Services\Productos\NormalizarPresentacionProductoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Fase19060AUnidadesPresentacionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_rollo_se_normaliza_a_metros_y_costo_unitario_base(): void
    {
        $metro = UnidadMedida::query()->create([
            'codigo' => 'M',
            'nombre' => 'Metro',
            'estado' => true,
        ]);
        $manguera = Producto::query()->create([
            'unidad_medida_id' => $metro->id,
            'codigo' => 'MANG-001',
            'descripcion' => 'Manguera hidráulica',
            'permite_fraccionamiento' => true,
            'estado' => true,
        ]);
        $rollo = ProductoPresentacion::query()->create([
            'producto_id' => $manguera->id,
            'nombre' => 'Rollo',
            'factor_conversion' => 100,
            'es_predeterminada' => true,
            'estado' => true,
        ]);

        $lineas = app(NormalizarPresentacionProductoService::class)
            ->normalizarLineasCotizacion([[
                'producto_id' => $manguera->id,
                'producto_presentacion_id' => $rollo->id,
                'cantidad' => 1,
                'precio_unitario' => 500,
                'descuento_modo' => 'SIN_DESCUENTO',
                'descuento_tipo' => null,
                'descuento_valor' => null,
                'igv_modo' => 'NO_APLICA',
            ]]);

        $this->assertSame(100.0, $lineas[0]['cantidad']);
        $this->assertSame(5.0, $lineas[0]['precio_unitario']);
        $this->assertSame(1.0, $lineas[0]['cantidad_presentacion']);
        $this->assertSame(100.0, $lineas[0]['factor_conversion']);
        $this->assertSame(500.0, $lineas[0]['precio_presentacion']);
        $this->assertSame('Rollo', $lineas[0]['presentacion_nombre']);

        [, $totales] = app(CalcularCotizacionProveedorService::class)
            ->calcular($lineas);

        $this->assertSame(500.0, $totales['total']);
    }

    public function test_producto_fraccionable_admite_cortes_exactos(): void
    {
        $producto = new Producto(['permite_fraccionamiento' => true]);

        $this->assertTrue($producto->cantidadAdmitida(1.5));
        $this->assertTrue($producto->cantidadAdmitida(3.2));
        $this->assertTrue($producto->cantidadAdmitida(0.75));
    }

    public function test_producto_indivisible_rechaza_conversion_fraccionaria(): void
    {
        $unidad = UnidadMedida::query()->create([
            'codigo' => 'UND',
            'nombre' => 'Unidad',
            'estado' => true,
        ]);
        $producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'UND-001',
            'descripcion' => 'Producto indivisible',
            'permite_fraccionamiento' => false,
            'estado' => true,
        ]);
        $paquete = ProductoPresentacion::query()->create([
            'producto_id' => $producto->id,
            'nombre' => 'Paquete especial',
            'factor_conversion' => 2.5,
            'estado' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(NormalizarPresentacionProductoService::class)
            ->normalizarLineasCotizacion([[
                'producto_id' => $producto->id,
                'producto_presentacion_id' => $paquete->id,
                'cantidad' => 1,
                'precio_unitario' => 10,
            ]]);
    }
}
