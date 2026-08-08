<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\Inventario;
use App\Models\MaterialRequeridoOrden;
use App\Models\NotaSalida;
use App\Models\NotaSalidaDetalle;
use App\Models\OrdenOperacion;
use App\Models\Producto;
use App\Models\Repisa;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase17074MaterialesRequeridosOrdenTest extends TestCase
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

        $this->jefePlanta = $this->usuarioConRol('JEFE_PLANTA', 'planta_17074');
        $this->logistica = $this->usuarioConRol('COMERCIAL_LOGISTICA', 'logistica_17074');

        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );

        $this->cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20617074001',
            'ruc' => '20617074001',
            'razon_social' => 'Cliente Fase 17074 SAC',
            'estado' => true,
        ]);

        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );

        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'MAT-17074-A',
            'descripcion' => 'Material inicial fase 17.0.7.4',
            'estado' => true,
        ]);

        $this->productoAdicional = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'MAT-17074-B',
            'descripcion' => 'Material adicional fase 17.0.7.4',
            'estado' => true,
        ]);

        $this->repisa = Repisa::query()->create([
            'codigo' => 'R-17074',
            'descripcion' => 'Repisa fase 17.0.7.4',
            'estado' => true,
        ]);

        Inventario::query()->create([
            'producto_id' => $this->productoAdicional->id,
            'repisa_id' => $this->repisa->id,
            'stock_actual' => 20,
            'stock_minimo' => 2,
            'stock_maximo' => 30,
            'costo_promedio_soles' => 10,
        ]);

        $tipoOrden = TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OP'],
            ['nombre' => 'Producción', 'estado' => true]
        );

        $this->orden = OrdenOperacion::query()->create([
            'tipo_orden_id' => $tipoOrden->id,
            'cliente_id' => $this->cliente->id,
            'codigo_orden' => 'OP-17074-00001',
            'numero_correlativo' => 7401,
            'anio' => (int) now()->format('Y'),
            'fecha_apertura' => now()->toDateString(),
            'descripcion' => 'Orden para materiales requeridos',
            'estado' => 'ABIERTA',
            'creado_por' => $this->logistica->id,
        ]);
    }

    public function test_cotizacion_convertida_crea_lista_inicial_sin_reservar_ni_mover_stock(): void
    {
        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.store'), $this->datosCotizacion())
            ->assertRedirect();

        $cotizacion = CotizacionCliente::query()->latest('id')->firstOrFail();

        $this->actingAs($this->logistica)
            ->patch(route('cotizaciones-cliente.cerrar', $cotizacion))
            ->assertRedirect();

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.convertir-orden', $cotizacion), [
                'fecha_apertura' => now()->toDateString(),
            ])
            ->assertRedirect();

        $ordenGenerada = OrdenOperacion::query()
            ->where('id', '!=', $this->orden->id)
            ->latest('id')
            ->firstOrFail();

        $material = MaterialRequeridoOrden::query()
            ->where('orden_operacion_id', $ordenGenerada->id)
            ->where('producto_id', $this->producto->id)
            ->firstOrFail();

        $this->assertEquals(2, (float) $material->cantidad_requerida);
        $this->assertDatabaseHas('historial_materiales_requeridos_orden', [
            'material_requerido_orden_id' => $material->id,
            'tipo_movimiento' => 'INICIAL',
            'cantidad_anterior' => 0,
            'cantidad_nueva' => 2,
        ]);
        $this->assertDatabaseCount('reservas_materiales_orden', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_jefe_planta_agrega_material_inicial_sin_crear_reserva(): void
    {
        $this->agregarMaterial(3);

        $material = MaterialRequeridoOrden::query()->firstOrFail();
        $this->assertEquals(3, (float) $material->cantidad_requerida);
        $this->assertDatabaseHas('historial_materiales_requeridos_orden', [
            'material_requerido_orden_id' => $material->id,
            'tipo_movimiento' => 'INICIAL',
            'cantidad_nueva' => 3,
        ]);
        $this->assertDatabaseCount('reservas_materiales_orden', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_repetir_producto_suma_material_adicional_y_conserva_historial(): void
    {
        $this->agregarMaterial(3);
        $this->agregarMaterial(2, 'Se necesitan dos unidades adicionales.');

        $material = MaterialRequeridoOrden::query()->firstOrFail();
        $this->assertEquals(5, (float) $material->cantidad_requerida);
        $this->assertDatabaseHas('historial_materiales_requeridos_orden', [
            'material_requerido_orden_id' => $material->id,
            'tipo_movimiento' => 'ADICIONAL',
            'cantidad_anterior' => 3,
            'cantidad_cambio' => 2,
            'cantidad_nueva' => 5,
        ]);
        $this->assertSame(2, $material->historial()->count());
    }

    public function test_modificar_total_registra_aumento_con_motivo(): void
    {
        $this->agregarMaterial(5);
        $material = MaterialRequeridoOrden::query()->firstOrFail();

        $this->actingAs($this->jefePlanta)
            ->patch(route('materiales-requeridos.update', $material), [
                'cantidad_nueva' => 7,
                'motivo' => 'Cambio técnico durante el armado.',
            ])
            ->assertRedirect();

        $this->assertEquals(7, (float) $material->fresh()->cantidad_requerida);
        $this->assertDatabaseHas('historial_materiales_requeridos_orden', [
            'material_requerido_orden_id' => $material->id,
            'tipo_movimiento' => 'AJUSTE_AUMENTO',
            'cantidad_anterior' => 5,
            'cantidad_cambio' => 2,
            'cantidad_nueva' => 7,
            'motivo' => 'Cambio técnico durante el armado.',
        ]);
    }

    public function test_no_permite_reducir_por_debajo_de_lo_ya_entregado(): void
    {
        $this->agregarMaterial(5);
        $material = MaterialRequeridoOrden::query()->firstOrFail();
        $this->registrarConsumoConfirmado(3);

        $this->actingAs($this->jefePlanta)
            ->from(route('ordenes-operacion.show', $this->orden))
            ->patch(route('materiales-requeridos.update', $material), [
                'cantidad_nueva' => 2,
                'motivo' => 'Intento de reducción inválida.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('cantidad_nueva');

        $this->assertEquals(5, (float) $material->fresh()->cantidad_requerida);
    }

    public function test_reduccion_valida_queda_en_historial(): void
    {
        $this->agregarMaterial(5);
        $material = MaterialRequeridoOrden::query()->firstOrFail();
        $this->registrarConsumoConfirmado(3);

        $this->actingAs($this->jefePlanta)
            ->patch(route('materiales-requeridos.update', $material), [
                'cantidad_nueva' => 4,
                'motivo' => 'Se optimizó el consumo previsto.',
            ])
            ->assertRedirect();

        $this->assertEquals(4, (float) $material->fresh()->cantidad_requerida);
        $this->assertDatabaseHas('historial_materiales_requeridos_orden', [
            'material_requerido_orden_id' => $material->id,
            'tipo_movimiento' => 'AJUSTE_REDUCCION',
            'cantidad_cambio' => -1,
            'cantidad_nueva' => 4,
        ]);
    }

    public function test_orden_cerrada_no_admite_nuevos_requerimientos(): void
    {
        $this->orden->update(['estado' => 'CERRADA', 'cerrado_en' => now()]);

        $this->actingAs($this->jefePlanta)
            ->from(route('ordenes-operacion.show', $this->orden))
            ->post(route('ordenes-operacion.materiales-requeridos.store', $this->orden), [
                'producto_id' => $this->productoAdicional->id,
                'cantidad' => 2,
                'motivo' => 'No debería registrarse.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('orden_operacion_id');

        $this->assertDatabaseCount('materiales_requeridos_orden', 0);
    }

    public function test_detalle_de_orden_muestra_totales_historial_y_pendiente(): void
    {
        $this->agregarMaterial(5);
        $this->agregarMaterial(2, 'Adicional para pruebas visuales.');
        $this->registrarConsumoConfirmado(3);

        $this->actingAs($this->jefePlanta)
            ->get(route('ordenes-operacion.show', $this->orden))
            ->assertOk()
            ->assertSee('Materiales requeridos')
            ->assertSee('Historial (2)')
            ->assertSee('Material adicional')
            ->assertSee('MAT-17074-B')
            ->assertSee('7')
            ->assertSee('3')
            ->assertSee('4');
    }

    private function agregarMaterial(float $cantidad, ?string $motivo = null): void
    {
        $this->actingAs($this->jefePlanta)
            ->post(route('ordenes-operacion.materiales-requeridos.store', $this->orden), [
                'producto_id' => $this->productoAdicional->id,
                'cantidad' => $cantidad,
                'motivo' => $motivo,
            ])
            ->assertRedirect();
    }

    private function registrarConsumoConfirmado(float $cantidad): void
    {
        $nota = NotaSalida::query()->create([
            'orden_operacion_id' => $this->orden->id,
            'motivo_salida' => 'ORDEN_OPERACION',
            'codigo' => 'NS-17074-' . NotaSalida::query()->count(),
            'fecha_salida' => now()->toDateString(),
            'entregado_a' => 'Jefe de planta',
            'estado' => 'CONFIRMADA',
            'registrado_por' => $this->jefePlanta->id,
            'confirmado_por' => $this->jefePlanta->id,
            'confirmado_en' => now(),
        ]);

        NotaSalidaDetalle::query()->create([
            'nota_salida_id' => $nota->id,
            'producto_id' => $this->productoAdicional->id,
            'repisa_id' => $this->repisa->id,
            'cantidad' => $cantidad,
            'cantidad_aplicada_reserva' => 0,
            'tratamiento' => 'CONSUMO',
            'costo_unitario_promedio' => 10,
            'subtotal' => $cantidad * 10,
        ]);
    }

    private function datosCotizacion(): array
    {
        $tipoProduccion = TipoOrden::query()->where('codigo', 'OP')->firstOrFail();

        return [
            'cliente_id' => $this->cliente->id,
            'tipo_orden_id' => $tipoProduccion->id,
            'cliente_direccion_id' => null,
            'vehiculo_id' => null,
            'descripcion_trabajo' => 'Producción con materiales iniciales',
            'fecha_emision' => now()->toDateString(),
            'fecha_validez' => now()->addDays(15)->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => null,
            'condiciones_pago' => 'Contado',
            'condiciones_entrega' => 'Según coordinación',
            'observacion' => 'Cotización fase 17.0.7.4',
            'detalles' => [[
                'producto_id' => $this->producto->id,
                'cantidad' => 2,
                'precio_unitario' => 125,
                'igv_modo' => 'AGREGAR',
                'observacion' => null,
            ]],
        ];
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
