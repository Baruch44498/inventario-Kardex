<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Role;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\Productos\GenerarCodigoProductoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1751CodigoAutomaticoProductoTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private User $logistica;
    private UnidadMedida $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = $this->crearUsuario('ALMACEN', 'almacen_1751');
        $this->logistica = $this->crearUsuario('COMERCIAL_LOGISTICA', 'logistica_1751');
        $this->unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
    }

    public function test_formulario_muestra_codigo_sugerido_segun_el_mayor_numerico(): void
    {
        $this->crearProducto('9', 'Producto numérico inicial');
        $this->crearProducto('10011', 'Producto numérico mayor');
        $this->crearProducto('FILT-99999', 'Código alfanumérico que no altera la secuencia');

        $this->actingAs($this->almacen)
            ->get(route('productos.create'))
            ->assertOk()
            ->assertSee('value="10012"', false)
            ->assertSee('Código sugerido automáticamente. Puedes cambiarlo antes de registrar.');

        $this->assertSame('10012', app(GenerarCodigoProductoService::class)->siguiente());
    }

    public function test_codigo_sugerido_es_compartido_con_el_alta_rapida_de_cotizacion(): void
    {
        $this->crearProducto('10011', 'Producto existente');

        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-proveedor.create'))
            ->assertOk()
            ->assertSee('data-next-code="10012"', false);
    }

    public function test_producto_puede_guardarse_con_codigo_sugerido_o_personalizado(): void
    {
        $this->crearProducto('10011', 'Producto existente');

        $this->actingAs($this->almacen)
            ->post(route('productos.store'), $this->datosProducto('10012', 'Producto con código sugerido'))
            ->assertRedirect();
        $this->actingAs($this->almacen)
            ->post(route('productos.store'), $this->datosProducto('BOMBA-PERSONAL', 'Producto con código personalizado'))
            ->assertRedirect();

        $this->assertDatabaseHas('productos', ['codigo' => '10012']);
        $this->assertDatabaseHas('productos', ['codigo' => 'BOMBA-PERSONAL']);
    }

    public function test_endpoint_advierte_similares_pero_no_bloquea_el_registro(): void
    {
        $this->crearProducto('10020', 'Filtro hidráulico de retorno Parker');

        $this->actingAs($this->almacen)
            ->postJson(route('productos.verificar-similitud'), [
                'descripcion' => 'Filtro hidráulico de retorno Parker',
            ])
            ->assertOk()
            ->assertJsonPath('hay_similares', true)
            ->assertJsonPath('productos_similares.0.codigo', '10020')
            ->assertJsonPath('productos_similares.0.puntaje', 100);

        $this->actingAs($this->almacen)
            ->post(route('productos.store'), $this->datosProducto(
                '10021',
                'Filtro hidráulico de retorno Parker'
            ))
            ->assertRedirect();

        $this->assertDatabaseCount('productos', 2);
    }

    public function test_endpoint_excluye_el_producto_editado_y_respeta_el_permiso(): void
    {
        $producto = $this->crearProducto('10030', 'Válvula hidráulica de control');

        $this->actingAs($this->almacen)
            ->postJson(route('productos.verificar-similitud'), [
                'descripcion' => $producto->descripcion,
                'excepto_id' => $producto->id,
            ])
            ->assertOk()
            ->assertJsonPath('hay_similares', false);

        $this->actingAs($this->logistica)
            ->postJson(route('productos.verificar-similitud'), [
                'descripcion' => $producto->descripcion,
            ])
            ->assertForbidden();
    }

    private function crearProducto(string $codigo, string $descripcion): Producto
    {
        return Producto::query()->create([
            'unidad_medida_id' => $this->unidad->id,
            'codigo' => $codigo,
            'descripcion' => $descripcion,
            'estado' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function datosProducto(string $codigo, string $descripcion): array
    {
        return [
            'codigo' => $codigo,
            'descripcion' => $descripcion,
            'id_unidad_medida' => $this->unidad->id,
            'id_marca_principal' => null,
            'activo' => 1,
        ];
    }

    private function crearUsuario(string $rol, string $username): User
    {
        return User::query()->create([
            'role_id' => Role::query()->where('codigo', $rol)->firstOrFail()->id,
            'username' => $username,
            'email' => "{$username}@example.com",
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
    }
}
