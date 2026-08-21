<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\CotizacionComponente;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\User;
use App\Services\Ventas\PresupuestoCotizacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase19060BHojaCostosUniversalTest extends TestCase
{
    use RefreshDatabase;

    private User $logistica;
    private CotizacionCliente $cotizacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistica = User::query()->create([
            'role_id' => Role::query()->where('codigo', 'COMERCIAL_LOGISTICA')->firstOrFail()->id,
            'username' => 'logistica_19060b',
            'email' => 'logistica_19060b@example.com',
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
            'numero_documento' => '20619060002',
            'ruc' => '20619060002',
            'razon_social' => 'Cliente Hoja Universal SAC',
            'estado' => true,
        ]);
        $this->cotizacion = CotizacionCliente::query()->create([
            'origen' => 'DIRECTA_LOGISTICA',
            'cliente_id' => $cliente->id,
            'descripcion_trabajo' => 'Cotización con producción, mantenimiento y servicio',
            'codigo_base' => 'CC-19060B',
            'version' => 1,
            'codigo' => 'CC-19060B-VRS1',
            'cliente_documento' => $cliente->ruc,
            'cliente_nombre' => $cliente->razon_social,
            'fecha_emision' => now()->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => 3.8,
            'subtotal' => 1000,
            'impuesto' => 180,
            'total' => 1180,
            'estado' => 'ABIERTA',
            'cotizado_por' => $this->logistica->id,
        ]);
    }

    public function test_calcula_costo_venta_utilidad_e_igv_en_pen_y_usd_desde_el_servidor(): void
    {
        $componente = $this->componente('OP', 1);

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.presupuesto.store', $this->cotizacion), [
                ...$this->datosBase($componente),
                'tipo_costo' => 'MATERIAL',
                'grupo_costo' => 'Estructura del tanque',
                'descripcion' => 'Plancha de acero',
                'cantidad' => 2,
                'unidad' => 'UNIDAD',
                'costo_unitario' => 50,
                'margen_porcentaje' => 20,
                'igv_modo' => 'NO_APLICA',
                'igv_venta_porcentaje' => 18,
                'precio_venta_total_soles' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cotizacion_presupuestos', [
            'componente_id' => $componente->id,
            'grupo_costo' => 'Estructura del tanque',
            'costo_neto_soles' => 100,
            'precio_venta_neto_soles' => 120,
            'igv_venta_soles' => 21.6,
            'precio_venta_total_soles' => 141.6,
            'utilidad_estimada_soles' => 20,
            'igv_por_pagar_soles' => 21.6,
            'utilidad_estimada_dolares' => 5.2632,
        ]);
    }

    public function test_agrupa_la_misma_hoja_para_componentes_op_om_y_os(): void
    {
        foreach (['OP', 'OM', 'OS'] as $secuencia => $tipo) {
            $componente = $this->componente($tipo, $secuencia + 1);
            app(PresupuestoCotizacionService::class)->registrar(
                $this->cotizacion,
                [
                    ...$this->datosBase($componente),
                    'tipo_costo' => $tipo === 'OP' ? 'MATERIAL' : 'MANO_OBRA',
                    'descripcion' => "Costo {$tipo}",
                    'unidad' => $tipo === 'OP' ? 'UNIDAD' : 'DIA',
                    'costo_unitario' => 100 * ($secuencia + 1),
                    'margen_porcentaje' => 10,
                ],
                $this->logistica
            );
        }

        $partidas = $this->cotizacion->presupuestos()
            ->with('componente.tipoOrden')
            ->get();
        $resumen = app(PresupuestoCotizacionService::class)->resumen($partidas);

        $this->assertSame(3, $resumen['por_componente']->count());
        $this->assertSame(['OP', 'OM', 'OS'], $resumen['por_componente']->pluck('tipo_orden')->all());
        $this->assertSame(600.0, $resumen['neto_soles']);
        $this->assertSame(60.0, $resumen['utilidad_soles']);

        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-cliente.presupuesto.show', $this->cotizacion))
            ->assertOk()
            ->assertSee('Hoja universal de costos')
            ->assertSee('Resultado estimado por componente')
            ->assertSee('OP 1')
            ->assertSee('OM 2')
            ->assertSee('OS 3');
    }

    private function componente(string $codigo, int $secuencia): CotizacionComponente
    {
        $tipo = TipoOrden::query()->updateOrCreate(
            ['codigo' => $codigo],
            ['nombre' => $codigo, 'estado' => true]
        );

        return $this->cotizacion->componentes()->create([
            'tipo_orden_id' => $tipo->id,
            'descripcion_componente' => "Componente {$codigo}",
            'tipo_cambio_comparacion' => 3.8,
            'orden_secuencia' => $secuencia,
        ]);
    }

    private function datosBase(CotizacionComponente $componente): array
    {
        return [
            'componente_id' => $componente->id,
            'tipo_costo' => 'OTRO',
            'grupo_costo' => null,
            'descripcion' => 'Costo estimado',
            'cantidad' => 1,
            'unidad' => 'GLOBAL',
            'moneda' => 'PEN',
            'tipo_cambio' => 3.8,
            'costo_unitario' => 100,
            'margen_porcentaje' => 0,
            'carga_social_porcentaje' => 0,
            'igv_modo' => 'NO_APLICA',
            'igv_porcentaje' => 18,
            'igv_venta_porcentaje' => 18,
        ];
    }
}
