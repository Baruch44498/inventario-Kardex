<?php

namespace Tests\Feature;

use App\Models\Marca;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Repisa;
use App\Models\Role;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogoBusquedaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $rol = Role::query()
            ->where('codigo', 'ADMINISTRADOR')
            ->firstOrFail();

        $this->usuario = User::query()->create([
            'role_id' => $rol->id,
            'username' => 'admin_catalogos',
            'email' => 'catalogos@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
    }

    public function test_busca_proveedores_por_ruc_o_nombre_y_excluye_inactivos(): void
    {
        $proveedorActivo = Proveedor::query()->create([
            'ruc' => '20123456789',
            'razon_social' => 'Comercial Hidráulica Norte SAC',
            'estado' => true,
        ]);
        Proveedor::query()->create([
            'ruc' => '20999999991',
            'razon_social' => 'Comercial Hidráulica Inactiva SAC',
            'estado' => false,
        ]);

        $response = $this->actingAs($this->usuario)->getJson(
            route('catalogos.proveedores.buscar', ['q' => 'Hidráulica'])
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $proveedorActivo->id)
            ->assertJsonPath('items.0.label', '20123456789 — Comercial Hidráulica Norte SAC');
    }

    public function test_los_buscadores_nunca_devuelven_mas_de_quince_resultados(): void
    {
        foreach (range(1, 20) as $indice) {
            Proveedor::query()->create([
                'ruc' => '20'.str_pad((string) $indice, 9, '0', STR_PAD_LEFT),
                'razon_social' => 'Proveedor '.str_pad((string) $indice, 2, '0', STR_PAD_LEFT),
                'estado' => true,
            ]);
        }

        $this->actingAs($this->usuario)
            ->getJson(route('catalogos.proveedores.buscar'))
            ->assertOk()
            ->assertJsonCount(15, 'items');
    }

    public function test_busca_productos_marcas_y_repisas_sin_descargar_catalogos_completos(): void
    {
        $unidad = UnidadMedida::query()->create([
            'codigo' => 'UND',
            'nombre' => 'Unidad',
            'estado' => true,
        ]);

        Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => '10011',
            'descripcion' => 'Filtro hidráulico de retorno',
            'estado' => true,
        ]);
        Marca::query()->create(['nombre' => 'Parker', 'estado' => true]);
        Repisa::query()->create([
            'codigo' => 'R-A-01',
            'descripcion' => 'Filtros hidráulicos',
            'estado' => true,
        ]);

        $this->actingAs($this->usuario)
            ->getJson(route('catalogos.productos.buscar', ['q' => '10011']))
            ->assertOk()
            ->assertJsonPath('items.0.label', '10011 — Filtro hidráulico de retorno');

        $this->actingAs($this->usuario)
            ->getJson(route('catalogos.marcas.buscar', ['q' => 'Park']))
            ->assertOk()
            ->assertJsonPath('items.0.label', 'Parker');

        $this->actingAs($this->usuario)
            ->getJson(route('catalogos.repisas.buscar', ['q' => 'R-A']))
            ->assertOk()
            ->assertJsonPath('items.0.label', 'R-A-01 — Filtros hidráulicos');
    }
}
