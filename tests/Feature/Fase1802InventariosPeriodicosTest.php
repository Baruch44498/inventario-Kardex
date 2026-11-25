<?php

namespace Tests\Feature;

use App\Models\Inventario;
use App\Models\InventarioPeriodico;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Repisa;
use App\Models\Role;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1802InventariosPeriodicosTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private User $logistica;
    private Repisa $repisa;
    private Inventario $inventarioA;
    private Inventario $inventarioB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = $this->usuarioConRol('ALMACEN', 'almacen_1802');
        $this->logistica = $this->usuarioConRol('COMERCIAL_LOGISTICA', 'logistica_1802');

        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $this->repisa = Repisa::query()->create([
            'codigo' => 'R-1802',
            'descripcion' => 'Repisa para inventario periódico',
            'estado' => true,
        ]);

        $productoA = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'IP-1802-A',
            'descripcion' => 'Producto A para conteo físico',
            'estado' => true,
        ]);
        $productoB = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'IP-1802-B',
            'descripcion' => 'Producto B para conteo físico',
            'estado' => true,
        ]);

        $this->inventarioA = Inventario::query()->create([
            'producto_id' => $productoA->id,
            'repisa_id' => $this->repisa->id,
            'stock_actual' => 10,
            'stock_minimo' => 1,
            'stock_maximo' => 20,
            'costo_promedio_soles' => 5,
        ]);
        $this->inventarioB = Inventario::query()->create([
            'producto_id' => $productoB->id,
            'repisa_id' => $this->repisa->id,
            'stock_actual' => 4,
            'stock_minimo' => 1,
            'stock_maximo' => 20,
            'costo_promedio_soles' => 10,
        ]);
    }

    public function test_la_consulta_por_prefijo_respeta_el_formato_los_permisos_y_el_limite(): void
    {
        $repisas = collect(range(1, 44))->map(fn ($numero) => [
            'codigo' => sprintf('AA%03d', $numero),
            'descripcion' => $numero === 11 ? 'Zona de engrase' : null,
            'estado' => true,
        ])->concat(collect(range(1, 205))->map(fn ($numero) => [
            'codigo' => sprintf('AB%03d', $numero),
            'descripcion' => null,
            'estado' => true,
        ]));
        Repisa::query()->insert($repisas->all());
        Repisa::query()->insert([
            ['codigo' => 'AA045', 'descripcion' => null, 'estado' => false],
            ['codigo' => 'XAA011', 'descripcion' => null, 'estado' => true],
            ['codigo' => 'A_01', 'descripcion' => null, 'estado' => true],
        ]);

        $ruta = fn ($prefijo) => route('inventarios-periodicos.create', ['prefijo' => $prefijo]);
        $this->actingAs($this->logistica)->getJson($ruta('AA'))->assertForbidden();
        $this->actingAs($this->almacen);

        $this->getJson($ruta('  '))->assertExactJson(['repisas' => [], 'total' => 0]);
        $this->getJson($ruta('  aa  '))->assertOk()
            ->assertJsonPath('total', 44)->assertJsonCount(44, 'repisas')
            ->assertJsonPath('repisas.0.codigo', 'AA001')->assertJsonPath('repisas.43.codigo', 'AA044');
        $repisa = Repisa::query()->where('codigo', 'AA011')->firstOrFail();
        $this->getJson($ruta('AA011'))->assertExactJson([
            'repisas' => [['id' => $repisa->id, 'codigo' => 'AA011', 'descripcion' => 'Zona de engrase']],
            'total' => 1,
        ]);
        $this->getJson($ruta('ab'))->assertOk()
            ->assertJsonPath('total', 205)->assertJsonCount(200, 'repisas')
            ->assertJsonPath('repisas.199.codigo', 'AB200');
        $this->getJson($ruta('A_'))->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('repisas.0.codigo', 'A_01');
        $this->getJson($ruta('A%'))->assertExactJson(['repisas' => [], 'total' => 0]);
        $this->getJson($ruta(str_repeat('A', 41)))->assertUnprocessable()->assertJsonValidationErrors('prefijo');

        $this->get($ruta('AA011'))->assertOk()->assertHeader('Vary', 'Accept')
            ->assertSee('Seleccionar repisas')->assertSee('Zona de engrase')
            ->assertSee('form="periodic-shelf-search-form"', false);
    }

    public function test_recupera_repisas_de_otro_sector_y_errores_de_apertura(): void
    {
        $inactiva = Repisa::query()->create(['codigo' => 'BB001', 'estado' => false]);
        $ruta = route('inventarios-periodicos.create', ['prefijo' => 'R-18']);
        $apertura = route('inventarios-periodicos.store');
        $this->actingAs($this->almacen);

        $this->from($ruta)->post($apertura, [
            'repisa_ids' => [$this->repisa->id, $inactiva->id],
            'observacion' => 'Recorrido entre sectores',
        ])->assertRedirect($ruta)->assertSessionHasErrors('repisa_ids');

        $this->get($ruta)->assertOk()->assertSee('R-1802')
            ->assertSee('BB001')->assertSee('No disponible')
            ->assertSee('Todas las repisas seleccionadas deben estar activas.')
            ->assertSee('Recorrido entre sectores');

        $this->from($ruta)->post($apertura, [
            'repisa_ids' => [$this->repisa->id, $this->repisa->id],
            'observacion' => str_repeat('a', 501),
        ])->assertRedirect($ruta)->assertSessionHasErrors(['repisa_ids.0', 'repisa_ids.1', 'observacion']);

        $respuesta = $this->get($ruta)->assertOk()->assertSee('periodic-shelf-observation-error');
        $this->assertSame(1, substr_count($respuesta->getContent(), 'data-code="R-1802"'));
        $this->assertSame(1, substr_count($respuesta->getContent(), 'method="POST" action="'.$apertura.'"'));

        $vacia = Repisa::query()->create(['codigo' => 'CC001', 'estado' => true]);
        $this->from($ruta)->post($apertura, ['repisa_id' => $vacia->id])
            ->assertRedirect($ruta)->assertSessionHasErrors('repisa_id');
        $this->get($ruta)->assertOk()->assertSee('CC001')
            ->assertSee('La repisa no tiene productos registrados para contar.');
    }

    public function test_abre_una_repisa_con_el_campo_unificado_y_muestra_conflictos_al_reintentar(): void
    {
        $this->actingAs($this->almacen);
        $ruta = route('inventarios-periodicos.create');
        $datos = ['repisa_ids' => [$this->repisa->id], 'observacion' => 'Conteo individual'];
        $apertura = route('inventarios-periodicos.store');

        $this->post($apertura, $datos)->assertRedirect();
        $conteo = InventarioPeriodico::query()->sole();
        $this->assertSame([$this->repisa->id], $conteo->repisas()->pluck('repisas.id')->all());
        $this->assertSame('Conteo individual', $conteo->observacion);

        $this->from($ruta)->post($apertura, $datos)->assertRedirect($ruta)->assertSessionHasErrors('repisa_ids');
        $this->get($ruta)->assertOk()->assertSee('Una de las repisas ya tiene un inventario periódico abierto.');
    }

    public function test_almacen_abre_conteo_y_congela_stock_y_valor_sin_mover_kardex(): void
    {
        $this->actingAs($this->almacen)
            ->post(route('inventarios-periodicos.store'), [
                'repisa_id' => $this->repisa->id,
                'observacion' => 'Conteo mensual',
            ])
            ->assertRedirect();

        $periodico = InventarioPeriodico::query()->with('detalles')->firstOrFail();

        $this->assertSame('ABIERTO', $periodico->estado);
        $this->assertStringStartsWith('IP-', $periodico->codigo);
        $this->assertSame(2, $periodico->total_lineas);
        $this->assertSame(90.0, (float) $periodico->valor_sistema_soles);
        $this->assertSame(10.0, (float) $periodico->detalles
            ->firstWhere('inventario_id', $this->inventarioA->id)
            ->stock_sistema);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertSame(10.0, (float) $this->inventarioA->fresh()->stock_actual);
    }

    public function test_permite_guardar_avance_pero_no_cerrar_con_lineas_pendientes(): void
    {
        $periodico = $this->abrirConteo();
        $detalleA = $periodico->detalles->firstWhere('inventario_id', $this->inventarioA->id);

        $this->actingAs($this->almacen)
            ->patch(route('inventarios-periodicos.conteo', $periodico), [
                'detalles' => [
                    $detalleA->id => ['stock_contado' => 9, 'observacion' => 'Falta una unidad'],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(9.0, (float) $detalleA->fresh()->stock_contado);
        $this->assertSame(-1.0, (float) $detalleA->fresh()->diferencia);

        $this->actingAs($this->almacen)
            ->patch(route('inventarios-periodicos.cerrar', $periodico))
            ->assertSessionHasErrors('conteo');

        $this->assertSame('ABIERTO', $periodico->fresh()->estado);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_la_vista_distingue_conteo_pendiente_de_cero_e_identifica_la_repisa(): void
    {
        $periodico = $this->abrirConteo();
        $detalleA = $periodico->detalles->firstWhere('inventario_id', $this->inventarioA->id);

        $respuestaInicial = $this->actingAs($this->almacen)
            ->get(route('inventarios-periodicos.show', $periodico))
            ->assertOk()
            ->assertSee('Conteo físico del producto IP-1802-A en R-1802')
            ->assertSee('Conteo físico del producto IP-1802-B en R-1802');
        $this->assertSame(2, substr_count($respuestaInicial->getContent(), 'Sin contar'));

        $this->actingAs($this->almacen)
            ->patch(route('inventarios-periodicos.conteo', $periodico), [
                'detalles' => [
                    $detalleA->id => ['stock_contado' => 0, 'observacion' => 'Repisa vacía'],
                ],
            ])->assertRedirect();

        $respuestaActualizada = $this->actingAs($this->almacen)
            ->get(route('inventarios-periodicos.show', $periodico))
            ->assertOk()
            ->assertSee('Repisa vacía');
        $this->assertSame(1, substr_count($respuestaActualizada->getContent(), 'Sin contar'));
        $this->assertSame(0.0, (float) $detalleA->fresh()->stock_contado);
        $this->assertSame(-10.0, (float) $detalleA->fresh()->diferencia);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_cierre_aplica_sobrante_y_faltante_como_movimientos_auditables(): void
    {
        $periodico = $this->abrirConteo();
        $this->guardarTodosLosConteos($periodico, 8, 6);

        $this->actingAs($this->almacen)
            ->patch(route('inventarios-periodicos.cerrar', $periodico))
            ->assertRedirect();

        $periodico->refresh();
        $this->assertSame('CERRADO', $periodico->estado);
        $this->assertSame(2, $periodico->lineas_con_diferencia);
        $this->assertSame(10.0, (float) $periodico->valor_diferencia_soles);
        $this->assertSame(8.0, (float) $this->inventarioA->fresh()->stock_actual);
        $this->assertSame(6.0, (float) $this->inventarioB->fresh()->stock_actual);

        $this->assertDatabaseHas('movimientos_inventario', [
            'inventario_id' => $this->inventarioA->id,
            'tipo_movimiento' => 'SALIDA',
            'motivo' => 'AJUSTE_INVENTARIO',
            'origen_tipo' => 'INVENTARIO_PERIODICO',
            'origen_id' => $periodico->id,
            'cantidad' => 2,
        ]);
        $this->assertDatabaseHas('movimientos_inventario', [
            'inventario_id' => $this->inventarioB->id,
            'tipo_movimiento' => 'ENTRADA',
            'motivo' => 'AJUSTE_INVENTARIO',
            'origen_tipo' => 'INVENTARIO_PERIODICO',
            'origen_id' => $periodico->id,
            'cantidad' => 2,
        ]);
    }

    public function test_cierre_sin_diferencias_no_crea_movimientos(): void
    {
        $periodico = $this->abrirConteo();
        $this->guardarTodosLosConteos($periodico, 10, 4);

        $this->actingAs($this->almacen)
            ->patch(route('inventarios-periodicos.cerrar', $periodico))
            ->assertRedirect();

        $this->assertSame('CERRADO', $periodico->fresh()->estado);
        $this->assertSame(0, $periodico->fresh()->lineas_con_diferencia);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_bloquea_cierre_si_hubo_movimientos_despues_de_la_apertura(): void
    {
        $periodico = $this->abrirConteo();
        $this->guardarTodosLosConteos($periodico, 10, 4);

        MovimientoInventario::query()->create([
            'inventario_id' => $this->inventarioA->id,
            'producto_id' => $this->inventarioA->producto_id,
            'repisa_id' => $this->repisa->id,
            'tipo_movimiento' => 'SALIDA',
            'motivo' => 'USO_INTERNO',
            'origen_tipo' => 'NOTA_SALIDA',
            'origen_id' => 999,
            'cantidad' => 1,
            'stock_anterior' => 10,
            'stock_posterior' => 9,
            'costo_unitario' => 5,
            'costo_promedio_anterior' => 5,
            'costo_promedio_nuevo' => 5,
            'fecha_movimiento' => now(),
            'registrado_por' => $this->almacen->id,
        ]);

        $this->actingAs($this->almacen)
            ->patch(route('inventarios-periodicos.cerrar', $periodico))
            ->assertSessionHasErrors('conteo');

        $this->assertSame('ABIERTO', $periodico->fresh()->estado);
        $this->assertSame(10.0, (float) $this->inventarioA->fresh()->stock_actual);
    }

    public function test_logistica_solo_consulta_y_almacen_puede_anular_sin_ajustar_stock(): void
    {
        $periodico = $this->abrirConteo();

        $this->actingAs($this->logistica)
            ->get(route('inventarios-periodicos.index'))
            ->assertOk();
        $this->actingAs($this->logistica)
            ->get(route('inventarios-periodicos.create'))
            ->assertForbidden();
        $this->actingAs($this->logistica)
            ->patch(route('inventarios-periodicos.cerrar', $periodico))
            ->assertForbidden();

        $this->actingAs($this->almacen)
            ->patch(route('inventarios-periodicos.anular', $periodico), [
                'motivo_anulacion' => 'Se registró una salida durante el conteo.',
            ])
            ->assertRedirect();

        $this->assertSame('ANULADO', $periodico->fresh()->estado);
        $this->assertSame(10.0, (float) $this->inventarioA->fresh()->stock_actual);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_un_conteo_agrupa_repisas_y_ajusta_el_mismo_producto_por_ubicacion(): void
    {
        $segunda = Repisa::query()->create(['codigo' => 'AB002', 'estado' => true]);
        $inventarioSegunda = Inventario::query()->create([
            'producto_id' => $this->inventarioA->producto_id,
            'repisa_id' => $segunda->id,
            'stock_actual' => 22,
            'stock_minimo' => 0,
            'costo_promedio_soles' => 5,
        ]);

        $this->actingAs($this->almacen)->post(route('inventarios-periodicos.store'), [
            'repisa_ids' => [$this->repisa->id, $segunda->id],
        ])->assertRedirect();

        $periodico = InventarioPeriodico::query()->with(['detalles', 'repisas'])->latest('id')->firstOrFail();
        $this->assertCount(2, $periodico->repisas);
        $this->assertSame(3, $periodico->total_lineas);
        $this->assertSame(200.0, (float) $periodico->valor_sistema_soles);

        $this->actingAs($this->almacen)
            ->get(route('inventarios-periodicos.show', $periodico))
            ->assertOk()
            ->assertSee('Conteo físico del producto IP-1802-A en R-1802')
            ->assertSee('Conteo físico del producto IP-1802-A en AB002');

        $detalles = $periodico->detalles->keyBy('inventario_id');
        $this->actingAs($this->almacen)->patch(route('inventarios-periodicos.conteo', $periodico), [
            'detalles' => [
                $detalles[$this->inventarioA->id]->id => ['stock_contado' => 9],
                $detalles[$this->inventarioB->id]->id => ['stock_contado' => 4],
                $detalles[$inventarioSegunda->id]->id => ['stock_contado' => 23],
            ],
        ])->assertRedirect();

        $this->actingAs($this->almacen)->patch(route('inventarios-periodicos.cerrar', $periodico))
            ->assertRedirect();
        $this->assertSame(9.0, (float) $this->inventarioA->fresh()->stock_actual);
        $this->assertSame(23.0, (float) $inventarioSegunda->fresh()->stock_actual);
        $this->assertSame(2, $periodico->fresh()->lineas_con_diferencia);
        $this->assertDatabaseCount('movimientos_inventario', 2);
    }

    public function test_no_permite_repisas_superpuestas_ni_cerrar_sin_confirmar_una_repisa_vacia(): void
    {
        $vacia = Repisa::query()->create(['codigo' => 'AB003', 'estado' => true]);
        $this->actingAs($this->almacen)->post(route('inventarios-periodicos.store'), [
            'repisa_ids' => [$this->repisa->id, $vacia->id],
        ])->assertRedirect();
        $periodico = InventarioPeriodico::query()->with('detalles')->latest('id')->firstOrFail();

        $this->actingAs($this->almacen)->post(route('inventarios-periodicos.store'), [
            'repisa_id' => $vacia->id,
        ])->assertSessionHasErrors();

        $this->guardarTodosLosConteos($periodico, 10, 4);
        $this->actingAs($this->almacen)->patch(route('inventarios-periodicos.cerrar', $periodico))
            ->assertSessionHasErrors('conteo');
        $this->actingAs($this->almacen)->patch(route('inventarios-periodicos.confirmar-vacia', [$periodico, $vacia]))
            ->assertRedirect();
        $this->actingAs($this->almacen)->patch(route('inventarios-periodicos.cerrar', $periodico))
            ->assertRedirect();
        $this->assertSame('CERRADO', $periodico->fresh()->estado);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_bloquea_cierre_si_aparece_un_producto_en_una_repisa_del_grupo(): void
    {
        $segunda = Repisa::query()->create(['codigo' => 'AB004', 'estado' => true]);
        Inventario::query()->create([
            'producto_id' => $this->inventarioA->producto_id,
            'repisa_id' => $segunda->id,
            'stock_actual' => 1,
            'costo_promedio_soles' => 5,
        ]);
        $this->actingAs($this->almacen)->post(route('inventarios-periodicos.store'), [
            'repisa_ids' => [$this->repisa->id, $segunda->id],
        ])->assertRedirect();
        $periodico = InventarioPeriodico::query()->with('detalles')->latest('id')->firstOrFail();

        $nuevo = Producto::query()->whereKey($this->inventarioB->producto_id)->firstOrFail();
        Inventario::query()->create([
            'producto_id' => $nuevo->id,
            'repisa_id' => $segunda->id,
            'stock_actual' => 1,
            'costo_promedio_soles' => 10,
        ]);
        $this->actingAs($this->almacen)->patch(route('inventarios-periodicos.conteo', $periodico), [
            'detalles' => $periodico->detalles->mapWithKeys(fn ($detalle) => [
                $detalle->id => ['stock_contado' => $detalle->stock_sistema],
            ])->all(),
        ])->assertRedirect();
        $this->actingAs($this->almacen)->patch(route('inventarios-periodicos.cerrar', $periodico))
            ->assertSessionHasErrors('conteo');
        $this->assertSame('ABIERTO', $periodico->fresh()->estado);
    }

    public function test_un_hallazgo_en_repisa_vacia_queda_auditable_sin_crear_stock_automatico(): void
    {
        $vacia = Repisa::query()->create(['codigo' => 'AB005', 'estado' => true]);
        $this->actingAs($this->almacen)->post(route('inventarios-periodicos.store'), [
            'repisa_ids' => [$this->repisa->id, $vacia->id],
        ])->assertRedirect();
        $periodico = InventarioPeriodico::query()->with('detalles')->latest('id')->firstOrFail();
        $this->actingAs($this->almacen)->patch(route('inventarios-periodicos.hallazgo', [$periodico, $vacia]), [
            'descripcion' => 'Punto de engrase sin identificar',
            'cantidad' => 2,
        ])->assertRedirect();
        $this->guardarTodosLosConteos($periodico, 10, 4);
        $this->actingAs($this->almacen)->patch(route('inventarios-periodicos.cerrar', $periodico))
            ->assertRedirect();

        $this->assertDatabaseHas('inventario_periodico_repisas', [
            'inventario_periodico_id' => $periodico->id,
            'repisa_id' => $vacia->id,
            'hallazgo_descripcion' => 'Punto de engrase sin identificar',
            'hallazgo_cantidad' => 2,
        ]);
        $this->assertDatabaseMissing('inventarios', ['repisa_id' => $vacia->id]);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    private function abrirConteo(): InventarioPeriodico
    {
        $this->actingAs($this->almacen)
            ->post(route('inventarios-periodicos.store'), ['repisa_id' => $this->repisa->id]);

        return InventarioPeriodico::query()->with('detalles')->latest('id')->firstOrFail();
    }

    private function guardarTodosLosConteos(
        InventarioPeriodico $periodico,
        float $conteoA,
        float $conteoB
    ): void {
        $detalleA = $periodico->detalles->firstWhere('inventario_id', $this->inventarioA->id);
        $detalleB = $periodico->detalles->firstWhere('inventario_id', $this->inventarioB->id);

        $this->actingAs($this->almacen)
            ->patch(route('inventarios-periodicos.conteo', $periodico), [
                'detalles' => [
                    $detalleA->id => ['stock_contado' => $conteoA],
                    $detalleB->id => ['stock_contado' => $conteoB],
                ],
            ])
            ->assertRedirect();
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
