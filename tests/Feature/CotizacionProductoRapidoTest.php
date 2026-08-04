<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Role;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CotizacionProductoRapidoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private UnidadMedida $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        $rol = Role::query()
            ->where('codigo', 'ADMINISTRADOR')
            ->firstOrFail();

        $this->usuario = User::query()->create([
            'role_id' => $rol->id,
            'username' => 'admin_pruebas',
            'email' => 'admin.pruebas@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);

        $this->unidad = UnidadMedida::query()->create([
            'codigo' => 'UND',
            'nombre' => 'Unidad',
            'estado' => true,
        ]);
    }

    public function test_busca_productos_activos_por_codigo_o_descripcion(): void
    {
        Producto::query()->create([
            'unidad_medida_id' => $this->unidad->id,
            'codigo' => '10011',
            'descripcion' => 'Filtro hidráulico Parker',
            'estado' => true,
        ]);
        Producto::query()->create([
            'unidad_medida_id' => $this->unidad->id,
            'codigo' => 'OCULTO-01',
            'descripcion' => 'Filtro hidráulico inactivo',
            'estado' => false,
        ]);

        $response = $this->actingAs($this->usuario)->getJson(
            route('cotizaciones-proveedor.productos.buscar', ['q' => 'hidráulico'])
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'productos')
            ->assertJsonPath('productos.0.codigo', '10011')
            ->assertJsonPath('productos.0.unidad', 'UND');
    }

    public function test_registra_un_producto_nuevo_sin_stock_y_lo_devuelve_para_seleccionarlo(): void
    {
        Producto::query()->create([
            'unidad_medida_id' => $this->unidad->id,
            'codigo' => '10011',
            'descripcion' => 'Producto existente',
            'estado' => true,
        ]);

        $response = $this->actingAs($this->usuario)->postJson(
            route('cotizaciones-proveedor.productos.registro-rapido'),
            [
                'codigo' => ' 10012 ',
                'descripcion' => '  Bomba hidráulica nueva  ',
                'unidad_medida_id' => $this->unidad->id,
                'marca_principal_id' => null,
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('producto.codigo', '10012')
            ->assertJsonPath('producto.descripcion', 'Bomba hidráulica nueva')
            ->assertJsonPath('siguiente_codigo', '10013');

        $this->assertDatabaseHas('productos', [
            'codigo' => '10012',
            'descripcion' => 'Bomba hidráulica nueva',
            'estado' => true,
        ]);
        $this->assertDatabaseCount('inventarios', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_rechaza_un_codigo_duplicado_sin_crear_otro_producto(): void
    {
        Producto::query()->create([
            'unidad_medida_id' => $this->unidad->id,
            'codigo' => '10011',
            'descripcion' => 'Producto existente',
            'estado' => true,
        ]);

        $response = $this->actingAs($this->usuario)->postJson(
            route('cotizaciones-proveedor.productos.registro-rapido'),
            [
                'codigo' => '10011',
                'descripcion' => 'Intento duplicado',
                'unidad_medida_id' => $this->unidad->id,
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('codigo');

        $this->assertDatabaseCount('productos', 1);
    }
}
