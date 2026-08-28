<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\CotizacionComponente;
use App\Models\MaterialRequeridoOrden;
use App\Models\Producto;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Ventas\PresupuestoCotizacionService;
use App\Services\Ventas\SincronizarHojaCostosCotizacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase19060B1SincronizaCosteoCotizacionTest extends TestCase
{
    use RefreshDatabase;

    private User $logistica;
    private Cliente $cliente;
    private Producto $producto;
    private Vehiculo $vehiculo;
    private CotizacionCliente $cotizacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistica = User::query()->create([
            'role_id' => Role::query()->where('codigo', 'COMERCIAL_LOGISTICA')->firstOrFail()->id,
            'username' => 'logistica_19060b1',
            'email' => 'logistica_19060b1@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );
        $this->cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20619060101',
            'ruc' => '20619060101',
            'razon_social' => 'Cliente Sincronización Costeo SAC',
            'estado' => true,
        ]);
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'M'],
            ['nombre' => 'Metro', 'abreviatura' => 'm', 'estado' => true]
        );
        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'MANG-19060B1',
            'descripcion' => 'Manguera fraccionable',
            'permite_fraccionamiento' => true,
            'estado' => true,
        ]);
        $this->vehiculo = Vehiculo::query()->create([
            'cliente_id' => $this->cliente->id,
            'placa' => 'B1-1906',
            'marca' => 'Volvo',
            'modelo' => 'Prueba',
            'estado' => true,
        ]);
        $this->cotizacion = CotizacionCliente::query()->create([
            'origen' => 'DIRECTA_LOGISTICA',
            'cliente_id' => $this->cliente->id,
            'descripcion_trabajo' => 'Cotización mixta calculada desde costos',
            'codigo_base' => 'CC-19060B1',
            'version' => 1,
            'codigo' => 'CC-19060B1-VRS1',
            'cliente_documento' => $this->cliente->ruc,
            'cliente_nombre' => $this->cliente->razon_social,
            'fecha_emision' => now()->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => 3.8,
            'subtotal' => 0,
            'impuesto' => 0,
            'total' => 0,
            'estado' => 'ABIERTA',
            'cotizado_por' => $this->logistica->id,
        ]);
    }

    public function test_op_om_y_os_actualizan_el_valor_comercial_sin_publicar_el_costeo_interno(): void
    {
        $op = $this->componente('OP', 1);
        $om = $this->componente('OM', 2);
        $os = $this->componente('OS', 3);

        $this->partida($op, 'MATERIAL', 2, 50, 20, $this->producto);
        $this->partida($om, 'MATERIAL', 2, 50, 20, $this->producto);
        $this->partida($om, 'MANO_OBRA', 1, 100, 20);
        $this->partida($os, 'MATERIAL', 1, 100, 20, $this->producto);
        $this->partida($os, 'SERVICIO_TERCERO', 1, 100, 20);

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.presupuesto.sincronizar', $this->cotizacion))
            ->assertRedirect(route('cotizaciones-cliente.show', $this->cotizacion))
            ->assertSessionHasNoErrors();

        $this->cotizacion->refresh();
        $this->assertSame(600.0, (float) $this->cotizacion->subtotal);
        $this->assertSame(108.0, (float) $this->cotizacion->impuesto);
        $this->assertSame(708.0, (float) $this->cotizacion->total);
        $this->assertNotNull($this->cotizacion->costeo_sincronizado_en);
        $this->assertSame(4, $this->cotizacion->detalles()->count());

        $this->assertDatabaseHas('cotizacion_cliente_detalles', [
            'componente_id' => $op->id,
            'producto_id' => null,
            'tipo_linea' => 'PRODUCCION',
            'origen_costeo' => true,
        ]);
        $this->assertDatabaseHas('cotizacion_cliente_detalles', [
            'componente_id' => $os->id,
            'producto_id' => null,
            'tipo_linea' => 'SERVICIO',
            'origen_costeo' => true,
        ]);
        $this->assertDatabaseHas('cotizacion_cliente_detalles', [
            'componente_id' => $om->id,
            'producto_id' => $this->producto->id,
            'tipo_linea' => 'PRODUCTO',
            'cantidad' => 2,
            'origen_costeo' => true,
        ]);
        $this->assertDatabaseHas('cotizacion_cliente_detalles', [
            'componente_id' => $om->id,
            'producto_id' => null,
            'tipo_linea' => 'SERVICIO',
            'origen_costeo' => true,
        ]);

        $this->actingAs($this->logistica)
            ->patch(route('cotizaciones-cliente.cerrar', $this->cotizacion))
            ->assertRedirect();
        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.convertir-orden', $this->cotizacion), [
                'fecha_apertura' => now()->toDateString(),
            ])
            ->assertRedirect(route('cotizaciones-cliente.show', $this->cotizacion));

        foreach ([$op->id => 2.0, $om->id => 2.0, $os->id => 1.0] as $componenteId => $cantidad) {
            $ordenId = CotizacionComponente::query()->findOrFail($componenteId)->orden_operacion_id;
            $material = MaterialRequeridoOrden::query()
                ->where('orden_operacion_id', $ordenId)
                ->where('producto_id', $this->producto->id)
                ->firstOrFail();
            $this->assertSame($cantidad, (float) $material->cantidad_requerida);
        }
    }

    public function test_un_cambio_de_costeo_invalida_la_sincronizacion_y_bloquea_el_cierre(): void
    {
        $op = $this->componente('OP', 1);
        $this->partida($op, 'MATERIAL', 1, 100, 20, $this->producto);

        app(SincronizarHojaCostosCotizacionService::class)->sincronizar($this->cotizacion);
        $this->assertNotNull($this->cotizacion->fresh()->costeo_sincronizado_en);

        $this->partida($op, 'MANO_OBRA', 1, 50, 20);
        $this->assertNull($this->cotizacion->fresh()->costeo_sincronizado_en);

        $this->actingAs($this->logistica)
            ->patch(route('cotizaciones-cliente.cerrar', $this->cotizacion))
            ->assertRedirect()
            ->assertSessionHas('error', function (string $mensaje): bool {
                return str_contains($mensaje, 'Sincronízala con la cotización');
            });

        $this->assertSame('ABIERTA', $this->cotizacion->fresh()->estado);
    }

    public function test_una_cotizacion_en_usd_usa_la_conversion_de_la_hoja_de_costos(): void
    {
        $this->cotizacion->update(['moneda' => 'USD']);
        $op = $this->componente('OP', 1);
        $this->partida($op, 'MATERIAL', 1, 380, 10, $this->producto);

        app(SincronizarHojaCostosCotizacionService::class)->sincronizar($this->cotizacion);

        $this->cotizacion->refresh();
        $this->assertSame(110.0, (float) $this->cotizacion->subtotal);
        $this->assertSame(19.8, (float) $this->cotizacion->impuesto);
        $this->assertSame(129.8, (float) $this->cotizacion->total);
        $this->assertDatabaseHas('cotizacion_cliente_detalles', [
            'cotizacion_cliente_id' => $this->cotizacion->id,
            'tipo_linea' => 'PRODUCCION',
            'precio_unitario' => 110,
            'total' => 129.8,
        ]);
    }

    public function test_no_sincroniza_si_un_componente_aun_no_tiene_costeo(): void
    {
        $op = $this->componente('OP', 1);
        $this->componente('OS', 2);
        $this->partida($op, 'MATERIAL', 1, 100, 20, $this->producto);

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.presupuesto.sincronizar', $this->cotizacion))
            ->assertRedirect()
            ->assertSessionHasErrors('presupuesto');

        $this->assertSame(0, $this->cotizacion->detalles()->count());
    }

    private function componente(string $codigo, int $secuencia): CotizacionComponente
    {
        $tipo = TipoOrden::query()->updateOrCreate(
            ['codigo' => $codigo],
            ['nombre' => $codigo, 'estado' => true]
        );

        return $this->cotizacion->componentes()->create([
            'tipo_orden_id' => $tipo->id,
            'descripcion_componente' => "Trabajo {$codigo}",
            'vehiculo_id' => $codigo === 'OM' ? $this->vehiculo->id : null,
            'tipo_cambio_comparacion' => 3.8,
            'orden_secuencia' => $secuencia,
        ]);
    }

    private function partida(
        CotizacionComponente $componente,
        string $tipo,
        float $cantidad,
        float $costoUnitario,
        float $margen,
        ?Producto $producto = null
    ): void {
        app(PresupuestoCotizacionService::class)->registrar(
            $this->cotizacion,
            [
                'componente_id' => $componente->id,
                'producto_id' => $producto?->id,
                'tipo_costo' => $tipo,
                'grupo_costo' => null,
                'descripcion' => $producto?->descripcion ?: "Costo {$tipo}",
                'cantidad' => $cantidad,
                'unidad' => match ($tipo) {
                    'MATERIAL' => 'METRO',
                    'MANO_OBRA' => 'DIA',
                    'SERVICIO_TERCERO' => 'SERVICIO',
                    default => 'GLOBAL',
                },
                'moneda' => 'PEN',
                'tipo_cambio' => 3.8,
                'costo_unitario' => $costoUnitario,
                'margen_porcentaje' => $margen,
                'carga_social_porcentaje' => 0,
                'igv_modo' => 'NO_APLICA',
                'igv_porcentaje' => 18,
                'igv_venta_porcentaje' => 18,
                'observacion' => null,
            ],
            $this->logistica
        );
    }
}
