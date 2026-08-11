<?php

namespace Tests\Feature;

use App\Models\Cotizacion;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\Role;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase17121CotizacionesMultiplesParcialesRequerimientoTest extends TestCase
{
    use RefreshDatabase;

    private User $logistica;
    private Requisicion $requerimiento;
    private Producto $productoA;
    private Producto $productoB;
    private Proveedor $proveedorA;
    private Proveedor $proveedorB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistica = User::query()->create([
            'role_id' => Role::query()->where('codigo', 'COMERCIAL_LOGISTICA')->firstOrFail()->id,
            'username' => 'logistica_17121',
            'email' => 'logistica_17121@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);

        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );

        $this->productoA = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'MAT-17121-A',
            'descripcion' => 'Retén cotizable por varios proveedores',
            'estado' => true,
        ]);
        $this->productoB = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'MAT-17121-B',
            'descripcion' => 'Válvula cotizable por proveedor distinto',
            'estado' => true,
        ]);

        $this->requerimiento = Requisicion::query()->create([
            'codigo' => 'REQ-17121-26',
            'fecha_solicitud' => now()->toDateString(),
            'origen' => 'REPOSICION',
            'descripcion' => 'Requerimiento con compras distribuidas.',
            'prioridad' => 'ALTA',
            'estado' => 'COTIZANDO',
            'solicitado_por' => $this->logistica->id,
            'enviado_por' => $this->logistica->id,
            'enviado_en' => now(),
            'recibido_por' => $this->logistica->id,
            'recibido_en' => now(),
        ]);

        $this->requerimiento->detalles()->createMany([
            [
                'producto_id' => $this->productoA->id,
                'cantidad_solicitada' => 10,
                'cantidad_sugerida' => 10,
                'cantidad_atendida' => 0,
            ],
            [
                'producto_id' => $this->productoB->id,
                'cantidad_solicitada' => 6,
                'cantidad_sugerida' => 6,
                'cantidad_atendida' => 0,
            ],
        ]);

        $this->proveedorA = Proveedor::query()->create([
            'ruc' => '20617121001',
            'razon_social' => 'Proveedor A Fase 17.1.2.1 SAC',
            'nombre_comercial' => 'Proveedor A 17121',
            'estado' => true,
        ]);
        $this->proveedorB = Proveedor::query()->create([
            'ruc' => '20617121002',
            'razon_social' => 'Proveedor B Fase 17.1.2.1 SAC',
            'nombre_comercial' => 'Proveedor B 17121',
            'estado' => true,
        ]);
    }

    public function test_formulario_desde_proveedor_puede_precargar_solo_lineas_relacionadas(): void
    {
        $detalleA = $this->requerimiento->detalles()->where('producto_id', $this->productoA->id)->firstOrFail();

        $respuesta = $this->actingAs($this->logistica)
            ->get(route('cotizaciones-proveedor.create', [
                'requisicion_id' => $this->requerimiento->id,
                'proveedor_id' => $this->proveedorA->id,
                'detalle_ids' => [$detalleA->id],
            ]))
            ->assertOk()
            ->assertSee('cotización parcial permitida');

        $lineas = $respuesta->viewData('lineasRequisicion');
        $this->assertCount(1, $lineas);
        $this->assertSame($detalleA->id, $lineas[0]['requisicion_detalle_id']);
        $this->assertSame($this->productoA->id, $lineas[0]['producto_id']);
    }

    public function test_cotizacion_puede_cubrir_solo_un_producto_y_una_cantidad_parcial(): void
    {
        $this->registrarCotizacion($this->proveedorA, $this->productoA, 4, 32.50);

        $cotizacion = Cotizacion::query()->latest('id')->firstOrFail();
        $linea = $cotizacion->detalles()->firstOrFail();
        $detalleRequerido = $this->requerimiento->detalles()->where('producto_id', $this->productoA->id)->firstOrFail();

        $this->assertSame($this->requerimiento->id, $cotizacion->requisicion_id);
        $this->assertSame($this->proveedorA->id, $cotizacion->proveedor_id);
        $this->assertSame($detalleRequerido->id, $linea->requisicion_detalle_id);
        $this->assertEquals(4, (float) $linea->cantidad);
        $this->assertDatabaseCount('cotizacion_detalles', 1);
    }

    public function test_misma_linea_del_requerimiento_admite_cotizaciones_de_varios_proveedores(): void
    {
        $this->registrarCotizacion($this->proveedorA, $this->productoA, 10, 35);
        $this->registrarCotizacion($this->proveedorB, $this->productoA, 8, 31);

        $detalleRequerido = $this->requerimiento->detalles()->where('producto_id', $this->productoA->id)->firstOrFail();

        $this->assertSame(
            2,
            $detalleRequerido->cotizacionDetalles()->count()
        );
        $this->assertSame(
            2,
            Cotizacion::query()->where('requisicion_id', $this->requerimiento->id)->count()
        );
    }

    public function test_backend_vincula_linea_por_producto_aunque_no_llegue_requisicion_detalle_id(): void
    {
        $this->registrarCotizacion($this->proveedorB, $this->productoB, 6, 100, false);

        $linea = Cotizacion::query()->latest('id')->firstOrFail()->detalles()->firstOrFail();
        $detalleRequerido = $this->requerimiento->detalles()->where('producto_id', $this->productoB->id)->firstOrFail();

        $this->assertSame($detalleRequerido->id, $linea->requisicion_detalle_id);
    }

    public function test_detalle_del_requerimiento_muestra_ofertas_por_producto_y_cobertura_por_documento(): void
    {
        $this->registrarCotizacion($this->proveedorA, $this->productoA, 10, 35);
        $this->registrarCotizacion($this->proveedorB, $this->productoB, 6, 100);

        $this->actingAs($this->logistica)
            ->get(route('requerimientos-compra.show', $this->requerimiento))
            ->assertOk()
            ->assertSee('Cotizaciones recibidas')
            ->assertSee('1 oferta')
            ->assertSee('1/2')
            ->assertSee($this->proveedorA->nombreVisible())
            ->assertSee($this->proveedorB->nombreVisible());
    }

    private function registrarCotizacion(
        Proveedor $proveedor,
        Producto $producto,
        float $cantidad,
        float $precio,
        bool $enviarDetalleId = true
    ): void {
        $detalleReq = $this->requerimiento->detalles()->where('producto_id', $producto->id)->firstOrFail();
        $detalle = [
            'producto_id' => $producto->id,
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'descuento_modo' => 'SIN_DESCUENTO',
            'descuento_tipo' => null,
            'descuento_valor' => null,
            'igv_modo' => 'NO_APLICA',
            'marca_ofertada' => null,
            'observacion' => null,
        ];
        if ($enviarDetalleId) {
            $detalle['requisicion_detalle_id'] = $detalleReq->id;
        }

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-proveedor.store'), [
                'proveedor_id' => $proveedor->id,
                'requisicion_id' => $this->requerimiento->id,
                'numero_documento' => 'EXT-'.uniqid(),
                'fecha_cotizacion' => now()->toDateString(),
                'fecha_validez' => null,
                'moneda' => 'PEN',
                'tipo_cambio' => null,
                'descuento_global_modo' => 'SIN_DESCUENTO',
                'descuento_global_tipo' => null,
                'descuento_global_valor' => null,
                'condiciones_pago' => null,
                'condiciones_entrega' => null,
                'observacion' => null,
                'detalles' => [$detalle],
            ])
            ->assertRedirect();
    }
}
