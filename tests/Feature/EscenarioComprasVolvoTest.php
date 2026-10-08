<?php

namespace Tests\Feature;

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\NotaIngreso;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EscenarioComprasVolvoTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_compras_y_notas_reales_con_precios_del_excel_una_sola_vez(): void
    {
        $usuario = User::query()->create([
            'role_id' => Role::query()->firstOrCreate(
                ['codigo' => 'ALMACEN'], ['nombre' => 'Almacén', 'estado' => true]
            )->id,
            'username' => 'escenario_volvo',
            'email' => 'escenario_volvo@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);

        $this->artisan('hidroil:inventario-volvo --aplicar')->assertSuccessful();
        $this->artisan('hidroil:escenario-volvo')->assertSuccessful();
        $this->assertSame(0, NotaIngreso::query()->count());

        $opciones = ['--aplicar' => true, '--usuario' => $usuario->id];
        $this->artisan('hidroil:escenario-volvo', $opciones)->assertSuccessful();

        $this->assertSame(3, OrdenCompra::query()->where('codigo', 'like', 'OC-DEMO-VOLVO-%')->count());
        $this->assertSame(3, NotaIngreso::query()->where('estado', 'CONFIRMADA')->count());
        $this->assertSame(144, MovimientoInventario::query()->where('motivo', 'COMPRA')->count());
        $this->assertSame(33, Inventario::query()->where('stock_actual', 0)->count());

        $plancha = Producto::query()->where('codigo', '10154')->firstOrFail();
        $inventario = Inventario::query()->where('producto_id', $plancha->id)->firstOrFail();
        $this->assertEqualsWithDelta(922, (float) $inventario->costo_promedio_soles, 0.0001);
        $this->assertTrue(NotaIngreso::query()->whereHas('ordenCompra.proveedor',
            fn ($proveedor) => $proveedor->where('razon_social', 'PROVEEDOR DEMO VOLVO 03'))
            ->whereHas('detalles', fn ($detalle) => $detalle->where('producto_id', $plancha->id))
            ->exists());

        $this->artisan('hidroil:escenario-volvo', $opciones)->assertSuccessful();
        $this->assertSame(3, NotaIngreso::query()->count());
        $this->assertSame(144, MovimientoInventario::query()->where('motivo', 'COMPRA')->count());
    }
}
