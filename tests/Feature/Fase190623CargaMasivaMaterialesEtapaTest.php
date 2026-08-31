<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\CotizacionComponente;
use App\Models\Producto;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase190623CargaMasivaMaterialesEtapaTest extends TestCase
{
    use RefreshDatabase;

    private User $logistica;
    private CotizacionCliente $cotizacion;
    private CotizacionComponente $componente;
    private Producto $plancha;
    private Producto $tubo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistica = User::query()->create([
            'role_id' => Role::query()->where('codigo', 'COMERCIAL_LOGISTICA')->firstOrFail()->id,
            'username' => 'logistica_190622',
            'email' => 'logistica_190622@example.com',
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
            'numero_documento' => '20619062201',
            'ruc' => '20619062201',
            'razon_social' => 'Cliente Carga Masiva SAC',
            'estado' => true,
        ]);
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $metro = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'M'],
            ['nombre' => 'Metro', 'estado' => true]
        );
        $this->plancha = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'PL-190622',
            'descripcion' => 'Plancha de acero',
            'permite_fraccionamiento' => false,
            'estado' => true,
        ]);
        $this->tubo = Producto::query()->create([
            'unidad_medida_id' => $metro->id,
            'codigo' => 'TB-190622',
            'descripcion' => 'Tubo hidráulico',
            'permite_fraccionamiento' => true,
            'estado' => true,
        ]);
        $tipoOrden = TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OP'],
            ['nombre' => 'Producción', 'estado' => true]
        );
        $this->cotizacion = CotizacionCliente::query()->create([
            'origen' => 'DIRECTA_LOGISTICA',
            'cliente_id' => $cliente->id,
            'descripcion_trabajo' => 'Fabricación de tanque',
            'codigo_base' => 'CC-190622',
            'version' => 1,
            'codigo' => 'CC-190622-VRS1',
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
            'descripcion_componente' => 'Tanque de almacenamiento',
            'tipo_cambio_comparacion' => 3.8,
            'orden_secuencia' => 1,
        ]);
    }

    public function test_guarda_varios_materiales_en_una_sola_etapa_y_transaccion(): void
    {
        $this->cotizacion->update(['costeo_sincronizado_en' => now()]);

        $this->actingAs($this->logistica)
            ->post(
                route('cotizaciones-cliente.presupuesto.materiales.store', $this->cotizacion),
                $this->datos([
                    ['producto_id' => $this->plancha->id, 'cantidad' => 4, 'costo_unitario' => 120],
                    ['producto_id' => $this->tubo->id, 'cantidad' => 8.5, 'costo_unitario' => 25],
                ])
            )
            ->assertRedirect(route('cotizaciones-cliente.presupuesto.show', [
                'cotizacionCliente' => $this->cotizacion,
                'componente_id' => $this->componente,
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $partidas = $this->cotizacion->presupuestos()->orderBy('id')->get();

        $this->assertCount(2, $partidas);
        $this->assertSame(['ESTRUCTURA DEL TANQUE'], $partidas->pluck('grupo_costo')->unique()->values()->all());
        $this->assertSame(['UND', 'M'], $partidas->pluck('unidad')->all());
        $this->assertSame(['Plancha de acero', 'Tubo hidráulico'], $partidas->pluck('descripcion')->all());
        $this->assertNull($this->cotizacion->fresh()->costeo_sincronizado_en);
    }

    public function test_rechaza_producto_repetido_y_no_guarda_parcialmente(): void
    {
        $this->actingAs($this->logistica)
            ->post(
                route('cotizaciones-cliente.presupuesto.materiales.store', $this->cotizacion),
                $this->datos([
                    ['producto_id' => $this->plancha->id, 'cantidad' => 2, 'costo_unitario' => 120],
                    ['producto_id' => $this->plancha->id, 'cantidad' => 3, 'costo_unitario' => 120],
                ])
            )
            ->assertSessionHasErrors('materiales.1.producto_id');

        $this->assertDatabaseCount('cotizacion_presupuestos', 0);
    }

    public function test_la_pantalla_separa_carga_masiva_de_otros_costos(): void
    {
        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-cliente.presupuesto.show', [
                'cotizacionCliente' => $this->cotizacion,
                'componente_id' => $this->componente,
            ]))
            ->assertOk()
            ->assertSee('Agregar varios materiales juntos')
            ->assertSee('Guardar todos los materiales')
            ->assertSee('Agregar mano de obra u otro costo')
            ->assertSee(route('cotizaciones-cliente.presupuesto.materiales.store', $this->cotizacion), false);
    }

    public function test_la_cantidad_entera_usa_minimo_y_salto_desde_uno(): void
    {
        $vista = file_get_contents(resource_path(
            'views/cotizaciones_cliente/_material_etapa_row.blade.php'
        ));
        $javascript = file_get_contents(public_path(
            'js/materiales-etapa-cotizacion.js'
        ));

        $this->assertStringContainsString(
            'min="{{ $productoFila && ! $productoFila->permite_fraccionamiento',
            $vista
        );
        $this->assertStringContainsString(
            "quantity.min = allowsFraction ? '0.001' : '1'",
            $javascript
        );
        $this->assertStringContainsString(
            "quantity.step = allowsFraction ? '0.001' : '1'",
            $javascript
        );
    }

    private function datos(array $materiales): array
    {
        return [
            'componente_id' => $this->componente->id,
            'grupo_costo' => 'ESTRUCTURA DEL TANQUE',
            'moneda' => 'PEN',
            'tipo_cambio' => 3.8,
            'margen_porcentaje' => 20,
            'igv_modo' => 'NO_APLICA',
            'igv_porcentaje' => 18,
            'igv_venta_porcentaje' => 18,
            'observacion' => 'Carga conjunta de la etapa',
            'materiales' => $materiales,
        ];
    }
}
