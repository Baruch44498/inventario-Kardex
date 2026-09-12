<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\Inventario;
use App\Models\OrdenOperacion;
use App\Models\Producto;
use App\Models\Repisa;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Bloque2CotizacionSimpleOrdenVentaTest extends TestCase
{
    use RefreshDatabase;

    private User $comercial;

    private Cliente $cliente;

    private Producto $producto;

    private TipoOrden $ov;

    private TipoOrden $op;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ov = TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OV'],
            ['nombre' => 'Orden de venta', 'estado' => true]
        );
        $this->op = TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OP'],
            ['nombre' => 'Orden de producción', 'estado' => true]
        );
        $this->comercial = $this->usuarioConRol('COMERCIAL_LOGISTICA', 'ventas_bloque_2');
        [$this->cliente, $this->producto] = $this->catalogosComerciales();
    }

    public function test_crea_y_edita_una_cotizacion_simple_forzando_orden_de_venta(): void
    {
        $this->actingAs($this->comercial)
            ->post(route('cotizaciones-cliente.store'), $this->datosCotizacion([
                'tipo_orden_id' => $this->op->id,
                'vehiculo_id' => 999999,
            ]))
            ->assertRedirect();

        $cotizacion = CotizacionCliente::query()->with('detalles')->firstOrFail();

        $this->assertSame($this->ov->id, $cotizacion->tipo_orden_id);
        $this->assertNull($cotizacion->vehiculo_id);
        $this->assertNull($cotizacion->proforma_id);
        $this->assertSame('ABIERTA', $cotizacion->estado);
        $this->assertCount(1, $cotizacion->detalles);
        $this->assertNull($cotizacion->detalles->first()->componente_id);
        $this->assertEquals(250, (float) $cotizacion->subtotal);
        $this->assertEquals(45, (float) $cotizacion->impuesto);
        $this->assertEquals(295, (float) $cotizacion->total);
        $this->assertDatabaseCount('cotizacion_componentes', 0);
        $this->assertDatabaseCount('cotizacion_presupuestos', 0);

        $this->actingAs($this->comercial)
            ->put(route('cotizaciones-cliente.update', $cotizacion), $this->datosCotizacion([
                'descripcion_trabajo' => 'Venta simple actualizada',
                'detalles' => [[
                    'producto_id' => $this->producto->id,
                    'cantidad' => 3,
                    'precio_unitario' => 100,
                    'igv_modo' => 'INCLUIDO',
                ]],
            ]))
            ->assertRedirect(route('cotizaciones-cliente.show', $cotizacion));

        $cotizacion->refresh()->load('detalles');
        $this->assertSame('Venta simple actualizada', $cotizacion->descripcion_trabajo);
        $this->assertCount(1, $cotizacion->detalles);
        $this->assertNull($cotizacion->detalles->first()->componente_id);
        $this->assertDatabaseCount('cotizacion_componentes', 0);
        $this->assertDatabaseCount('cotizacion_presupuestos', 0);
    }

    public function test_cierra_y_convierte_exclusivamente_en_ov_sin_estructura_productiva(): void
    {
        $cotizacion = $this->crearCotizacion();

        $this->actingAs($this->comercial)
            ->post(route('cotizaciones-cliente.convertir-orden', $cotizacion), [
                'fecha_apertura' => now()->toDateString(),
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('ordenes_operacion', 0);

        $this->actingAs($this->comercial)
            ->patch(route('cotizaciones-cliente.cerrar', $cotizacion))
            ->assertRedirect(route('cotizaciones-cliente.show', $cotizacion));

        $this->actingAs($this->comercial)
            ->post(route('cotizaciones-cliente.convertir-orden', $cotizacion), [
                'fecha_apertura' => now()->toDateString(),
                'tipo_orden_id' => $this->op->id,
            ])
            ->assertRedirect(route('cotizaciones-cliente.show', $cotizacion));

        $orden = OrdenOperacion::query()->with('tipoOrden')->firstOrFail();
        $cotizacion->refresh();

        $this->assertSame('OV', $orden->tipoOrden->codigo);
        $this->assertStringStartsWith('OV-', $orden->codigo_orden);
        $this->assertSame($cotizacion->id, $orden->cotizacion_cliente_id);
        $this->assertSame($orden->id, $cotizacion->orden_operacion_id);
        $this->assertSame('CONVERTIDA_EN_ORDEN', $cotizacion->estado);
        $this->assertNull($orden->orden_padre_id);
        $this->assertDatabaseCount('ordenes_operacion', 1);
        $this->assertDatabaseCount('cotizacion_componentes', 0);
        $this->assertDatabaseCount('cotizacion_presupuestos', 0);

        $this->actingAs($this->comercial)
            ->post(route('cotizaciones-cliente.convertir-orden', $cotizacion), [
                'fecha_apertura' => now()->toDateString(),
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('ordenes_operacion', 1);
    }

    public function test_versiona_y_anula_sin_copiar_componentes_ni_presupuestos(): void
    {
        $cotizacion = $this->crearCotizacion();

        $this->actingAs($this->comercial)
            ->patch(route('cotizaciones-cliente.cerrar', $cotizacion));

        $this->actingAs($this->comercial)
            ->post(route('cotizaciones-cliente.version', $cotizacion))
            ->assertRedirect();

        $versionDos = CotizacionCliente::query()
            ->where('codigo_base', $cotizacion->codigo_base)
            ->where('version', 2)
            ->with('detalles')
            ->firstOrFail();

        $this->assertSame('ABIERTA', $versionDos->estado);
        $this->assertCount(1, $versionDos->detalles);
        $this->assertNull($versionDos->detalles->first()->componente_id);
        $this->assertDatabaseCount('cotizacion_componentes', 0);
        $this->assertDatabaseCount('cotizacion_presupuestos', 0);

        $this->actingAs($this->comercial)
            ->patch(route('cotizaciones-cliente.anular', $versionDos), [
                'motivo_anulacion' => 'El cliente cambió el pedido.',
            ])
            ->assertRedirect(route('cotizaciones-cliente.show', $versionDos));

        $this->assertSame('ANULADA', $versionDos->fresh()->estado);
    }

    public function test_el_listado_y_detalle_solo_exponen_ordenes_de_venta(): void
    {
        $cotizacion = $this->crearCotizacion();
        $this->actingAs($this->comercial)
            ->patch(route('cotizaciones-cliente.cerrar', $cotizacion));
        $this->actingAs($this->comercial)
            ->post(route('cotizaciones-cliente.convertir-orden', $cotizacion), [
                'fecha_apertura' => now()->toDateString(),
            ]);

        $ordenProduccion = OrdenOperacion::query()->create([
            'tipo_orden_id' => $this->op->id,
            'cliente_id' => $this->cliente->id,
            'codigo_orden' => 'OP-999-26',
            'numero_correlativo' => 999,
            'anio' => 2026,
            'fecha_apertura' => now()->toDateString(),
            'descripcion' => 'Orden heredada oculta',
            'estado' => 'ABIERTA',
            'creado_por' => $this->comercial->id,
        ]);

        $this->actingAs($this->comercial)
            ->get(route('ordenes-operacion.index'))
            ->assertOk()
            ->assertSee('Órdenes de Venta')
            ->assertDontSee($ordenProduccion->codigo_orden);

        $this->actingAs($this->comercial)
            ->get(route('ordenes-operacion.show', $ordenProduccion))
            ->assertNotFound();

        $this->assertFalse(Route::has('ordenes-operacion.avances.store'));
        $this->assertFalse(Route::has('ordenes-operacion.materiales-requeridos.store'));
        $this->assertFalse(Route::has('ordenes-operacion.costos-directos.store'));
        $this->assertFalse(Route::has('ordenes-operacion.reservas-materiales.store'));
    }

    private function crearCotizacion(): CotizacionCliente
    {
        $this->actingAs($this->comercial)
            ->post(route('cotizaciones-cliente.store'), $this->datosCotizacion())
            ->assertRedirect();

        return CotizacionCliente::query()->firstOrFail();
    }

    private function datosCotizacion(array $cambios = []): array
    {
        return array_replace_recursive([
            'cliente_id' => $this->cliente->id,
            'tipo_orden_id' => $this->ov->id,
            'cliente_direccion_id' => null,
            'vehiculo_id' => null,
            'descripcion_trabajo' => 'Venta de productos al cliente',
            'fecha_emision' => now()->toDateString(),
            'fecha_validez' => now()->addDays(15)->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => null,
            'condiciones_pago' => 'Contado',
            'condiciones_entrega' => 'Entrega coordinada',
            'observacion' => 'Cotización simple de prueba',
            'detalles' => [[
                'producto_id' => $this->producto->id,
                'cantidad' => 2,
                'precio_unitario' => 125,
                'igv_modo' => 'AGREGAR',
            ]],
        ], $cambios);
    }

    private function catalogosComerciales(): array
    {
        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );
        $cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20987654321',
            'ruc' => '20987654321',
            'razon_social' => 'Cliente Bloque 2 SAC',
            'estado' => true,
        ]);
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $repisa = Repisa::query()->create([
            'codigo' => 'R-B02',
            'descripcion' => 'Repisa de prueba Bloque 2',
            'estado' => true,
        ]);
        $producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'PRD-B02',
            'descripcion' => 'Producto de venta Bloque 2',
            'estado' => true,
        ]);
        Inventario::query()->create([
            'producto_id' => $producto->id,
            'repisa_id' => $repisa->id,
            'stock_actual' => 25,
            'stock_minimo' => 5,
            'stock_maximo' => 50,
            'costo_promedio_soles' => 100,
        ]);

        return [$cliente, $producto];
    }

    private function usuarioConRol(string $codigoRol, string $username): User
    {
        return User::query()->create([
            'role_id' => Role::query()->where('codigo', $codigoRol)->firstOrFail()->id,
            'username' => $username,
            'email' => $username . '@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
    }
}
