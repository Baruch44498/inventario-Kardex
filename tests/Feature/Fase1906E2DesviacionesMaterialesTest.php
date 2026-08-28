<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\Inventario;
use App\Models\MaterialRequeridoOrden;
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

class Fase1906E2DesviacionesMaterialesTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private Empleado $empleado;
    private OrdenOperacion $orden;
    private Producto $producto;
    private Repisa $repisa;
    private Inventario $inventario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = User::query()->create([
            'role_id' => Role::query()->where('codigo', 'ALMACEN')->firstOrFail()->id,
            'username' => 'almacen_e2',
            'email' => 'almacen_e2@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
        $this->empleado = Empleado::query()->create([
            'nombre_completo' => 'Operario E2',
            'dni' => '73456789',
            'estado' => true,
            'registrado_por' => $this->almacen->id,
        ]);
        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );
        $cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20619060022',
            'ruc' => '20619060022',
            'razon_social' => 'Cliente E2 SAC',
            'estado' => true,
        ]);
        $tipoOrden = TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OP'],
            ['nombre' => 'Producción', 'estado' => true]
        );
        $this->orden = OrdenOperacion::query()->create([
            'tipo_orden_id' => $tipoOrden->id,
            'cliente_id' => $cliente->id,
            'codigo_orden' => 'OP-E2-001',
            'numero_correlativo' => 2,
            'anio' => (int) now()->format('Y'),
            'fecha_apertura' => now()->toDateString(),
            'descripcion' => 'Orden para desviaciones E2',
            'estado' => 'EN_PROCESO',
            'creado_por' => $this->almacen->id,
            'iniciado_por' => $this->almacen->id,
            'iniciado_en' => now(),
        ]);
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $this->repisa = Repisa::query()->create([
            'codigo' => 'R-E2',
            'descripcion' => 'Repisa E2',
            'estado' => true,
        ]);
        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'MAT-E2',
            'descripcion' => 'Material E2',
            'estado' => true,
        ]);
        $this->inventario = Inventario::query()->create([
            'producto_id' => $this->producto->id,
            'repisa_id' => $this->repisa->id,
            'stock_actual' => 10,
            'stock_minimo' => 1,
            'stock_maximo' => 20,
            'costo_promedio_soles' => 5,
        ]);
        MaterialRequeridoOrden::query()->create([
            'orden_operacion_id' => $this->orden->id,
            'producto_id' => $this->producto->id,
            'cantidad_requerida' => 4,
            'cantidad_prevista' => 4,
            'creado_por' => $this->almacen->id,
        ]);
    }

    public function test_separa_cantidad_planificada_y_excedente_con_motivo(): void
    {
        $this->actingAs($this->almacen)
            ->post(route('notas-salida.store'), $this->salida(6, 'NECESIDAD_OPERATIVA'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('nota_salida_detalles', [
            'producto_id' => $this->producto->id,
            'cantidad_planificada_aplicada' => 4,
            'cantidad_excedente' => 2,
            'motivo_excedente' => 'NECESIDAD_OPERATIVA',
        ]);
    }

    public function test_exceso_exige_motivo_en_el_flujo_nuevo(): void
    {
        $this->actingAs($this->almacen)
            ->post(route('notas-salida.store'), $this->salida(5))
            ->assertSessionHasErrors('detalles');

        $this->assertDatabaseCount('notas_salida', 0);
    }

    public function test_material_malogrado_no_regresa_al_stock_y_conserva_trazabilidad(): void
    {
        $this->actingAs($this->almacen)
            ->post(route('notas-salida.store'), $this->salida(2))
            ->assertSessionHasNoErrors();

        $salida = NotaSalida::query()->with('detalles')->firstOrFail();
        $detalle = $salida->detalles->first();
        $this->assertEquals(8, (float) $this->inventario->fresh()->stock_actual);

        $this->actingAs($this->almacen)
            ->post(route('notas-ingreso.store'), [
                'motivo_ingreso' => 'DEVOLUCION_MATERIAL_MALOGRADO',
                'nota_salida_id' => $salida->id,
                'devuelto_por_empleado_id' => $this->empleado->id,
                'fecha_ingreso' => now()->toDateString(),
                'detalles' => [[
                    'nota_salida_detalle_id' => $detalle->id,
                    'producto_id' => $this->producto->id,
                    'repisa_id' => $this->repisa->id,
                    'cantidad' => 1,
                    'observacion' => 'Pieza devuelta para registrar la pérdida.',
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals(8, (float) $this->inventario->fresh()->stock_actual);
        $this->assertDatabaseHas('notas_ingreso', [
            'motivo_ingreso' => 'DEVOLUCION_MATERIAL_MALOGRADO',
            'orden_operacion_id' => $this->orden->id,
            'area_trabajo' => 'GENERAL',
            'devuelto_por_empleado_id' => $this->empleado->id,
            'devuelto_por_dni' => '73456789',
        ]);
        $this->assertDatabaseHas('nota_ingreso_detalles', [
            'nota_salida_detalle_id' => $detalle->id,
            'condicion_retorno' => 'MALOGRADO',
            'afecta_stock' => false,
        ]);

        $this->actingAs($this->almacen)
            ->post(route('notas-salida.store'), $this->salida(3, 'REPOSICION_MALOGRADO'))
            ->assertSessionHasNoErrors();

        $resumen = app(ResumenEjecucionOrdenService::class)->construir($this->orden, false);
        $fila = $resumen['comparacion_materiales']->first();
        $this->assertEquals(4, $fila['estimado']);
        $this->assertEquals(5, $fila['salida_bruta']);
        $this->assertEquals(5, $fila['real']);
        $this->assertEquals(1, $fila['malogrado']);
        $this->assertEquals(1, $fila['diferencia']);
    }

    private function salida(float $cantidad, ?string $motivoExcedente = null): array
    {
        return [
            'motivo_salida' => 'ORDEN_OPERACION',
            'orden_operacion_id' => $this->orden->id,
            'area_trabajo' => 'GENERAL',
            'recibido_por_empleado_id' => $this->empleado->id,
            'fecha_salida' => now()->toDateString(),
            'detalles' => [[
                'inventario_id' => $this->inventario->id,
                'producto_id' => $this->producto->id,
                'repisa_id' => $this->repisa->id,
                'tratamiento' => 'CONSUMO',
                'cantidad' => $cantidad,
                'motivo_excedente' => $motivoExcedente,
            ]],
        ];
    }
}
