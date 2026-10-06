<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogoVolvoCosteoTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_vista_previa_no_escribe_y_la_carga_es_repetible_sin_generar_stock(): void
    {
        $this->artisan('hidroil:importar-catalogo-volvo')->assertExitCode(0);
        $this->assertDatabaseCount('productos', 0);
        $this->assertDatabaseCount('proveedores', 0);

        $this->artisan('hidroil:importar-catalogo-volvo --aplicar')->assertExitCode(0);
        $this->assertDatabaseCount('productos', 179);
        $this->assertDatabaseCount('proveedores', 29);
        $this->assertDatabaseCount('producto_referencias_costeo', 189);
        $this->assertDatabaseCount('inventarios', 0);
        $this->assertDatabaseHas('producto_referencias_costeo', [
            'fila_excel' => 7,
            'costo_unitario_pen' => 922,
            'margen_porcentaje' => 5,
        ]);
        $this->assertDatabaseHas('productos', ['codigo' => 'VOLVO-0215', 'descripcion' => 'GUANTES CORTOS']);
        $this->assertDatabaseHas('producto_referencias_costeo', [
            'fila_excel' => 215,
            'proveedor_id' => null,
            'margen_porcentaje' => 0,
        ]);

        $this->artisan('hidroil:importar-catalogo-volvo --aplicar')->assertExitCode(0);
        $this->assertSame(179, DB::table('productos')->count());
        $this->assertSame(29, DB::table('proveedores')->count());
        $this->assertSame(189, DB::table('producto_referencias_costeo')->count());
    }
}
