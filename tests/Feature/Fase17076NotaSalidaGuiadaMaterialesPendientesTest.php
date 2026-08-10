<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\MaterialRequeridoOrden;
use App\Models\NotaSalida;
use App\Models\OrdenOperacion;
use App\Models\Producto;
use App\Models\Repisa;
use App\Models\ReservaMaterialOrden;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase17076NotaSalidaGuiadaMaterialesPendientesTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private User $jefePlanta;
    private Cliente $cliente;
    private UnidadMedida $unidad;
    private Repisa $repisa;
    private Producto $producto;
    private Inventario $inventario;
    private OrdenOperacion $orden;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = $this->usuarioConRol('ALMACEN', 'almacen_17076');
        $this->jefePlanta = $this->usuarioConRol('JEFE_PLANTA', 'planta_17076');

        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );

        $this->cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20617076001',
            'ruc' => '20617076001',
            'razon_social' => 'Cliente Fase 17076 SAC',
            'estado' => true,
        ]);

        $this->unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );

        $this->repisa = Repisa::query()->create([
            'codigo' => 'R-17076',
            'descripcion' => 'Repisa salida guiada',
            'estado' => true,
        ]);

        $this->producto = $this->crearProducto('MAT-17076-A', 'Material planificado 17.0.7.6', 20);

        $tipoOrden = TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OP'],
            ['nombre' => 'Producción', 'estado' => true]
        );

        $this->orden = OrdenOperacion::query()->create([
            'tipo_orden_id' => $tipoOrden->id,
            'cliente_id' => $this->cliente->id,
            'codigo_orden' => 'OP-17076-00001',
            'numero_correlativo' => 7601,
            'anio' => (int) now()->format('Y'),
            'fecha_apertura' => now()->toDateString(),
            'descripcion' => 'Orden para validar salida guiada',
            'estado' => 'ABIERTA',
            'creado_por' => $this->jefePlanta->id,
        ]);

        $this->agregarMaterial($this->producto, 10);
    }

    public function test_catalogo_de_salida_solo_muestra_ordenes_activas(): void
    {
        $abierta = $this->actingAs($this->almacen)
            ->getJson(route('catalogos.ordenes-operacion.buscar', ['q' => 'OP-17076']))
            ->assertOk();

        $this->assertCount(0, $abierta->json('items'));

        $this->activarOrden();

        $activa = $this->actingAs($this->almacen)
            ->getJson(route('catalogos.ordenes-operacion.buscar', ['q' => 'OP-17076']))
            ->assertOk();

        $this->assertCount(1, $activa->json('items'));
        $this->assertSame($this->orden->id, $activa->json('items.0.id'));
        $this->assertStringContainsString('EN_PROCESO', $activa->json('items.0.description'));
    }

    public function test_catalogo_busca_ordenes_activas_por_codigo_tipo_o_nombre_y_excluye_cerradas(): void
    {
        $this->activarOrden();

        $tipoOs = TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OS'],
            ['nombre' => 'Servicio', 'estado' => true]
        );
        $tipoOp = TipoOrden::query()->where('codigo', 'OP')->firstOrFail();

        $ordenOs = OrdenOperacion::query()->create([
            'tipo_orden_id' => $tipoOs->id,
            'cliente_id' => $this->cliente->id,
            'codigo_orden' => 'OS-17076-00002',
            'numero_correlativo' => 7602,
            'anio' => (int) now()->format('Y'),
            'fecha_apertura' => now()->toDateString(),
            'descripcion' => 'Servicio activo para filtro',
            'estado' => 'EN_PROCESO',
            'creado_por' => $this->jefePlanta->id,
            'iniciado_por' => $this->jefePlanta->id,
            'iniciado_en' => now(),
        ]);

        OrdenOperacion::query()->create([
            'tipo_orden_id' => $tipoOp->id,
            'cliente_id' => $this->cliente->id,
            'codigo_orden' => 'OP-17076-CERRADA',
            'numero_correlativo' => 7603,
            'anio' => (int) now()->format('Y'),
            'fecha_apertura' => now()->toDateString(),
            'descripcion' => 'Producción cerrada',
            'estado' => 'CERRADA',
            'creado_por' => $this->jefePlanta->id,
            'cerrado_en' => now(),
        ]);

        $porCodigo = $this->actingAs($this->almacen)
            ->getJson(route('catalogos.ordenes-operacion.buscar', ['q' => 'OP-17076']))
            ->assertOk();

        $this->assertSame([$this->orden->id], collect($porCodigo->json('items'))->pluck('id')->values()->all());
        $this->assertStringNotContainsString('OP-17076-CERRADA', json_encode($porCodigo->json('items')));

        $porTipo = $this->actingAs($this->almacen)
            ->getJson(route('catalogos.ordenes-operacion.buscar', ['q' => 'OS']))
            ->assertOk();
        $this->assertSame([$ordenOs->id], collect($porTipo->json('items'))->pluck('id')->values()->all());

        $porNombre = $this->actingAs($this->almacen)
            ->getJson(route('catalogos.ordenes-operacion.buscar', ['q' => 'servicio']))
            ->assertOk();
        $this->assertSame([$ordenOs->id], collect($porNombre->json('items'))->pluck('id')->values()->all());
    }

    public function test_formulario_carga_materiales_previstos_y_deja_buscador_solo_para_extras(): void
    {
        $extra = $this->crearProducto('MAT-17076-BUSCAR', 'Producto adicional buscable', 7);
        $this->activarOrden();

        $respuesta = $this->actingAs($this->almacen)
            ->get(route('notas-salida.create', [
                'motivo_salida' => 'ORDEN_OPERACION',
                'orden_operacion_id' => $this->orden->id,
            ]))
            ->assertOk()
            ->assertSee('Orden activa relacionada')
            ->assertSee('OM-0001, mantenimiento, OS, servicio, OP, producción o cliente')
            ->assertDontSee('Tipo de orden')
            ->assertDontSee('Buscar en materiales de la orden')
            ->assertSee('Los materiales previstos de la orden ya están cargados en la tabla')
            ->assertSee('Agregar producto adicional / no previsto')
            ->assertSee('MAT-17076-A')
            ->assertDontSee('MAT-17076-BUSCAR');

        $planificado = $this->actingAs($this->almacen)
            ->getJson(route('catalogos.existencias-salida.buscar', [
                'orden_id' => $this->orden->id,
                'q' => 'MAT-17076-A',
            ]))
            ->assertOk();
        $this->assertCount(0, $planificado->json('items'));

        $adicional = $this->actingAs($this->almacen)
            ->getJson(route('catalogos.existencias-salida.buscar', [
                'orden_id' => $this->orden->id,
                'q' => 'MAT-17076-BUSCAR',
            ]))
            ->assertOk();
        $this->assertCount(1, $adicional->json('items'));
        $this->assertSame($extra->id, $adicional->json('items.0.producto_id'));
    }

    public function test_nota_de_salida_de_orden_exige_orden_previamente_activada(): void
    {
        $this->actingAs($this->almacen)
            ->post(route('notas-salida.store'), $this->payloadSalida($this->producto, $this->inventario, 1))
            ->assertSessionHasErrors('orden_operacion_id');

        $this->assertDatabaseCount('notas_salida', 0);
        $this->assertEquals(20, (float) $this->inventario->fresh()->stock_actual);
    }

    public function test_formulario_prioriza_materiales_pendientes_y_muestra_plan_de_la_orden(): void
    {
        $this->activarOrden();

        $respuesta = $this->actingAs($this->almacen)
            ->get(route('notas-salida.create', [
                'motivo_salida' => 'ORDEN_OPERACION',
                'orden_operacion_id' => $this->orden->id,
            ]))
            ->assertOk()
            ->assertSee('Entrega guiada por la orden')
            ->assertSee('Plan de la orden')
            ->assertSee('Previsto')
            ->assertSee('Requerido')
            ->assertSee('Entregado')
            ->assertSee('Pendiente')
            ->assertSee('Usar pendiente')
            ->assertSee('MAT-17076-A');

        $pendientes = $respuesta->viewData('pendientesOrden');
        $this->assertCount(1, $pendientes);
        $this->assertEquals(10, $pendientes->first()['previsto']);
        $this->assertEquals(10, $pendientes->first()['requerido']);
        $this->assertEquals(0, $pendientes->first()['entregado']);
        $this->assertEquals(10, $pendientes->first()['pendiente']);
    }

    public function test_entrega_parcial_reduce_reserva_y_el_siguiente_formulario_muestra_saldo_real(): void
    {
        $this->activarOrden();

        $this->actingAs($this->almacen)
            ->post(route('notas-salida.store'), $this->payloadSalida($this->producto, $this->inventario, 4))
            ->assertRedirect();

        $reserva = ReservaMaterialOrden::query()
            ->where('orden_operacion_id', $this->orden->id)
            ->where('producto_id', $this->producto->id)
            ->firstOrFail();

        $this->assertEquals(4, (float) $reserva->cantidad_atendida);
        $this->assertEquals(6, $reserva->cantidadPendiente());
        $this->assertEquals(16, (float) $this->inventario->fresh()->stock_actual);

        $respuesta = $this->actingAs($this->almacen)
            ->get(route('notas-salida.create', [
                'motivo_salida' => 'ORDEN_OPERACION',
                'orden_operacion_id' => $this->orden->id,
            ]))
            ->assertOk();

        $pendiente = $respuesta->viewData('pendientesOrden')->first();
        $this->assertEquals(4, $pendiente['entregado']);
        $this->assertEquals(6, $pendiente['pendiente']);
        $this->assertEquals(6, $pendiente['reserva_pendiente']);
    }

    public function test_material_pendiente_sin_stock_permanece_visible_como_faltante(): void
    {
        $this->inventario->update(['stock_actual' => 0]);
        $this->activarOrden();

        $respuesta = $this->actingAs($this->almacen)
            ->get(route('notas-salida.create', [
                'motivo_salida' => 'ORDEN_OPERACION',
                'orden_operacion_id' => $this->orden->id,
            ]))
            ->assertOk()
            ->assertSee('Pendientes sin stock físico')
            ->assertSee('MAT-17076-A')
            ->assertSee('Sin existencia disponible')
            ->assertSee('Abastecer antes de despachar')
            ->assertSee('No hay stock físico disponible para registrar la salida');

        $this->assertCount(1, $respuesta->viewData('pendientesSinStock'));
        $this->assertCount(1, $respuesta->viewData('materialesSinExistencia'));
    }

    public function test_consumo_no_planificado_se_sigue_permitiendo_como_excepcion_trazable(): void
    {
        $extra = $this->crearProducto('MAT-17076-X', 'Material extra no planificado', 5);
        $this->activarOrden();

        $this->actingAs($this->almacen)
            ->get(route('notas-salida.create', [
                'motivo_salida' => 'ORDEN_OPERACION',
                'orden_operacion_id' => $this->orden->id,
            ]))
            ->assertOk()
            ->assertSee('Agregar producto adicional / no previsto')
            ->assertDontSee('MAT-17076-X');

        $busqueda = $this->actingAs($this->almacen)
            ->getJson(route('catalogos.existencias-salida.buscar', [
                'orden_id' => $this->orden->id,
                'q' => 'MAT-17076-X',
            ]))
            ->assertOk();

        $this->assertCount(1, $busqueda->json('items'));
        $this->assertSame($extra->id, $busqueda->json('items.0.producto_id'));

        $inventarioExtra = Inventario::query()->where('producto_id', $extra->id)->firstOrFail();

        $this->actingAs($this->almacen)
            ->post(route('notas-salida.store'), $this->payloadSalida($extra, $inventarioExtra, 2))
            ->assertRedirect();

        $detalle = NotaSalida::query()->latest('id')->firstOrFail()->detalles()->firstOrFail();
        $this->assertEquals(2, (float) $detalle->cantidad);
        $this->assertNull($detalle->reserva_material_orden_id);
        $this->assertEquals(0, (float) $detalle->cantidad_aplicada_reserva);
    }

    public function test_uso_temporal_no_reduce_material_pendiente_ni_reserva(): void
    {
        $this->activarOrden();
        $reservaAntes = ReservaMaterialOrden::query()->firstOrFail();

        $payload = $this->payloadSalida($this->producto, $this->inventario, 1);
        $payload['detalles'][0]['tratamiento'] = 'USO_TEMPORAL';

        $this->actingAs($this->almacen)
            ->post(route('notas-salida.store'), $payload)
            ->assertRedirect();

        $this->assertEquals(0, (float) $reservaAntes->fresh()->cantidad_atendida);
        $this->assertEquals(10, $reservaAntes->fresh()->cantidadPendiente());

        $respuesta = $this->actingAs($this->almacen)
            ->get(route('notas-salida.create', [
                'motivo_salida' => 'ORDEN_OPERACION',
                'orden_operacion_id' => $this->orden->id,
            ]))
            ->assertOk();

        $pendiente = $respuesta->viewData('pendientesOrden')->first();
        $this->assertEquals(0, $pendiente['entregado']);
        $this->assertEquals(10, $pendiente['pendiente']);
    }

    private function activarOrden(): void
    {
        $this->actingAs($this->jefePlanta)
            ->patch(route('ordenes-operacion.iniciar', $this->orden))
            ->assertRedirect();

        $this->orden->refresh();
        $this->assertSame('EN_PROCESO', $this->orden->estado);
    }

    private function agregarMaterial(Producto $producto, float $cantidad): void
    {
        $this->actingAs($this->jefePlanta)
            ->post(route('ordenes-operacion.materiales-requeridos.store', $this->orden), [
                'producto_id' => $producto->id,
                'cantidad' => $cantidad,
                'motivo' => 'Plan inicial de prueba 17.0.7.6',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('materiales_requeridos_orden', [
            'orden_operacion_id' => $this->orden->id,
            'producto_id' => $producto->id,
        ]);
    }

    private function payloadSalida(Producto $producto, Inventario $inventario, float $cantidad): array
    {
        return [
            'motivo_salida' => 'ORDEN_OPERACION',
            'orden_operacion_id' => $this->orden->id,
            'fecha_salida' => now()->toDateString(),
            'entregado_a' => 'Responsable de planta',
            'detalles' => [[
                'inventario_id' => $inventario->id,
                'producto_id' => $producto->id,
                'repisa_id' => $inventario->repisa_id,
                'tratamiento' => 'CONSUMO',
                'cantidad' => $cantidad,
            ]],
        ];
    }

    private function crearProducto(string $codigo, string $descripcion, float $stock): Producto
    {
        $producto = Producto::query()->create([
            'unidad_medida_id' => $this->unidad->id,
            'codigo' => $codigo,
            'descripcion' => $descripcion,
            'estado' => true,
        ]);

        $inventario = Inventario::query()->create([
            'producto_id' => $producto->id,
            'repisa_id' => $this->repisa->id,
            'stock_actual' => $stock,
            'stock_minimo' => 2,
            'stock_maximo' => 30,
            'costo_promedio_soles' => 10,
        ]);

        if ($codigo === 'MAT-17076-A') {
            $this->inventario = $inventario;
        }

        return $producto;
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
