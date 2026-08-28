<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\Inventario;
use App\Models\MaterialRequeridoOrden;
use App\Models\OrdenOperacion;
use App\Models\Producto;
use App\Models\Repisa;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1906E1NotaSalidaAreaEmpleadoTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private Empleado $receptor;
    private OrdenOperacion $orden;
    private Producto $producto;
    private Repisa $repisa;
    private Inventario $inventario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = User::query()->create([
            'role_id' => Role::query()->where('codigo', 'ALMACEN')->firstOrFail()->id,
            'username' => 'almacen_e1',
            'email' => 'almacen_e1@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
        $this->receptor = Empleado::query()->create([
            'nombre_completo' => 'Luis Técnico Prueba',
            'dni' => '71234567',
            'estado' => true,
            'registrado_por' => $this->almacen->id,
        ]);
        $empleadoAlmacen = Empleado::query()->create([
            'nombre_completo' => 'Encargado de Almacén',
            'dni' => '72345678',
            'estado' => true,
            'registrado_por' => $this->almacen->id,
        ]);
        $this->almacen->update(['empleado_id' => $empleadoAlmacen->id]);

        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );
        $cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20619060011',
            'ruc' => '20619060011',
            'razon_social' => 'Cliente E1 SAC',
            'estado' => true,
        ]);
        $tipoOrden = TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OP'],
            ['nombre' => 'Producción', 'estado' => true]
        );
        $this->orden = OrdenOperacion::query()->create([
            'tipo_orden_id' => $tipoOrden->id,
            'cliente_id' => $cliente->id,
            'codigo_orden' => 'OP-E1-001',
            'numero_correlativo' => 1,
            'anio' => (int) now()->format('Y'),
            'fecha_apertura' => now()->toDateString(),
            'descripcion' => 'Orden para nota por área y empleado',
            'estado' => 'EN_PROCESO',
            'creado_por' => $this->almacen->id,
            'iniciado_por' => $this->almacen->id,
            'iniciado_en' => now(),
        ]);

        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $this->repisa = Repisa::query()->create([
            'codigo' => 'R-E1',
            'descripcion' => 'Repisa E1',
            'estado' => true,
        ]);
        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'MAT-E1',
            'descripcion' => 'Material de prueba E1',
            'estado' => true,
        ]);
        $this->inventario = Inventario::query()->create([
            'producto_id' => $this->producto->id,
            'repisa_id' => $this->repisa->id,
            'stock_actual' => 10,
            'stock_minimo' => 1,
            'stock_maximo' => 20,
            'costo_promedio_soles' => 5,
        ]);
        MaterialRequeridoOrden::query()->create([
            'orden_operacion_id' => $this->orden->id,
            'producto_id' => $this->producto->id,
            'cantidad_requerida' => 4,
            'cantidad_prevista' => 4,
            'creado_por' => $this->almacen->id,
        ]);
    }

    public function test_nota_guarda_area_receptor_y_usuario_que_entrega(): void
    {
        $this->actingAs($this->almacen)
            ->post(route('notas-salida.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('notas_salida', [
            'orden_operacion_id' => $this->orden->id,
            'area_trabajo' => 'GENERAL',
            'recibido_por_empleado_id' => $this->receptor->id,
            'recibido_por_nombre' => 'Luis Técnico Prueba',
            'recibido_por_dni' => '71234567',
            'entregado_a' => 'Luis Técnico Prueba',
            'entregado_por_nombre' => 'Encargado de Almacén',
            'entregado_por_dni' => '72345678',
            'registrado_por' => $this->almacen->id,
            'confirmado_por' => $this->almacen->id,
        ]);
    }

    public function test_rechaza_empleado_inactivo_y_area_ajena_a_la_orden(): void
    {
        $this->receptor->update(['estado' => false]);

        $this->actingAs($this->almacen)
            ->post(route('notas-salida.store'), $this->payload([
                'area_trabajo' => 'SISTEMA NEUMÁTICO',
            ]))
            ->assertSessionHasErrors(['recibido_por_empleado_id', 'area_trabajo']);

        $this->assertDatabaseCount('notas_salida', 0);
    }

    public function test_formulario_muestra_lista_de_empleados_con_nombre_y_dni(): void
    {
        $this->actingAs($this->almacen)
            ->get(route('notas-salida.create', [
                'motivo_salida' => 'ORDEN_OPERACION',
                'orden_operacion_id' => $this->orden->id,
            ]))
            ->assertOk()
            ->assertSee('Área del trabajo')
            ->assertSee('GENERAL')
            ->assertSee('Luis Técnico Prueba')
            ->assertSee('DNI 71234567');
    }

    public function test_selector_de_origen_usa_rejilla_flexible_sin_desbordarse(): void
    {
        $this->actingAs($this->almacen)
            ->get(route('notas-salida.create', [
                'motivo_salida' => 'ORDEN_OPERACION',
                'orden_operacion_id' => $this->orden->id,
            ]))
            ->assertOk()
            ->assertSee('order-selector-form--note-output', false);

        $css = file_get_contents(public_path('css/hidroil-admin.css'));

        $this->assertStringContainsString(
            'grid-template-columns: minmax(0, .75fr) minmax(0, 1.35fr) minmax(0, .85fr);',
            $css
        );
        $this->assertStringContainsString(
            '.order-selector-form--note-output .order-selector-form__submit',
            $css
        );
    }

    private function payload(array $cambios = []): array
    {
        return array_replace_recursive([
            'motivo_salida' => 'ORDEN_OPERACION',
            'orden_operacion_id' => $this->orden->id,
            'area_trabajo' => 'GENERAL',
            'recibido_por_empleado_id' => $this->receptor->id,
            'fecha_salida' => now()->toDateString(),
            'detalles' => [[
                'inventario_id' => $this->inventario->id,
                'producto_id' => $this->producto->id,
                'repisa_id' => $this->repisa->id,
                'tratamiento' => 'CONSUMO',
                'cantidad' => 1,
            ]],
        ], $cambios);
    }
}
