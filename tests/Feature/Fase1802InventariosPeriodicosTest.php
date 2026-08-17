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
