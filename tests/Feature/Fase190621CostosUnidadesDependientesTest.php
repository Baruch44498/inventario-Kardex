<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\CotizacionComponente;
use App\Models\CotizacionPresupuesto;
use App\Models\Producto;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase190621CostosUnidadesDependientesTest extends TestCase
{
    use RefreshDatabase;

    private User $logistica;
    private CotizacionCliente $cotizacion;
    private CotizacionComponente $componente;
    private Producto $manguera;
    private Producto $casco;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistica = User::query()->create([
            'role_id' => Role::query()
                ->where('codigo', 'COMERCIAL_LOGISTICA')
                ->firstOrFail()
                ->id,
            'username' => 'logistica_190621',
            'email' => 'logistica_190621@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );
        $cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20619062101',
            'ruc' => '20619062101',
            'razon_social' => 'Cliente Unidades Dependientes SAC',
            'estado' => true,
        ]);
        $metro = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'M'],
            ['nombre' => 'Metro', 'estado' => true]
        );
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $this->manguera = Producto::query()->create([
            'unidad_medida_id' => $metro->id,
            'codigo' => 'MANG-190621',
            'descripcion' => 'Manguera hidráulica',
            'permite_fraccionamiento' => true,
            'estado' => true,
        ]);
        $this->casco = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'EPP-CASCO-190621',
            'descripcion' => 'Casco de seguridad',
            'permite_fraccionamiento' => false,
            'estado' => true,
        ]);
        $tipoOrden = TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OP'],
            ['nombre' => 'Producción', 'estado' => true]
        );
        $this->cotizacion = CotizacionCliente::query()->create([
            'origen' => 'DIRECTA_LOGISTICA',
            'cliente_id' => $cliente->id,
            'descripcion_trabajo' => 'Fabricación de cisterna',
            'codigo_base' => 'CC-190621',
            'version' => 1,
            'codigo' => 'CC-190621-VRS1',
            'cliente_documento' => $cliente->ruc,
            'cliente_nombre' => $cliente->razon_social,
            'fecha_emision' => now()->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => 3.8,
            'subtotal' => 0,
            'impuesto' => 0,
            'total' => 0,
            'estado' => 'ABIERTA',
            'cotizado_por' => $this->logistica->id,
        ]);
        $this->componente = $this->cotizacion->componentes()->create([
            'tipo_orden_id' => $tipoOrden->id,
            'descripcion_componente' => 'Cisterna de combustible',
            'tipo_cambio_comparacion' => 3.8,
            'orden_secuencia' => 1,
        ]);
    }

    public function test_epp_deja_de_ser_tipo_y_material_exige_producto_de_almacen(): void
    {
        $this->assertArrayNotHasKey('EPP_CONSUMIBLES', CotizacionPresupuesto::TIPOS);
        $this->assertArrayNotHasKey('HERRAMIENTA_EQUIPO', CotizacionPresupuesto::TIPOS);

        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-cliente.presupuesto.show', $this->cotizacion))
            ->assertOk()
            ->assertDontSee('value="EPP_CONSUMIBLES"', false)
            ->assertSee('También los EPP y consumibles se eligen aquí');

        $this->actingAs($this->logistica)
            ->post(
                route('cotizaciones-cliente.presupuesto.store', $this->cotizacion),
                $this->datosBase([
                    'tipo_costo' => 'MATERIAL',
                    'producto_id' => null,
                    'unidad' => 'HORA',
                ])
            )
            ->assertSessionHasErrors('producto_id');

        $this->assertDatabaseCount('cotizacion_presupuestos', 0);
    }

    public function test_material_ignora_unidad_manipulada_y_usa_la_unidad_del_producto(): void
    {
        $this->actingAs($this->logistica)
            ->post(
                route('cotizaciones-cliente.presupuesto.store', $this->cotizacion),
                $this->datosBase([
                    'tipo_costo' => 'MATERIAL',
                    'producto_id' => $this->manguera->id,
                    'grupo_costo' => 'TUBERÍAS',
                    'descripcion' => 'Manguera hidráulica',
                    'cantidad' => 3.2,
                    'unidad' => 'HORA',
                ])
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cotizacion_presupuestos', [
            'producto_id' => $this->manguera->id,
            'tipo_costo' => 'MATERIAL',
            'grupo_costo' => 'TUBERÍAS',
            'cantidad' => 3.2,
            'unidad' => 'M',
        ]);
    }

    public function test_producto_entero_rechaza_fracciones_y_servicio_alquilado_admite_horas(): void
    {
        $this->actingAs($this->logistica)
            ->post(
                route('cotizaciones-cliente.presupuesto.store', $this->cotizacion),
                $this->datosBase([
                    'tipo_costo' => 'MATERIAL',
                    'producto_id' => $this->casco->id,
                    'grupo_costo' => 'EPP Y CONSUMIBLES',
                    'descripcion' => 'Casco de seguridad',
                    'cantidad' => 1.5,
                    'unidad' => 'UND',
                ])
            )
            ->assertSessionHasErrors('cantidad');

        $this->actingAs($this->logistica)
            ->post(
                route('cotizaciones-cliente.presupuesto.store', $this->cotizacion),
                $this->datosBase([
                    'tipo_costo' => 'SERVICIO_TERCERO',
                    'producto_id' => null,
                    'descripcion' => 'Arenado de tanque',
                    'unidad' => 'HORA',
                ])
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cotizacion_presupuestos', [
            'tipo_costo' => 'SERVICIO_TERCERO',
            'descripcion' => 'Arenado de tanque',
            'unidad' => 'HORA',
        ]);
    }

    public function test_catalogo_entrega_unidad_y_control_fraccionario_a_la_interfaz(): void
    {
        $this->actingAs($this->logistica)
            ->getJson(route('catalogos.productos.buscar', ['q' => 'MANG-190621']))
            ->assertOk()
            ->assertJsonPath('items.0.id', $this->manguera->id)
            ->assertJsonPath('items.0.unidad_codigo', 'M')
            ->assertJsonPath('items.0.unidad_nombre', 'Metro')
            ->assertJsonPath('items.0.permite_fraccionamiento', true);

        $javascript = file_get_contents(public_path('js/presupuesto-cotizacion.js'));
        $this->assertStringContainsString('unitSelect.disabled = true', $javascript);
        $this->assertStringContainsString('data-compatible-types', file_get_contents(
            resource_path('views/cotizaciones_cliente/_presupuesto_form.blade.php')
        ));
    }

    private function datosBase(array $cambios = []): array
    {
        return array_replace([
            'componente_id' => $this->componente->id,
            'tipo_costo' => 'OTRO',
            'producto_id' => null,
            'grupo_costo' => null,
            'descripcion' => 'Costo de prueba',
            'cantidad' => 1,
            'unidad' => 'GLOBAL',
            'moneda' => 'PEN',
            'tipo_cambio' => 3.8,
            'costo_unitario' => 100,
            'margen_porcentaje' => 20,
            'carga_social_porcentaje' => 0,
            'igv_modo' => 'NO_APLICA',
            'igv_porcentaje' => 18,
            'igv_venta_porcentaje' => 18,
            'observacion' => null,
        ], $cambios);
    }
}
