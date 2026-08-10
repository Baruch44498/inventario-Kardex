<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\MaterialRequeridoOrden;
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

class Fase17075ActivacionReservaAutomaticaTest extends TestCase
{
    use RefreshDatabase;

    private User $jefePlanta;
    private User $logistica;
    private Cliente $cliente;
    private Producto $producto;
    private Producto $productoAdicional;
    private Repisa $repisa;
    private OrdenOperacion $orden;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jefePlanta = $this->usuarioConRol('JEFE_PLANTA', 'planta_17075');
        $this->logistica = $this->usuarioConRol('COMERCIAL_LOGISTICA', 'logistica_17075');

        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );

        $this->cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20617075001',
            'ruc' => '20617075001',
            'razon_social' => 'Cliente Fase 17075 SAC',
            'estado' => true,
        ]);

        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );

        $this->repisa = Repisa::query()->create([
            'codigo' => 'R-17075',
            'descripcion' => 'Repisa activación automática',
            'estado' => true,
        ]);

        $this->producto = $this->crearProducto($unidad->id, 'MAT-17075-A', 10, 2);
        $this->productoAdicional = $this->crearProducto($unidad->id, 'MAT-17075-B', 8, 1);

        $tipoOrden = TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OP'],
            ['nombre' => 'Producción', 'estado' => true]
        );

        $this->orden = OrdenOperacion::query()->create([
            'tipo_orden_id' => $tipoOrden->id,
            'cliente_id' => $this->cliente->id,
            'codigo_orden' => 'OP-17075-00001',
            'numero_correlativo' => 7501,
            'anio' => (int) now()->format('Y'),
            'fecha_apertura' => now()->toDateString(),
            'descripcion' => 'Orden para reserva automática',
            'estado' => 'ABIERTA',
            'creado_por' => $this->logistica->id,
        ]);
    }

    public function test_activar_congela_prevision_y_crea_reserva_sin_mover_stock_ni_kardex(): void
    {
        $this->agregarMaterial($this->producto, 6);

        $this->actingAs($this->jefePlanta)
            ->patch(route('ordenes-operacion.iniciar', $this->orden))
            ->assertRedirect();

        $material = MaterialRequeridoOrden::query()->firstOrFail();
        $reserva = ReservaMaterialOrden::query()->firstOrFail();
        $orden = $this->orden->fresh();

        $this->assertSame('EN_PROCESO', $orden->estado);
        $this->assertNotNull($orden->iniciado_en);
        $this->assertSame($this->jefePlanta->id, $orden->iniciado_por);
        $this->assertEquals(6, (float) $material->cantidad_prevista);
        $this->assertEquals(6, (float) $reserva->cantidad_reservada);
        $this->assertEquals(6, $reserva->cantidadPendiente());
        $this->assertDatabaseHas('historial_materiales_requeridos_orden', [
            'material_requerido_orden_id' => $material->id,
            'tipo_movimiento' => 'CONGELACION',
            'cantidad_nueva' => 6,
        ]);
        $this->assertEquals(10, (float) Inventario::query()->where('producto_id', $this->producto->id)->value('stock_actual'));
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_correccion_previa_a_activacion_se_convierte_en_prevision_congelada(): void
    {
        $this->agregarMaterial($this->producto, 5);
        $material = MaterialRequeridoOrden::query()->firstOrFail();

        $this->actingAs($this->jefePlanta)
            ->patch(route('materiales-requeridos.update', $material), [
                'cantidad_nueva' => 7,
                'motivo' => 'Corrección técnica antes de iniciar.',
            ])
            ->assertRedirect();

        $this->actingAs($this->jefePlanta)
            ->patch(route('ordenes-operacion.iniciar', $this->orden))
            ->assertRedirect();

        $material = $material->fresh();
        $this->assertEquals(7, (float) $material->cantidad_prevista);
        $this->assertEquals(7, (float) $material->cantidad_requerida);
        $this->assertEquals(0, $material->variacionAcumulada());
        $this->assertEquals(7, ReservaMaterialOrden::query()->firstOrFail()->cantidadPendiente());
    }

    public function test_material_nuevo_durante_ejecucion_es_adicional_y_se_reserva_automaticamente(): void
    {
        $this->agregarMaterial($this->producto, 4);
        $this->activarOrden();

        $this->agregarMaterial($this->productoAdicional, 3, 'Material detectado durante fabricación.');

        $material = MaterialRequeridoOrden::query()
            ->where('producto_id', $this->productoAdicional->id)
            ->firstOrFail();

        $this->assertEquals(0, (float) $material->cantidad_prevista);
        $this->assertEquals(3, (float) $material->cantidad_requerida);
        $this->assertEquals(3, $material->variacionAcumulada());
        $this->assertDatabaseHas('historial_materiales_requeridos_orden', [
            'material_requerido_orden_id' => $material->id,
            'tipo_movimiento' => 'ADICIONAL',
            'cantidad_anterior' => 0,
            'cantidad_nueva' => 3,
        ]);
        $this->assertEquals(3, ReservaMaterialOrden::query()
            ->where('producto_id', $this->productoAdicional->id)
            ->firstOrFail()
            ->cantidadPendiente());
    }

    public function test_aumento_posterior_reajusta_reserva_sin_duplicarla(): void
    {
        $this->agregarMaterial($this->producto, 5);
        $this->activarOrden();
        $this->agregarMaterial($this->producto, 2, 'Dos unidades adicionales.');

        $reserva = ReservaMaterialOrden::query()->firstOrFail();
        $material = MaterialRequeridoOrden::query()->firstOrFail();

        $this->assertEquals(5, (float) $material->cantidad_prevista);
        $this->assertEquals(7, (float) $material->cantidad_requerida);
        $this->assertEquals(7, $reserva->cantidadPendiente());
        $this->assertDatabaseCount('reservas_materiales_orden', 1);
        $this->assertEquals(10, (float) Inventario::query()->where('producto_id', $this->producto->id)->value('stock_actual'));
    }

    public function test_reduccion_posterior_libera_exceso_de_reserva_sin_mover_stock(): void
    {
        $this->agregarMaterial($this->producto, 5);
        $this->activarOrden();
        $material = MaterialRequeridoOrden::query()->firstOrFail();

        $this->actingAs($this->jefePlanta)
            ->patch(route('materiales-requeridos.update', $material), [
                'cantidad_nueva' => 3,
                'motivo' => 'Se optimizó el uso del material.',
            ])
            ->assertRedirect();

        $reserva = ReservaMaterialOrden::query()->firstOrFail()->fresh();
        $this->assertEquals(5, (float) $reserva->cantidad_reservada);
        $this->assertEquals(2, (float) $reserva->cantidad_liberada);
        $this->assertEquals(3, $reserva->cantidadPendiente());
        $this->assertEquals(10, (float) Inventario::query()->where('producto_id', $this->producto->id)->value('stock_actual'));
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_stock_insuficiente_no_bloquea_activacion_y_reserva_toda_la_necesidad(): void
    {
        $this->agregarMaterial($this->producto, 12);

        $this->actingAs($this->jefePlanta)
            ->patch(route('ordenes-operacion.iniciar', $this->orden))
            ->assertRedirect();

        $reserva = ReservaMaterialOrden::query()->firstOrFail();
        $this->assertEquals(12, $reserva->cantidadPendiente());
        $this->assertEquals(10, (float) Inventario::query()->where('producto_id', $this->producto->id)->value('stock_actual'));

        $this->actingAs($this->jefePlanta)
            ->get(route('ordenes-operacion.show', $this->orden))
            ->assertOk()
            ->assertSee('Comprar');
    }

    public function test_logistica_no_puede_modificar_materiales_requeridos_de_la_orden(): void
    {
        $this->actingAs($this->logistica)
            ->post(route('ordenes-operacion.materiales-requeridos.store', $this->orden), [
                'producto_id' => $this->producto->id,
                'cantidad' => 2,
                'motivo' => 'Intento desde Logística.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('materiales_requeridos_orden', 0);
    }

    public function test_interfaz_explica_activacion_y_ya_no_muestra_formulario_manual_de_reserva(): void
    {
        $this->agregarMaterial($this->producto, 4);

        $this->actingAs($this->jefePlanta)
            ->get(route('ordenes-operacion.show', $this->orden))
            ->assertOk()
            ->assertSee('Activar orden')
            ->assertSee('Previsión editable')
            ->assertSee('Las reservas se crearán cuando el Jefe de Planta active la orden.')
            ->assertDontSee('Cantidad a reservar')
            ->assertDontSee('Reservar material');
    }

    private function activarOrden(): void
    {
        $this->actingAs($this->jefePlanta)
            ->patch(route('ordenes-operacion.iniciar', $this->orden))
            ->assertRedirect();
        $this->orden->refresh();
    }

    private function agregarMaterial(Producto $producto, float $cantidad, ?string $motivo = null): void
    {
        $this->actingAs($this->jefePlanta)
            ->post(route('ordenes-operacion.materiales-requeridos.store', $this->orden), [
                'producto_id' => $producto->id,
                'cantidad' => $cantidad,
                'motivo' => $motivo,
            ])
            ->assertRedirect();
    }

    private function crearProducto(int $unidadId, string $codigo, float $stock, float $minimo): Producto
    {
        $producto = Producto::query()->create([
            'unidad_medida_id' => $unidadId,
            'codigo' => $codigo,
            'descripcion' => 'Producto '.$codigo,
            'estado' => true,
        ]);

        Inventario::query()->create([
            'producto_id' => $producto->id,
            'repisa_id' => $this->repisa->id,
            'stock_actual' => $stock,
            'stock_minimo' => $minimo,
            'stock_maximo' => max($stock * 2, $minimo + 1),
            'costo_promedio_soles' => 10,
        ]);

        return $producto;
    }

    private function usuarioConRol(string $codigoRol, string $username): User
    {
        return User::query()->create([
            'role_id' => Role::query()->where('codigo', $codigoRol)->firstOrFail()->id,
            'username' => $username,
            'email' => $username.'@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
    }
}
