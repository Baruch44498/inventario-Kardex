<?php

namespace Tests\Feature;

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Repisa;
use App\Models\Role;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1801KardexValorizadoTest extends TestCase
{
    use RefreshDatabase;

    private User $administrador;

    private User $almacen;

    private Producto $producto;

    private Repisa $repisa;

    private Inventario $inventario;

    private MovimientoInventario $movimientoCompra;

    private MovimientoInventario $movimientoConsumo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrador = $this->usuarioConRol('ADMINISTRADOR', 'admin_kardex');
        $this->almacen = $this->usuarioConRol('ALMACEN', 'almacen_kardex');

        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );

        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'KDX-001',
            'descripcion' => 'Producto de prueba para Kardex valorizado',
            'estado' => true,
        ]);

        $this->repisa = Repisa::query()->create([
            'codigo' => 'R-KDX-01',
            'descripcion' => 'Repisa de Kardex',
            'estado' => true,
        ]);

        $this->inventario = Inventario::query()->create([
            'producto_id' => $this->producto->id,
            'repisa_id' => $this->repisa->id,
            'stock_actual' => 8,
            'stock_minimo' => 1,
            'stock_maximo' => 20,
            'costo_promedio_soles' => 5,
        ]);

        $this->movimientoCompra = $this->crearMovimiento([
            'tipo_movimiento' => 'ENTRADA',
            'motivo' => 'COMPRA',
            'origen_tipo' => 'NOTA_INGRESO',
            'origen_id' => 10,
            'cantidad' => 10,
            'stock_anterior' => 0,
            'stock_posterior' => 10,
            'costo_unitario' => 5,
            'costo_promedio_anterior' => 0,
            'costo_promedio_nuevo' => 5,
            'fecha_movimiento' => now()->subHour(),
        ]);

        $this->movimientoConsumo = $this->crearMovimiento([
            'tipo_movimiento' => 'SALIDA',
            'motivo' => 'CONSUMO_ORDEN',
            'origen_tipo' => 'NOTA_SALIDA',
            'origen_id' => 20,
            'cantidad' => 2,
            'stock_anterior' => 10,
            'stock_posterior' => 8,
            'costo_unitario' => 5,
            'costo_promedio_anterior' => 5,
            'costo_promedio_nuevo' => 5,
            'fecha_movimiento' => now(),
        ]);
    }

    public function test_administrador_consulta_movimientos_y_saldos_valorizados(): void
    {
        $this->actingAs($this->administrador)
            ->get(route('kardex.index'))
            ->assertOk()
            ->assertSee('Kardex valorizado')
            ->assertSee('KDX-001')
            ->assertSee('R-KDX-01')
            ->assertSee('50.00')
            ->assertSee('10.00')
            ->assertSee('40.00')
            ->assertSee('Costo promedio')
            ->assertSee('Saldo valorizado');
        $inventarioActual = $this->inventario->fresh();

        $this->assertSame(
            40.0,
            (float) $inventarioActual->stock_actual * (float) $inventarioActual->costo_promedio_soles
        );
    }

    public function test_filtros_por_producto_repisa_tipo_y_fecha_funcionan(): void
    {
        $this->actingAs($this->administrador)
            ->get(route('kardex.index', [
                'producto_id' => $this->producto->id,
                'repisa_id' => $this->repisa->id,
                'tipo' => 'SALIDA',
                'desde' => now()->toDateString(),
                'hasta' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Consumo Orden')
            ->assertSee(route('movimientos.show', $this->movimientoConsumo), false)
            ->assertDontSee(route('movimientos.show', $this->movimientoCompra), false)
            ->assertSee('10.00')
            ->assertSee('40.00');
    }

    public function test_rol_sin_permiso_no_puede_abrir_kardex(): void
    {
        $this->actingAs($this->almacen)
            ->get(route('kardex.index'))
            ->assertForbidden();
    }

    public function test_saldo_inicial_y_final_respetan_el_corte_del_periodo(): void
    {
        $manana = now()->addDay()->toDateString();

        $this->actingAs($this->administrador)
            ->get(route('kardex.index', ['desde' => $manana, 'hasta' => $manana]))
            ->assertOk()
            ->assertViewHas('saldoInicial', 40.0)
            ->assertViewHas('saldoFinal', 40.0)
            ->assertViewHas('variacionValorizada', 0.0)
            ->assertSee('Balance valorizado del período')
            ->assertSee('Saldo inicial')
            ->assertSee('40.00')
            ->assertSee('Variación neta');
    }

    public function test_enlace_anterior_del_placeholder_redirige_al_kardex_real(): void
    {
        $this->actingAs($this->administrador)
            ->get(route('modulos.show', 'kardex'))
            ->assertRedirect(route('kardex.index'));
    }

    private function crearMovimiento(array $datos): MovimientoInventario
    {
        return MovimientoInventario::query()->create([
            'inventario_id' => $this->inventario->id,
            'producto_id' => $this->producto->id,
            'repisa_id' => $this->repisa->id,
            'observacion' => null,
            'registrado_por' => $this->almacen->id,
            ...$datos,
        ]);
    }

    private function usuarioConRol(string $codigoRol, string $username): User
    {
        return User::query()->create([
            'role_id' => Role::query()->where('codigo', $codigoRol)->firstOrFail()->id,
            'username' => $username,
            'email' => $username . '@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
    }
}
