<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
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

class Fase1707ReservasMaterialesHerramientasTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private User $jefePlanta;
    private Cliente $cliente;
    private Producto $producto;
    private Repisa $repisa;
    private Inventario $inventario;
    private OrdenOperacion $orden;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = $this->usuarioConRol('ALMACEN', 'almacen_1707');
        $this->jefePlanta = $this->usuarioConRol('JEFE_PLANTA', 'planta_1707');

        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );

        $this->cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20617070001',
            'ruc' => '20617070001',
            'razon_social' => 'Cliente Fase 1707 SAC',
            'estado' => true,
        ]);

        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );

        $this->repisa = Repisa::query()->create([
            'codigo' => 'R-1707-01',
            'descripcion' => 'Pruebas reservas 17.0.7',
            'estado' => true,
        ]);

        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'PRD-1707',
            'descripcion' => 'Material y herramienta de prueba 17.0.7',
            'estado' => true,
        ]);

        $this->inventario = Inventario::query()->create([
            'producto_id' => $this->producto->id,
            'repisa_id' => $this->repisa->id,
            'stock_actual' => 10,
            'stock_minimo' => 2,
            'stock_maximo' => 20,
            'costo_promedio_soles' => 10,
        ]);

        $tipoOrden = TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OP'],
            ['nombre' => 'Producción', 'estado' => true]
        );

        $this->orden = OrdenOperacion::query()->create([
            'tipo_orden_id' => $tipoOrden->id,
            'cliente_id' => $this->cliente->id,
            'codigo_orden' => 'OP-1707-00001',
            'numero_correlativo' => 1,
            'anio' => (int) now()->format('Y'),
            'fecha_apertura' => now()->toDateString(),
            'descripcion' => 'Orden para validar reservas blandas',
            'estado' => 'EN_PROCESO',
            'creado_por' => $this->almacen->id,
        ]);
    }

    public function test_reservar_material_no_descuenta_stock_ni_crea_kardex(): void
    {
        $this->reservar(6);

        $this->assertDatabaseHas('reservas_materiales_orden', [
            'orden_operacion_id' => $this->orden->id,
            'producto_id' => $this->producto->id,
            'cantidad_reservada' => 6,
            'cantidad_atendida' => 0,
            'estado' => 'ACTIVA',
        ]);
        $this->assertEquals(10, (float) $this->inventario->fresh()->stock_actual);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_consumo_de_orden_aplica_reserva_sin_reducir_disponibilidad_libre(): void
    {
        $this->reservar(6);
        $this->registrarSalida('CONSUMO', 4);

        $reserva = ReservaMaterialOrden::query()->firstOrFail();
        $detalle = NotaSalida::query()->with('detalles')->firstOrFail()->detalles->firstOrFail();

        $this->assertEquals(6, (float) $this->inventario->fresh()->stock_actual);
        $this->assertEquals(4, (float) $reserva->fresh()->cantidad_atendida);
        $this->assertEquals(2, $reserva->fresh()->cantidadPendiente());
        $this->assertEquals(4, (float) $detalle->cantidad_aplicada_reserva);
        $this->assertSame($reserva->id, $detalle->reserva_material_orden_id);
        $this->assertEquals(4, (float) $this->inventario->fresh()->stock_actual - $reserva->fresh()->cantidadPendiente());
    }

    public function test_salida_superior_a_reserva_se_permite_y_solo_imputa_el_saldo_reservado(): void
    {
        $this->reservar(2);
        $this->registrarSalida('CONSUMO', 4);

        $reserva = ReservaMaterialOrden::query()->firstOrFail();
        $detalle = NotaSalida::query()->with('detalles')->firstOrFail()->detalles->firstOrFail();

        $this->assertEquals(6, (float) $this->inventario->fresh()->stock_actual);
        $this->assertEquals(2, (float) $reserva->fresh()->cantidad_atendida);
        $this->assertSame('ATENDIDA', $reserva->fresh()->estado);
        $this->assertEquals(2, (float) $detalle->cantidad_aplicada_reserva);
    }

    public function test_anular_salida_restituye_stock_y_reabre_reserva(): void
    {
        $this->reservar(5);
        $this->registrarSalida('CONSUMO', 3);
        $nota = NotaSalida::query()->firstOrFail();

        $this->actingAs($this->almacen)
            ->patch(route('notas-salida.anular', $nota->id), [
                'motivo_anulacion' => 'Salida registrada por error de prueba.',
            ])
            ->assertRedirect();

        $reserva = ReservaMaterialOrden::query()->firstOrFail()->fresh();
        $this->assertEquals(10, (float) $this->inventario->fresh()->stock_actual);
        $this->assertEquals(0, (float) $reserva->cantidad_atendida);
        $this->assertEquals(5, $reserva->cantidadPendiente());
        $this->assertSame('ACTIVA', $reserva->estado);
    }

    public function test_cerrar_orden_libera_saldo_pendiente_sin_mover_inventario(): void
    {
        $this->reservar(7);

        $this->actingAs($this->jefePlanta)
            ->patch(route('ordenes-operacion.cerrar', $this->orden->id))
            ->assertRedirect();

        $reserva = ReservaMaterialOrden::query()->firstOrFail()->fresh();
        $this->assertSame('CERRADA', $this->orden->fresh()->estado);
        $this->assertSame('LIBERADA', $reserva->estado);
        $this->assertEquals(7, (float) $reserva->cantidad_liberada);
        $this->assertEquals(10, (float) $this->inventario->fresh()->stock_actual);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_herramienta_no_consume_reserva_y_se_controla_hasta_su_devolucion(): void
    {
        $this->reservar(5);
        $this->registrarSalida('USO_TEMPORAL', 1);

        $reserva = ReservaMaterialOrden::query()->firstOrFail()->fresh();
        $salida = NotaSalida::query()->with('detalles')->firstOrFail();
        $detalle = $salida->detalles->firstOrFail();

        $this->assertEquals(0, (float) $reserva->cantidad_atendida);
        $this->assertEquals(5, $reserva->cantidadPendiente());
        $this->assertEquals(0, (float) $detalle->cantidad_aplicada_reserva);

        $this->actingAs($this->almacen)
            ->get(route('inventario.index'))
            ->assertOk()
            ->assertSee('Herramientas en uso')
            ->assertSee('PRD-1707');

        $this->actingAs($this->almacen)
            ->post(route('notas-ingreso.store'), [
                'motivo_ingreso' => 'DEVOLUCION_HERRAMIENTA',
                'nota_salida_id' => $salida->id,
                'fecha_ingreso' => now()->toDateString(),
                'detalles' => [[
                    'nota_salida_detalle_id' => $detalle->id,
                    'producto_id' => $this->producto->id,
                    'repisa_id' => $this->repisa->id,
                    'cantidad' => 1,
                ]],
            ])
            ->assertRedirect();

        $this->actingAs($this->almacen)
            ->get(route('ordenes-operacion.show', $this->orden->id))
            ->assertOk()
            ->assertSee('Sin herramientas pendientes');
    }

    public function test_inventario_y_orden_muestran_reservado_disponible_y_compra_sugerida(): void
    {
        $this->reservar(12);

        $this->actingAs($this->almacen)
            ->get(route('inventario.index'))
            ->assertOk()
            ->assertSee('Reservado total')
            ->assertSee('Disponible libre')
            ->assertSee('Compra sugerida')
            ->assertSee('Requieren compra');

        $this->actingAs($this->almacen)
            ->get(route('ordenes-operacion.show', $this->orden->id))
            ->assertOk()
            ->assertSee('Reservas de la orden')
            ->assertSee('La reserva se sincroniza automáticamente con los materiales requeridos.')
            ->assertSee('descuenta stock físico ni crea Kardex')
            ->assertSee('Comprar');
    }

    public function test_catalogo_de_reserva_no_expone_costo_y_muestra_disponibilidad(): void
    {
        $this->reservar(3);

        $respuesta = $this->actingAs($this->jefePlanta)
            ->getJson(route('catalogos.productos.buscar', [
                'contexto' => 'reserva_orden',
                'orden_id' => $this->orden->id,
                'q' => 'PRD-1707',
            ]))
            ->assertOk();

        $item = $respuesta->json('items.0');
        $this->assertSame($this->producto->id, $item['id']);
        $this->assertStringContainsString('Reservado', $item['description']);
        $this->assertArrayNotHasKey('costo_referencia', $item);

        $general = $this->actingAs($this->jefePlanta)
            ->getJson(route('catalogos.productos.buscar', ['q' => 'PRD-1707']))
            ->assertOk()
            ->json('items.0');
        $this->assertArrayNotHasKey('costo_referencia', $general);
    }

    private function reservar(float $cantidad): void
    {
        $this->actingAs($this->almacen)
            ->post(route('ordenes-operacion.reservas-materiales.store', $this->orden->id), [
                'producto_id' => $this->producto->id,
                'cantidad' => $cantidad,
                'observacion' => 'Reserva de prueba 17.0.7',
            ])
            ->assertRedirect(route('ordenes-operacion.show', $this->orden->id));
    }

    private function registrarSalida(string $tratamiento, float $cantidad): void
    {
        $this->actingAs($this->almacen)
            ->post(route('notas-salida.store'), [
                'motivo_salida' => 'ORDEN_OPERACION',
                'orden_operacion_id' => $this->orden->id,
                'fecha_salida' => now()->toDateString(),
                'entregado_a' => 'Responsable de planta',
                'detalles' => [[
                    'inventario_id' => $this->inventario->id,
                    'producto_id' => $this->producto->id,
                    'repisa_id' => $this->repisa->id,
                    'tratamiento' => $tratamiento,
                    'cantidad' => $cantidad,
                ]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('movimientos_inventario', [
            'producto_id' => $this->producto->id,
            'tipo_movimiento' => 'SALIDA',
            'motivo' => $tratamiento === 'USO_TEMPORAL'
                ? 'HERRAMIENTA_EN_USO'
                : 'CONSUMO_ORDEN',
        ]);
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
