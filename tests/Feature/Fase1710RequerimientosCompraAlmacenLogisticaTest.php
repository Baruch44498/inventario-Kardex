<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\Inventario;
use App\Models\MaterialRequeridoOrden;
use App\Models\OrdenOperacion;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Repisa;
use App\Models\Requisicion;
use App\Models\ReservaMaterialOrden;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1710RequerimientosCompraAlmacenLogisticaTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private User $logistica;
    private User $planta;
    private Producto $producto;
    private Inventario $inventario;
    private OrdenOperacion $orden;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = $this->usuarioConRol('ALMACEN', 'almacen_1710');
        $this->logistica = $this->usuarioConRol('COMERCIAL_LOGISTICA', 'logistica_1710');
        $this->planta = $this->usuarioConRol('JEFE_PLANTA', 'planta_1710');

        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $repisa = Repisa::query()->create([
            'codigo' => 'R-1710',
            'descripcion' => 'Repisa fase 17.1',
            'estado' => true,
        ]);
        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'MAT-1710-A',
            'descripcion' => 'Válvula para requerimiento de compra',
            'estado' => true,
        ]);
        $this->inventario = Inventario::query()->create([
            'producto_id' => $this->producto->id,
            'repisa_id' => $repisa->id,
            'stock_actual' => 4,
            'stock_minimo' => 3,
            'stock_maximo' => 30,
            'costo_promedio_soles' => 12,
        ]);

        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );
        $cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20617100001',
            'ruc' => '20617100001',
            'razon_social' => 'Cliente Fase 17.1 SAC',
            'estado' => true,
        ]);
        $tipoOrden = TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OP'],
            ['nombre' => 'Producción', 'estado' => true]
        );
        $this->orden = OrdenOperacion::query()->create([
            'tipo_orden_id' => $tipoOrden->id,
            'cliente_id' => $cliente->id,
            'codigo_orden' => 'OP-171-26',
            'numero_correlativo' => 171,
            'anio' => 2026,
            'fecha_apertura' => now()->toDateString(),
            'descripcion' => 'Orden activa para requerimiento',
            'estado' => 'EN_PROCESO',
            'creado_por' => $this->logistica->id,
            'iniciado_por' => $this->planta->id,
            'iniciado_en' => now(),
        ]);
    }

    public function test_solo_almacen_crea_requerimientos_y_logistica_los_recibe(): void
    {
        $this->actingAs($this->almacen)
            ->get(route('requerimientos-compra.create'))
            ->assertOk()
            ->assertSee('Crear requerimiento de compra');

        $this->actingAs($this->logistica)
            ->get(route('requerimientos-compra.create'))
            ->assertForbidden();

        $this->actingAs($this->planta)
            ->get(route('requerimientos-compra.index'))
            ->assertForbidden();
    }

    public function test_guardar_requerimiento_no_mueve_stock_ni_reservas_ni_kardex(): void
    {
        $stockAntes = (float) $this->inventario->stock_actual;

        $this->actingAs($this->almacen)
            ->post(route('requerimientos-compra.store'), $this->payload())
            ->assertRedirect();

        $requerimiento = Requisicion::query()->firstOrFail();
        $detalle = $requerimiento->detalles()->firstOrFail();

        $this->assertSame('BORRADOR', $requerimiento->estado);
        $this->assertStringStartsWith('REQ-', $requerimiento->codigo);
        $this->assertSame('ORDEN_OPERACION', $requerimiento->origen);
        $this->assertSame($this->orden->id, $requerimiento->orden_operacion_id);
        $this->assertEquals(8, (float) $detalle->cantidad_solicitada);
        $this->assertEquals(4, (float) $detalle->stock_fisico_snapshot);
        $this->assertEquals($stockAntes, (float) $this->inventario->fresh()->stock_actual);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertDatabaseCount('reservas_materiales_orden', 0);
    }

    public function test_borrador_no_aparece_a_logistica_hasta_que_almacen_lo_envia(): void
    {
        $requerimiento = $this->crearRequerimiento();

        $respuestaListado = $this->actingAs($this->logistica)
            ->get(route('requerimientos-compra.index'))
            ->assertOk();

        // El POST que crea el borrador deja un mensaje flash con el código del
        // requerimiento. Como actingAs() cambia el usuario autenticado pero no
        // crea una sesión nueva, assertDontSee($requerimiento->codigo) puede
        // encontrar el código dentro del toast aunque el registro no esté en la
        // colección que Logística recibe. Validamos directamente los datos del
        // listado, que es la regla de negocio que este test debe proteger.
        $respuestaListado->assertViewHas('requerimientos', function ($requerimientos) use ($requerimiento): bool {
            return ! $requerimientos->getCollection()->contains(
                fn($fila): bool => (int) $fila->id === (int) $requerimiento->id
            );
        });

        $this->actingAs($this->logistica)
            ->get(route('requerimientos-compra.show', $requerimiento))
            ->assertForbidden();

        $this->actingAs($this->almacen)
            ->patch(route('requerimientos-compra.enviar', $requerimiento))
            ->assertRedirect();

        $requerimiento->refresh();
        $this->assertSame('ENVIADA', $requerimiento->estado);
        $this->assertNotNull($requerimiento->enviado_en);

        $respuestaEnviada = $this->actingAs($this->logistica)
            ->get(route('requerimientos-compra.index'))
            ->assertOk();

        $respuestaEnviada->assertViewHas('requerimientos', function ($requerimientos) use ($requerimiento): bool {
            return $requerimientos->getCollection()->contains(
                fn($fila): bool => (int) $fila->id === (int) $requerimiento->id
            );
        });
    }

    public function test_logistica_puede_tomar_y_marcar_requerimiento_como_cotizando(): void
    {
        $requerimiento = $this->crearRequerimientoEnviado();

        $this->actingAs($this->logistica)
            ->patch(route('requerimientos-compra.recibir', $requerimiento))
            ->assertRedirect();
        $this->assertSame('EN_REVISION', $requerimiento->fresh()->estado);
        $this->assertSame($this->logistica->id, $requerimiento->fresh()->recibido_por);

        $this->actingAs($this->logistica)
            ->patch(route('requerimientos-compra.cotizando', $requerimiento))
            ->assertRedirect();
        $this->assertSame('COTIZANDO', $requerimiento->fresh()->estado);
    }

    public function test_solo_se_puede_vincular_una_orden_en_ejecucion(): void
    {
        $this->orden->update(['estado' => 'CERRADA', 'cerrado_en' => now()]);

        $this->actingAs($this->almacen)
            ->post(route('requerimientos-compra.store'), $this->payload())
            ->assertSessionHasErrors('orden_operacion_id');

        $this->assertDatabaseCount('requisiciones', 0);
    }

    public function test_abrir_requerimiento_desde_orden_precarga_faltantes_sugeridos(): void
    {
        MaterialRequeridoOrden::query()->create([
            'orden_operacion_id' => $this->orden->id,
            'producto_id' => $this->producto->id,
            'cantidad_requerida' => 8,
            'cantidad_prevista' => 8,
            'creado_por' => $this->planta->id,
            'actualizado_por' => $this->planta->id,
        ]);
        ReservaMaterialOrden::query()->create([
            'orden_operacion_id' => $this->orden->id,
            'producto_id' => $this->producto->id,
            'cantidad_reservada' => 8,
            'cantidad_atendida' => 0,
            'cantidad_liberada' => 0,
            'estado' => 'ACTIVA',
            'reservado_por' => $this->planta->id,
            'actualizado_por' => $this->planta->id,
        ]);

        $respuesta = $this->actingAs($this->almacen)
            ->get(route('requerimientos-compra.create', ['orden_operacion_id' => $this->orden->id]))
            ->assertOk();

        $lineas = $respuesta->viewData('detallesIniciales');
        $this->assertCount(1, $lineas);
        $this->assertSame($this->producto->id, $lineas[0]['producto_id']);
        $this->assertEquals(7, (float) $lineas[0]['cantidad_solicitada']);
        $this->assertEquals(7, (float) $lineas[0]['cantidad_sugerida']);
    }

    public function test_busqueda_de_productos_para_requerimiento_devuelve_disponibilidad_y_sugerencia(): void
    {
        $response = $this->actingAs($this->almacen)
            ->getJson(route('catalogos.productos.buscar', [
                'contexto' => 'requerimiento_compra',
                'q' => 'MAT-1710-A',
            ]))
            ->assertOk();

        $this->assertSame($this->producto->id, $response->json('items.0.id'));
        $this->assertSame(4.0, (float) $response->json('items.0.stock_fisico'));
        $this->assertArrayHasKey('cantidad_sugerida', $response->json('items.0'));
    }

    public function test_logistica_ve_nombre_telefono_y_correo_de_proveedor_que_ya_cotizo_producto(): void
    {
        $requerimiento = $this->crearRequerimientoEnviado();
        $proveedor = Proveedor::query()->create([
            'ruc' => '20617100002',
            'razon_social' => 'Proveedor Histórico SAC',
            'nombre_comercial' => 'Proveedor Histórico',
            'telefono' => '999111222',
            'correo' => 'ventas@historico.test',
            'estado' => true,
        ]);

        $cotizacion = Cotizacion::query()->create([
            'requisicion_id' => $requerimiento->id,
            'proveedor_id' => $proveedor->id,
            'codigo' => 'CP-171-26',
            'fecha_cotizacion' => now()->toDateString(),
            'moneda' => 'PEN',
            'subtotal' => 100,
            'impuesto' => 18,
            'total' => 118,
            'estado' => 'REGISTRADA',
            'registrado_por' => $this->logistica->id,
        ]);
        CotizacionDetalle::query()->create([
            'cotizacion_id' => $cotizacion->id,
            'requisicion_detalle_id' => $requerimiento->detalles()->first()->id,
            'producto_id' => $this->producto->id,
            'cantidad' => 8,
            'precio_unitario' => 12.5,
            'descuento_porcentaje' => 0,
            'subtotal' => 100,
            'impuesto' => 18,
            'total' => 118,
            'igv_modo' => 'AGREGAR',
            'descuento_modo' => 'SIN_DESCUENTO',
        ]);

        $this->actingAs($this->logistica)
            ->get(route('requerimientos-compra.show', $requerimiento))
            ->assertOk()
            ->assertSee('Proveedor Histórico')
            ->assertSee('999111222')
            ->assertSee('ventas@historico.test');
    }

    public function test_registrar_cotizacion_desde_requerimiento_precarga_productos_y_cantidad(): void
    {
        $requerimiento = $this->crearRequerimientoEnviado();
        $proveedor = Proveedor::query()->create([
            'ruc' => '20617100003',
            'razon_social' => 'Proveedor Precarga SAC',
            'telefono' => '988000111',
            'estado' => true,
        ]);

        $respuesta = $this->actingAs($this->logistica)
            ->get(route('cotizaciones-proveedor.create', [
                'requisicion_id' => $requerimiento->id,
                'proveedor_id' => $proveedor->id,
            ]))
            ->assertOk()
            ->assertSee('Requerimiento relacionado');

        $this->assertSame($requerimiento->id, $respuesta->viewData('requisicionSeleccionada')->id);
        $this->assertSame($this->producto->id, $respuesta->viewData('lineasRequisicion')[0]['producto_id']);
        $this->assertEquals(8, (float) $respuesta->viewData('lineasRequisicion')[0]['cantidad']);
    }

    public function test_modulos_antiguos_requisiciones_y_solicitudes_redirigen_al_unico_modulo(): void
    {
        $this->actingAs($this->almacen)
            ->get(route('modulos.show', 'requisiciones'))
            ->assertRedirect(route('requerimientos-compra.index'));

        $this->actingAs($this->logistica)
            ->get(route('modulos.show', 'solicitudes-compra'))
            ->assertRedirect(route('requerimientos-compra.index'));
    }

    public function test_sidebar_ya_no_muestra_solicitudes_de_compra_como_modulo_duplicado(): void
    {
        $this->actingAs($this->logistica)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Requerimientos de compra')
            ->assertDontSee('Solicitudes de compra');
    }

    private function payload(): array
    {
        return [
            'fecha_solicitud' => now()->toDateString(),
            'origen' => 'ORDEN_OPERACION',
            'orden_operacion_id' => $this->orden->id,
            'prioridad' => 'ALTA',
            'descripcion' => 'Faltante para completar la orden.',
            'detalles' => [[
                'producto_id' => $this->producto->id,
                'cantidad_solicitada' => 8,
                'observacion' => 'Solicitar marca equivalente aprobada.',
            ]],
        ];
    }

    private function crearRequerimiento(): Requisicion
    {
        $this->actingAs($this->almacen)
            ->post(route('requerimientos-compra.store'), $this->payload())
            ->assertRedirect();

        return Requisicion::query()->firstOrFail();
    }

    private function crearRequerimientoEnviado(): Requisicion
    {
        $requerimiento = $this->crearRequerimiento();
        $this->actingAs($this->almacen)
            ->patch(route('requerimientos-compra.enviar', $requerimiento))
            ->assertRedirect();

        return $requerimiento->fresh();
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
