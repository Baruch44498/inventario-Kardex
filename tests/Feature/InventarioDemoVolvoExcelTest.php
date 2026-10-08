<?php

namespace Tests\Feature;

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Repisa;
use App\Models\Role;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventarioDemoVolvoExcelTest extends TestCase
{
    use RefreshDatabase;

    public function test_reutiliza_productos_con_espacios_distintos_y_kilogramos_k_g_m(): void
    {
        $unidad = UnidadMedida::query()->firstOrCreate(['codigo' => 'UND'], ['nombre' => 'Unidad', 'estado' => true]);
        $kilogramo = UnidadMedida::query()->firstOrCreate(['codigo' => 'KGM'], ['nombre' => 'Kilogramo', 'estado' => true]);
        $codo = Producto::query()->create([
            'codigo' => '10054',
            'descripcion' => 'CODO DE ACERO SOLDABLE A-324 SCH-40 90ºX1"',
            'unidad_medida_id' => $unidad->id,
            'estado' => true,
        ]);
        $waype = Producto::query()->create([
            'codigo' => '10471',
            'descripcion' => 'WAYPE DE LIMPIEZA',
            'unidad_medida_id' => $kilogramo->id,
            'estado' => true,
        ]);

        $this->artisan('hidroil:inventario-volvo --aplicar')->assertSuccessful();

        $this->assertSame(177, Producto::query()->count());
        $this->assertSame(177, Inventario::query()->count());
        $this->assertSame('CODO DE ACERO SOLDABLE A-324 SCH-40 90ºX1"', $codo->fresh()->descripcion);
        $this->assertSame('KGM', $waype->fresh()->unidadMedida->codigo);
        $this->assertTrue(Inventario::query()->where('producto_id', $codo->id)->exists());
        $this->assertTrue(Inventario::query()->where('producto_id', $waype->id)->exists());
    }

    public function test_vista_previa_y_catalogo_no_inventan_existencias(): void
    {
        $this->artisan('hidroil:inventario-volvo')->assertSuccessful();
        $this->assertSame(0, Producto::query()->count());

        $this->artisan('hidroil:inventario-volvo --aplicar')->assertSuccessful();
        $this->assertSame(177, Producto::query()->count());
        $this->assertSame(3, Repisa::query()->count());
        $this->assertSame(177, Inventario::query()->count());
        $this->assertSame(0, MovimientoInventario::query()->count());
        $this->assertSame(0.0, (float) Inventario::query()->sum('stock_actual'));
        $this->assertTrue(Proveedor::query()->where('razon_social', 'STEELMARK S.A.')->exists());
        $this->assertEqualsWithDelta(922, Producto::query()->where('codigo', '10154')->firstOrFail()->costoPromedioActual(), 0.0001);

        $this->artisan('hidroil:inventario-volvo --aplicar')->assertSuccessful();
        $this->assertSame(177, Inventario::query()->count());
    }

    public function test_existencias_de_ejemplo_crean_kardex_una_sola_vez(): void
    {
        $usuario = User::query()->create([
            'role_id' => Role::query()->firstOrCreate(
                ['codigo' => 'ADMIN'], ['nombre' => 'Administrador', 'estado' => true]
            )->id,
            'username' => 'prueba_volvo',
            'email' => 'prueba_volvo@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);

        $this->artisan('hidroil:inventario-volvo', [
            '--aplicar' => true,
            '--stock-demo' => true,
            '--usuario' => $usuario->id,
        ])->assertSuccessful();

        $this->assertSame(5, MovimientoInventario::query()->where('origen_tipo', 'DEMO_VOLVO')->count());
        $this->assertSame(2.0, (float) Inventario::query()->whereHas('producto', fn ($q) => $q->where('codigo', '10154'))->value('stock_actual'));

        $this->artisan('hidroil:inventario-volvo', [
            '--aplicar' => true,
            '--stock-demo' => true,
            '--usuario' => $usuario->id,
        ])->assertSuccessful();
        $this->assertSame(5, MovimientoInventario::query()->where('origen_tipo', 'DEMO_VOLVO')->count());
    }

    public function test_actualiza_solo_la_referencia_antigua_sin_stock_ni_movimientos(): void
    {
        $this->artisan('hidroil:inventario-volvo --aplicar')->assertSuccessful();
        $inventario = Inventario::query()->whereHas('producto', fn ($q) => $q->where('codigo', '10154'))
            ->firstOrFail();
        $inventario->update(['costo_promedio_soles' => round(922 / 1.18, 4)]);

        $this->artisan('hidroil:inventario-volvo --aplicar')->assertSuccessful();

        $this->assertEqualsWithDelta(922, (float) $inventario->fresh()->costo_promedio_soles, 0.0001);
        $this->assertSame(0, MovimientoInventario::query()->count());
    }
}
