<?php

namespace Tests\Feature;

use App\Models\AlertaStock;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Repisa;
use App\Models\Requisicion;
use App\Models\Role;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1911AlertasRequerimientoTrazableTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private UnidadMedida $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = User::query()->create([
            'role_id' => Role::query()->where('codigo', 'ALMACEN')->firstOrFail()->id,
            'username' => 'almacen_1911',
            'email' => 'almacen_1911@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
        $this->unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
    }

    public function test_conserva_las_alertas_exactas_al_preparar_y_guardar_el_requerimiento(): void
    {
        [$producto, $alertaA] = $this->crearAlerta('MAT-1911-A', 'Producto trazable', 'R-1911-A');
        [, $alertaB] = $this->crearAlertaParaProducto($producto, 'R-1911-B');

        $this->actingAs($this->almacen)
            ->post(route('alertas.preparar-requerimiento'), [
                'alcance' => 'SELECCIONADAS',
                'alerta_ids' => [$alertaA->id, $alertaB->id],
            ])
            ->assertRedirect(route('requerimientos-compra.create'));

        $this->assertEqualsCanonicalizing(
            [$alertaA->id, $alertaB->id],
            session()->get('_old_input.alerta_ids')
        );

        $this->actingAs($this->almacen)
            ->post(route('requerimientos-compra.store'), $this->payload(
                $producto,
                [$alertaA->id, $alertaB->id]
            ))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $requerimiento = Requisicion::query()->firstOrFail();

        $this->assertSame('BORRADOR', $requerimiento->estado);
        $this->assertEqualsCanonicalizing(
            [$alertaA->id, $alertaB->id],
            $requerimiento->alertasStock()->pluck('alertas_stock.id')->all()
        );
        $this->assertSame('ACTIVA', $alertaA->fresh()->estado);
        $this->assertSame('ACTIVA', $alertaB->fresh()->estado);
    }

    public function test_al_enviar_marca_la_reposicion_en_curso_sin_mover_stock(): void
    {
        [$producto, $alerta] = $this->crearAlerta('MAT-1911-B', 'Producto enviado', 'R-1911-C');
        $stockAntes = (float) $alerta->inventario->stock_actual;
        $requerimiento = $this->guardarRequerimiento($producto, [$alerta->id]);

        $this->actingAs($this->almacen)
            ->patch(route('requerimientos-compra.enviar', $requerimiento))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $alerta->refresh();
        $this->assertSame('ENVIADA', $requerimiento->fresh()->estado);
        $this->assertSame('ATENDIDA', $alerta->estado);
        $this->assertSame($this->almacen->id, $alerta->atendida_por);
        $this->assertNotNull($alerta->atendida_en);
        $this->assertSame($stockAntes, (float) $alerta->inventario->fresh()->stock_actual);
    }

    public function test_la_alerta_vinculada_muestra_su_requerimiento_y_no_puede_prepararse_otra_vez(): void
    {
        [$producto, $alerta] = $this->crearAlerta('MAT-1911-C', 'Producto no duplicable', 'R-1911-D');
        $requerimiento = $this->guardarRequerimiento($producto, [$alerta->id]);

        $this->actingAs($this->almacen)
            ->get(route('alertas.index'))
            ->assertOk()
            ->assertSee('Reposición en curso')
            ->assertSee($requerimiento->codigo)
            ->assertSee(route('requerimientos-compra.show', $requerimiento));

        $this->actingAs($this->almacen)
            ->from(route('alertas.index'))
            ->post(route('alertas.preparar-requerimiento'), [
                'alcance' => 'SELECCIONADAS',
                'alerta_ids' => [$alerta->id],
            ])
            ->assertRedirect(route('alertas.index'))
            ->assertSessionHasErrors('alerta_ids');
    }

    public function test_no_permite_vincular_una_alerta_a_dos_requerimientos_abiertos(): void
    {
        [$producto, $alerta] = $this->crearAlerta('MAT-1911-D', 'Producto con control doble', 'R-1911-E');
        $primero = $this->guardarRequerimiento($producto, [$alerta->id]);

        $this->actingAs($this->almacen)
            ->post(route('requerimientos-compra.store'), $this->payload($producto, [$alerta->id]))
            ->assertSessionHasErrors('alerta_ids');

        $this->assertDatabaseCount('requisiciones', 1);
        $this->assertDatabaseHas('alerta_stock_requisicion', [
            'alerta_stock_id' => $alerta->id,
            'requisicion_id' => $primero->id,
        ]);
    }

    public function test_no_permite_crear_reposicion_desde_una_alerta_resuelta(): void
    {
        [$producto, $alerta] = $this->crearAlerta('MAT-1911-E', 'Producto ya resuelto', 'R-1911-F');
        $alerta->update([
            'estado' => 'RESUELTA',
            'resuelta_por' => $this->almacen->id,
            'resuelta_en' => now(),
        ]);

        $this->actingAs($this->almacen)
            ->post(route('requerimientos-compra.store'), $this->payload($producto, [$alerta->id]))
            ->assertSessionHasErrors('alerta_ids');

        $this->assertDatabaseCount('requisiciones', 0);
        $this->assertDatabaseCount('alerta_stock_requisicion', 0);
    }

    private function guardarRequerimiento(Producto $producto, array $alertaIds): Requisicion
    {
        $this->actingAs($this->almacen)
            ->post(route('requerimientos-compra.store'), $this->payload($producto, $alertaIds))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        return Requisicion::query()->latest('id')->firstOrFail();
    }

    private function payload(Producto $producto, array $alertaIds): array
    {
        return [
            'fecha_solicitud' => now()->toDateString(),
            'origen' => 'REPOSICION',
            'prioridad' => 'ALTA',
            'descripcion' => 'Reposición originada por alerta de stock.',
            'alerta_ids' => $alertaIds,
            'detalles' => [[
                'producto_id' => $producto->id,
                'cantidad_solicitada' => 5,
                'observacion' => 'Reponer hasta el nivel previsto.',
            ]],
        ];
    }

    /** @return array{Producto, AlertaStock} */
    private function crearAlerta(string $codigo, string $descripcion, string $repisaCodigo): array
    {
        $producto = Producto::query()->create([
            'unidad_medida_id' => $this->unidad->id,
            'codigo' => $codigo,
            'descripcion' => $descripcion,
            'estado' => true,
        ]);

        return $this->crearAlertaParaProducto($producto, $repisaCodigo);
    }

    /** @return array{Producto, AlertaStock} */
    private function crearAlertaParaProducto(Producto $producto, string $repisaCodigo): array
    {
        $repisa = Repisa::query()->create([
            'codigo' => $repisaCodigo,
            'descripcion' => 'Repisa '.$repisaCodigo,
            'estado' => true,
        ]);
        $inventario = Inventario::query()->create([
            'producto_id' => $producto->id,
            'repisa_id' => $repisa->id,
            'stock_actual' => 1,
            'stock_minimo' => 5,
            'stock_maximo' => 30,
            'costo_promedio_soles' => 10,
        ]);
        $alerta = AlertaStock::query()->create([
            'inventario_id' => $inventario->id,
            'producto_id' => $producto->id,
            'repisa_id' => $repisa->id,
            'tipo_alerta' => 'STOCK_MINIMO',
            'nivel' => 'CRITICA',
            'stock_actual' => 1,
            'stock_minimo' => 5,
            'mensaje' => 'Producto por debajo del mínimo.',
            'estado' => 'ACTIVA',
            'detectada_en' => now(),
        ]);

        return [$producto, $alerta];
    }
}
