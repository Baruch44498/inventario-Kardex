<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\Inventario;
use App\Models\MaterialRequeridoOrden;
use App\Models\MaterialPlanificadoOrdenArea;
use App\Models\NotaIngreso;
use App\Models\NotaSalida;
use App\Models\OrdenArea;
use App\Models\OrdenOperacion;
use App\Models\Producto;
use App\Models\Repisa;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\Ordenes\GastoRealOrdenService;
use App\Services\Ordenes\ResumenEjecucionOrdenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1906E1NotaSalidaAreaEmpleadoTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private Empleado $receptor;
    private OrdenOperacion $orden;
    private Producto $producto;
    private Repisa $repisa;
    private Inventario $inventario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = User::query()->create([
            'role_id' => Role::query()->where('codigo', 'ALMACEN')->firstOrFail()->id,
            'username' => 'almacen_e1',
            'email' => 'almacen_e1@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
        $this->receptor = Empleado::query()->create([
            'nombre_completo' => 'Luis Técnico Prueba',
            'dni' => '71234567',
            'estado' => true,
            'registrado_por' => $this->almacen->id,
        ]);
        $empleadoAlmacen = Empleado::query()->create([
            'nombre_completo' => 'Encargado de Almacén',
            'dni' => '72345678',
            'estado' => true,
            'registrado_por' => $this->almacen->id,
        ]);
        $this->almacen->update(['empleado_id' => $empleadoAlmacen->id]);

        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );
        $cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20619060011',
            'ruc' => '20619060011',
            'razon_social' => 'Cliente E1 SAC',
            'estado' => true,
        ]);
        $tipoOrden = TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OP'],
            ['nombre' => 'Producción', 'estado' => true]
        );
        $this->orden = OrdenOperacion::query()->create([
            'tipo_orden_id' => $tipoOrden->id,
            'cliente_id' => $cliente->id,
            'codigo_orden' => 'OP-E1-001',
            'numero_correlativo' => 1,
            'anio' => (int) now()->format('Y'),
            'fecha_apertura' => now()->toDateString(),
            'descripcion' => 'Orden para nota por área y empleado',
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
            'codigo' => 'R-E1',
            'descripcion' => 'Repisa E1',
            'estado' => true,
        ]);
        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'MAT-E1',
            'descripcion' => 'Material de prueba E1',
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

    public function test_nota_guarda_area_receptor_y_usuario_que_entrega(): void
    {
        $this->actingAs($this->almacen)
            ->post(route('notas-salida.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('notas_salida', [
            'orden_operacion_id' => $this->orden->id,
            'area_trabajo' => 'GENERAL',
            'recibido_por_empleado_id' => $this->receptor->id,
            'recibido_por_nombre' => 'Luis Técnico Prueba',
            'recibido_por_dni' => '71234567',
            'entregado_a' => 'Luis Técnico Prueba',
            'entregado_por_nombre' => 'Encargado de Almacén',
            'entregado_por_dni' => '72345678',
            'registrado_por' => $this->almacen->id,
            'confirmado_por' => $this->almacen->id,
        ]);
    }

    public function test_rechaza_empleado_inactivo_y_area_ajena_a_la_orden(): void
    {
        $this->receptor->update(['estado' => false]);

        $this->actingAs($this->almacen)
            ->post(route('notas-salida.store'), $this->payload([
                'area_trabajo' => 'SISTEMA NEUMÁTICO',
            ]))
            ->assertSessionHasErrors(['recibido_por_empleado_id', 'area_trabajo']);

        $this->assertDatabaseCount('notas_salida', 0);
    }

    public function test_formulario_muestra_lista_de_empleados_con_nombre_y_dni(): void
    {
        $this->actingAs($this->almacen)
            ->get(route('notas-salida.create', [
                'motivo_salida' => 'ORDEN_OPERACION',
                'orden_operacion_id' => $this->orden->id,
            ]))
            ->assertOk()
            ->assertSee('Área del trabajo')
            ->assertSee('GENERAL')
            ->assertSee('Luis Técnico Prueba')
            ->assertSee('DNI 71234567');
    }

    public function test_selector_de_origen_usa_rejilla_flexible_sin_desbordarse(): void
    {
        $this->actingAs($this->almacen)
            ->get(route('notas-salida.create', [
                'motivo_salida' => 'ORDEN_OPERACION',
                'orden_operacion_id' => $this->orden->id,
            ]))
            ->assertOk()
            ->assertSee('order-selector-form--note-output', false);

        $css = file_get_contents(public_path('css/hidroil-admin.css'));

        $this->assertStringContainsString(
            'grid-template-columns: minmax(0, .75fr) minmax(0, 1.35fr) minmax(0, .85fr);',
            $css
        );
        $this->assertStringContainsString(
            '.order-selector-form--note-output .order-selector-form__submit',
            $css
        );
    }

    public function test_subareas_homonimas_conservan_sus_materiales_y_salidas_por_id(): void
    {
        $hojas = [];
        foreach (['ESTRUCTURA', 'SISTEMA NEUMÁTICO'] as $indice => $nombre) {
            $padre = $this->area($nombre);
            $hija = $this->area('MONTAJE', $padre->id);
            $hojas[] = $hija;
            MaterialPlanificadoOrdenArea::create([
                'orden_operacion_id' => $this->orden->id, 'orden_area_id' => $hija->id,
                'producto_id' => $this->producto->id, 'codigo_producto' => $this->producto->codigo,
                'descripcion_producto' => $this->producto->descripcion, 'unidad' => 'UND',
                'cantidad_estimada' => $indice === 0 ? 2 : 5,
                'costo_unitario_estimado_soles' => 5, 'costo_total_estimado_soles' => $indice === 0 ? 10 : 25,
                'congelado_en' => now(),
            ]);
        }

        $this->actingAs($this->almacen)->get(route('notas-salida.create', [
            'motivo_salida' => 'ORDEN_OPERACION', 'orden_operacion_id' => $this->orden->id,
            'orden_area_id' => $hojas[1]->id,
        ]))->assertOk()->assertSee('ESTRUCTURA / MONTAJE')->assertSee('SISTEMA NEUMÁTICO / MONTAJE')
            ->assertSee('name="orden_area_id" value="'.$hojas[1]->id.'"', false);

        // El texto MONTAJE por sí solo ya no elige arbitrariamente la primera subárea.
        $this->post(route('notas-salida.store'), $this->payload(['area_trabajo' => 'MONTAJE']))
            ->assertSessionHasErrors('area_trabajo');
        $this->post(route('notas-salida.store'), $this->payload([
            'area_trabajo' => 'MONTAJE', 'orden_area_id' => $hojas[1]->id,
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('notas_salida', [
            'orden_operacion_id' => $this->orden->id,
            'orden_area_id' => $hojas[1]->id,
            'area_trabajo' => 'MONTAJE', 'estado' => 'CONFIRMADA',
        ]);
        $resumen = app(ResumenEjecucionOrdenService::class)->construir($this->orden, false)['comparacion_materiales'];
        $this->assertSame(0.0, (float) $resumen->firstWhere('area', 'ESTRUCTURA / MONTAJE')['real']);
        $this->assertSame(1.0, (float) $resumen->firstWhere('area', 'SISTEMA NEUMÁTICO / MONTAJE')['real']);
        $gasto = app(GastoRealOrdenService::class)->construir($this->orden);
        $this->assertSame(2, count($gasto['materiales']));
        $this->assertSame(1.0, (float) collect($gasto['materiales'])->firstWhere('area', 'SISTEMA NEUMÁTICO / MONTAJE')['real']);
    }

    public function test_retorno_reutilizable_libera_el_plan_de_la_subarea_de_la_salida_original(): void
    {
        $padreA = $this->area('ESTRUCTURA');
        $padreB = $this->area('SISTEMA NEUMÁTICO');
        $areaA = $this->area('MONTAJE', $padreA->id);
        $areaB = $this->area('MONTAJE', $padreB->id);
        foreach ([$areaA, $areaB] as $area) {
            MaterialPlanificadoOrdenArea::create([
                'orden_operacion_id' => $this->orden->id, 'orden_area_id' => $area->id,
                'producto_id' => $this->producto->id, 'codigo_producto' => $this->producto->codigo,
                'descripcion_producto' => $this->producto->descripcion, 'unidad' => 'UND',
                'cantidad_estimada' => 2, 'costo_unitario_estimado_soles' => 5,
                'costo_total_estimado_soles' => 10, 'congelado_en' => now(),
            ]);
        }

        $this->actingAs($this->almacen)->post(route('notas-salida.store'), $this->payload([
            'orden_area_id' => $areaA->id,
            'area_trabajo' => 'MONTAJE',
            'detalles' => [[
                'inventario_id' => $this->inventario->id,
                'producto_id' => $this->producto->id,
                'repisa_id' => $this->repisa->id,
                'tratamiento' => 'CONSUMO', 'cantidad' => 2,
            ]],
        ]))->assertSessionHasNoErrors();
        $salida = NotaSalida::query()->firstOrFail();
        $this->post(route('notas-ingreso.store'), [
            'motivo_ingreso' => 'RETORNO_MATERIAL',
            'nota_salida_id' => $salida->id,
            'devuelto_por_empleado_id' => $this->receptor->id,
            'fecha_ingreso' => now()->toDateString(),
            'detalles' => [[
                'nota_salida_detalle_id' => $salida->detalles()->firstOrFail()->id,
                'producto_id' => $this->producto->id,
                'repisa_id' => $this->repisa->id,
                'cantidad' => 1,
            ]],
        ])->assertSessionHasNoErrors();
        NotaIngreso::query()->firstOrFail()->update([
            'orden_area_id' => $areaB->id, 'area_trabajo' => 'MONTAJE',
        ]);

        $this->get(route('notas-salida.create', [
            'motivo_salida' => 'ORDEN_OPERACION',
            'orden_operacion_id' => $this->orden->id,
            'orden_area_id' => $areaA->id,
        ]))->assertOk()->assertSee('data-pendiente-orden="1"', false)
            ->assertSee('Retornado')->assertSee('Consumido');

        $this->post(route('notas-salida.store'), $this->payload([
            'orden_area_id' => $areaA->id,
            'area_trabajo' => 'MONTAJE',
        ]))->assertSessionHasNoErrors();
        $detalle = NotaSalida::query()->latest('id')->firstOrFail()->detalles()->firstOrFail();
        $this->assertSame(1.0, (float) $detalle->cantidad_planificada_aplicada);
        $this->assertSame(0.0, (float) $detalle->cantidad_excedente);
        $this->assertSame(2, NotaSalida::query()->count());
    }

    private function area(string $nombre, ?int $padreId = null): OrdenArea
    {
        return OrdenArea::create([
            'orden_operacion_id' => $this->orden->id, 'area_padre_id' => $padreId,
            'nombre' => $nombre, 'nombre_normalizado' => mb_strtoupper($nombre),
            'orden_secuencia' => 1, 'estado' => 'ACTIVA', 'origen' => 'COTIZACION',
        ]);
    }

    private function payload(array $cambios = []): array
    {
        return array_replace_recursive([
            'motivo_salida' => 'ORDEN_OPERACION',
            'orden_operacion_id' => $this->orden->id,
            'area_trabajo' => 'GENERAL',
            'recibido_por_empleado_id' => $this->receptor->id,
            'fecha_salida' => now()->toDateString(),
            'detalles' => [[
                'inventario_id' => $this->inventario->id,
                'producto_id' => $this->producto->id,
                'repisa_id' => $this->repisa->id,
                'tratamiento' => 'CONSUMO',
                'cantidad' => 1,
            ]],
        ], $cambios);
    }
}
