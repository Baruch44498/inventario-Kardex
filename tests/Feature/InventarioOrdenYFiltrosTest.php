<?php

namespace Tests\Feature;

use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Repisa;
use App\Models\Role;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventarioOrdenYFiltrosTest extends TestCase
{
    use RefreshDatabase;

    public function test_urgencia_es_predeterminada_y_vistas_rapidas_y_orden_son_controlados(): void
    {
        $usuario = User::query()->create([
            'role_id' => Role::query()->where('codigo', 'ALMACEN')->firstOrFail()->id,
            'username' => 'inventario_orden',
            'email' => 'inventario_orden@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
        $unidad = UnidadMedida::query()->firstOrCreate(['codigo' => 'UND'], ['nombre' => 'Unidad', 'estado' => true]);
        $repisa = Repisa::query()->create(['codigo' => 'R-ORDEN', 'estado' => true]);

        foreach ([
            ['A-SIN', 0, 0, null],
            ['B-BAJO', 2, 5, 10],
            ['C-NORMAL', 10, 0, null],
        ] as [$codigo, $stock, $minimo, $maximo]) {
            $producto = Producto::query()->create([
                'codigo' => $codigo,
                'descripcion' => $codigo,
                'unidad_medida_id' => $unidad->id,
                'estado' => true,
            ]);
            Inventario::query()->create([
                'producto_id' => $producto->id,
                'repisa_id' => $repisa->id,
                'stock_actual' => $stock,
                'stock_minimo' => $minimo,
                'stock_maximo' => $maximo,
                'costo_promedio_soles' => 1,
            ]);
        }

        $this->actingAs($usuario);
        $this->assertSame(['B-BAJO', 'A-SIN', 'C-NORMAL'], $this->codigos([]));
        $this->assertSame(['B-BAJO'], $this->codigos(['vista' => 'atencion']));
        $this->assertSame(['B-BAJO', 'C-NORMAL'], $this->codigos(['vista' => 'con_stock']));
        $this->assertSame(['A-SIN', 'B-BAJO', 'C-NORMAL'], $this->codigos(['orden' => 'codigo']));
        $this->assertSame(['C-NORMAL', 'B-BAJO', 'A-SIN'], $this->codigos(['orden' => 'stock_fisico']));
        $this->assertSame(['B-BAJO', 'A-SIN', 'C-NORMAL'], $this->codigos(['orden' => 'invalido', 'vista' => 'invalida']));

        $this->get(route('inventario.index', ['estado_stock' => 'SIN_STOCK']))
            ->assertOk()
            ->assertSee('Sin existencias')
            ->assertSee('badge--neutral', false);
    }

    private function codigos(array $query): array
    {
        $response = $this->get(route('inventario.index', $query))->assertOk();
        $this->assertSame(15, $response->viewData('inventarios')->perPage());

        return $response->viewData('inventarios')->getCollection()->pluck('producto_codigo')->all();
    }
}
