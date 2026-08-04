<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Proforma;
use App\Models\Repisa;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase17021CorreccionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_ruta_antigua_de_nueva_orden_redirige_a_cotizaciones(): void
    {
        $administrador = $this->usuarioConRol('ADMINISTRADOR', 'admin_hotfix');

        TipoOrden::query()->firstOrCreate(
            ['codigo' => 'OV'],
            ['nombre' => 'Orden de venta', 'estado' => true]
        );

        $this->actingAs($administrador)
            ->get(route('ordenes-operacion.create'))
            ->assertRedirect(route('cotizaciones-cliente.create'));
    }

    public function test_proforma_de_almacen_no_expone_moneda_y_siempre_guarda_referencias_en_pen(): void
    {
        $almacen = $this->usuarioConRol('ALMACEN', 'almacen_hotfix');
        [$cliente, $producto] = $this->catalogosComerciales();

        $this->actingAs($almacen)
            ->get(route('proformas.create'))
            ->assertOk()
            ->assertDontSee('data-document-currency', false)
            ->assertDontSee('data-exchange-input', false)
            ->assertSee('name="moneda" value="PEN"', false);

        $this->actingAs($almacen)
            ->post(route('proformas.store'), [
                'tipo_origen' => 'VENTA_DIRECTA',
                'cliente_id' => $cliente->id,
                'fecha_emision' => now()->toDateString(),
                'fecha_validez' => now()->addDays(7)->toDateString(),
                'moneda' => 'USD',
                'tipo_cambio' => 3.75,
                'detalles' => [[
                    'producto_id' => $producto->id,
                    'cantidad' => 2,
                    'igv_modo' => 'AGREGAR',
                ]],
            ])
            ->assertRedirect();

        $proforma = Proforma::query()->firstOrFail();

        $this->assertSame('PEN', $proforma->moneda);
        $this->assertNull($proforma->tipo_cambio);
        $this->assertEquals(100, (float) $proforma->detalles()->value('costo_referencia'));
    }

    private function catalogosComerciales(): array
    {
        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );
        $cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20123456789',
            'ruc' => '20123456789',
            'razon_social' => 'Cliente Hotfix SAC',
            'estado' => true,
        ]);
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $repisa = Repisa::query()->create([
            'codigo' => 'R-HOTFIX-01',
            'descripcion' => 'Pruebas del hotfix',
            'estado' => true,
        ]);
        $producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'PRD-HOTFIX-01',
            'descripcion' => 'Producto de prueba',
            'estado' => true,
        ]);
        Inventario::query()->create([
            'producto_id' => $producto->id,
            'repisa_id' => $repisa->id,
            'stock_actual' => 10,
            'stock_minimo' => 1,
            'stock_maximo' => 20,
            'costo_promedio_soles' => 100,
        ]);

        return [$cliente, $producto];
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
