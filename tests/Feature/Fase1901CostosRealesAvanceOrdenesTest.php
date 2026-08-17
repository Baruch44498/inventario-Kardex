<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\Inventario;
use App\Models\MaterialRequeridoOrden;
use App\Models\NotaIngreso;
use App\Models\NotaSalida;
use App\Models\OrdenOperacion;
use App\Models\Producto;
use App\Models\Repisa;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\Ordenes\ResumenEjecucionOrdenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1901CostosRealesAvanceOrdenesTest extends TestCase
{
    use RefreshDatabase;

    private User $jefePlanta;
    private User $almacen;
    private User $logistica;
    private OrdenOperacion $orden;
    private Producto $producto;
    private Repisa $repisa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jefePlanta = $this->usuarioConRol('JEFE_PLANTA', 'planta_1901');
        $this->almacen = $this->usuarioConRol('ALMACEN', 'almacen_1901');
        $this->logistica = $this->usuarioConRol('COMERCIAL_LOGISTICA', 'logistica_1901');

        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );
        $cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20619010001',
            'ruc' => '20619010001',
            'razon_social' => 'Cliente Fase 19 SAC',
            'estado' => true,
        ]);
        $tipoOrden = TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OP'],
            ['nombre' => 'Producción', 'estado' => true]
        );
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $this->repisa = Repisa::query()->create([
            'codigo' => 'R-1901',
            'descripcion' => 'Repisa fase 19.0.1',
            'estado' => true,
        ]);
        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'MAT-1901',
            'descripcion' => 'Material de costo real',
            'estado' => true,
        ]);
        Inventario::query()->create([
            'producto_id' => $this->producto->id,
            'repisa_id' => $this->repisa->id,
            'stock_actual' => 20,
            'stock_minimo' => 2,
            'stock_maximo' => 40,
            'costo_promedio_soles' => 20,
        ]);

        $this->orden = OrdenOperacion::query()->create([
            'tipo_orden_id' => $tipoOrden->id,
            'cliente_id' => $cliente->id,
            'codigo_orden' => 'OP-1901-00001',
            'numero_correlativo' => 1,
            'anio' => (int) now()->format('Y'),
            'fecha_apertura' => now()->toDateString(),
            'descripcion' => 'Orden para avance y costo real',
            'estado' => 'EN_PROCESO',
            'creado_por' => $this->logistica->id,
        ]);

        MaterialRequeridoOrden::query()->create([
            'orden_operacion_id' => $this->orden->id,
            'producto_id' => $this->producto->id,
            'cantidad_requerida' => 5,
            'cantidad_prevista' => 5,
            'creado_por' => $this->jefePlanta->id,
            'actualizado_por' => $this->jefePlanta->id,
        ]);

        $cotizacion = CotizacionCliente::query()->create([
            'origen' => 'DIRECTA_LOGISTICA',
            'cliente_id' => $cliente->id,
            'tipo_orden_id' => $tipoOrden->id,
            'descripcion_trabajo' => 'Fabricación de prueba',
            'codigo_base' => 'CC-1901',
            'version' => 1,
            'codigo' => 'CC-1901-vrs1',
            'cliente_nombre' => $cliente->razon_social,
            'fecha_emision' => now()->toDateString(),
            'moneda' => 'PEN',
            'subtotal' => 150,
            'impuesto' => 27,
            'total' => 177,
            'estado' => 'CONVERTIDA_EN_ORDEN',
            'cotizado_por' => $this->logistica->id,
            'orden_operacion_id' => $this->orden->id,
        ]);
        $cotizacion->detalles()->create([
            'producto_id' => $this->producto->id,
            'codigo_producto' => $this->producto->codigo,
            'descripcion' => $this->producto->descripcion,
            'unidad_medida' => 'UND',
            'cantidad' => 5,
            'costo_referencia' => 20,
            'precio_unitario' => 30,
            'subtotal' => 150,
            'impuesto' => 27,
            'total' => 177,
        ]);
    }

    public function test_jefe_de_planta_registra_avance_creciente_y_con_historial(): void
    {
        $this->actingAs($this->jefePlanta)
            ->post(route('ordenes-operacion.avances.store', $this->orden), [
                'porcentaje' => 35.5,
                'detalle' => 'Armado inicial completado.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('avances_orden_operacion', [
            'orden_operacion_id' => $this->orden->id,
            'porcentaje' => 35.5,
            'registrado_por' => $this->jefePlanta->id,
        ]);

        $this->actingAs($this->jefePlanta)
            ->post(route('ordenes-operacion.avances.store', $this->orden), [
                'porcentaje' => 30,
                'detalle' => 'Intento de retroceso.',
            ])
            ->assertSessionHasErrors('porcentaje');

        $this->assertDatabaseCount('avances_orden_operacion', 1);
    }

    public function test_almacen_no_puede_registrar_avance_productivo(): void
    {
        $this->actingAs($this->almacen)
            ->post(route('ordenes-operacion.avances.store', $this->orden), [
                'porcentaje' => 10,
                'detalle' => 'No autorizado.',
            ])
            ->assertForbidden();
    }

    public function test_costo_real_descuenta_retorno_y_avance_material_usa_cantidad_neta(): void
    {
        $salida = NotaSalida::query()->create([
            'orden_operacion_id' => $this->orden->id,
            'motivo_salida' => 'ORDEN_OPERACION',
            'codigo' => 'NS-1901-1',
            'fecha_salida' => now()->toDateString(),
            'estado' => 'CONFIRMADA',
            'registrado_por' => $this->almacen->id,
            'confirmado_por' => $this->almacen->id,
            'confirmado_en' => now(),
        ]);
        $detalleSalida = $salida->detalles()->create([
            'producto_id' => $this->producto->id,
            'repisa_id' => $this->repisa->id,
            'cantidad' => 4,
            'tratamiento' => 'CONSUMO',
            'costo_unitario_promedio' => 20,
            'subtotal' => 80,
        ]);

        $ingreso = NotaIngreso::query()->create([
            'motivo_ingreso' => 'RETORNO_MATERIAL',
            'nota_salida_id' => $salida->id,
            'codigo' => 'NI-1901-1',
            'fecha_ingreso' => now()->toDateString(),
            'estado' => 'CONFIRMADA',
            'registrado_por' => $this->almacen->id,
            'confirmado_por' => $this->almacen->id,
            'confirmado_en' => now(),
        ]);
        $ingreso->detalles()->create([
            'nota_salida_detalle_id' => $detalleSalida->id,
            'producto_id' => $this->producto->id,
            'repisa_id' => $this->repisa->id,
            'cantidad' => 1,
            'costo_unitario' => 20,
            'subtotal' => 20,
        ]);

        $resumen = app(ResumenEjecucionOrdenService::class)
            ->construir($this->orden->fresh('cotizacionCliente.detalles'), true);

        $this->assertSame(60.0, $resumen['avance_materiales']);
        $this->assertSame(100.0, (float) $resumen['costos']['previsto_materiales']);
        $this->assertSame(80.0, (float) $resumen['costos']['salidas_consumo']);
        $this->assertSame(20.0, (float) $resumen['costos']['retornos']);
        $this->assertSame(60.0, (float) $resumen['costos']['real_materiales']);
    }

    public function test_costos_solo_se_muestran_a_logistica_o_administrador(): void
    {
        $this->actingAs($this->logistica)
            ->get(route('ordenes-operacion.show', $this->orden))
            ->assertOk()
            ->assertSee('Costo material real neto');

        $this->actingAs($this->jefePlanta)
            ->get(route('ordenes-operacion.show', $this->orden))
            ->assertOk()
            ->assertSee('Avance operativo')
            ->assertDontSee('Costo material real neto');
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
