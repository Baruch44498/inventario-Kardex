<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\CotizacionPresupuesto;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\User;
use App\Services\Ventas\PresupuestoCotizacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1904PresupuestoInternoCotizacionTest extends TestCase
{
    use RefreshDatabase;

    private User $logistica;
    private User $almacen;
    private CotizacionCliente $cotizacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistica = $this->usuarioConRol('COMERCIAL_LOGISTICA', 'logistica_1904');
        $this->almacen = $this->usuarioConRol('ALMACEN', 'almacen_1904');
        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );
        $cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20619040001',
            'ruc' => '20619040001',
            'razon_social' => 'Cliente Presupuesto Interno SAC',
            'estado' => true,
        ]);
        $tipo = TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OP'],
            ['nombre' => 'Producción', 'estado' => true]
        );

        $this->cotizacion = CotizacionCliente::query()->create([
            'origen' => 'DIRECTA_LOGISTICA',
            'cliente_id' => $cliente->id,
            'tipo_orden_id' => $tipo->id,
            'descripcion_trabajo' => 'Fabricación con presupuesto interno',
            'codigo_base' => 'CC-1904',
            'version' => 1,
            'codigo' => 'CC-1904-VRS1',
            'cliente_documento' => $cliente->ruc,
            'cliente_nombre' => $cliente->razon_social,
            'fecha_emision' => now()->toDateString(),
            'moneda' => 'PEN',
            'subtotal' => 10000,
            'impuesto' => 1800,
            'total' => 11800,
            'estado' => 'ABIERTA',
            'cotizado_por' => $this->logistica->id,
        ]);
    }

    public function test_mano_de_obra_pen_incluye_carga_social_y_recalcula_en_servidor(): void
    {
        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.presupuesto.store', $this->cotizacion), [
                ...$this->datosBase(),
                'tipo_costo' => 'MANO_OBRA',
                'descripcion' => 'Personal de fabricación',
                'cantidad' => 10,
                'unidad' => 'DIA',
                'moneda' => 'PEN',
                'tipo_cambio' => 4,
                'costo_unitario' => 100,
                'carga_social_porcentaje' => 30,
                'igv_modo' => 'NO_APLICA',
                'costo_total_soles' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cotizacion_presupuestos', [
            'cotizacion_cliente_id' => $this->cotizacion->id,
            'tipo_costo' => 'MANO_OBRA',
            'carga_social_original' => 300,
            'costo_neto_original' => 1300,
            'costo_total_soles' => 1300,
            'costo_neto_dolares' => 325,
            'estado' => 'VIGENTE',
        ]);
    }

    public function test_servicio_usd_con_igv_genera_los_seis_importes_comparables(): void
    {
        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.presupuesto.store', $this->cotizacion), [
                ...$this->datosBase(),
                'tipo_costo' => 'SERVICIO_TERCERO',
                'descripcion' => 'Ensayo especializado',
                'cantidad' => 2,
                'unidad' => 'SERVICIO',
                'moneda' => 'USD',
                'tipo_cambio' => 3.5,
                'costo_unitario' => 100,
                'igv_modo' => 'AGREGAR',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cotizacion_presupuestos', [
            'costo_neto_original' => 200,
            'igv_original' => 36,
            'costo_total_original' => 236,
            'costo_neto_soles' => 700,
            'igv_soles' => 126,
            'costo_total_soles' => 826,
            'costo_neto_dolares' => 200,
            'igv_dolares' => 36,
            'costo_total_dolares' => 236,
        ]);
    }

    public function test_almacen_no_puede_consultar_ni_modificar_el_presupuesto_interno(): void
    {
        $this->actingAs($this->almacen)
            ->get(route('cotizaciones-cliente.presupuesto.show', $this->cotizacion))
            ->assertForbidden();

        $this->actingAs($this->almacen)
            ->post(
                route('cotizaciones-cliente.presupuesto.store', $this->cotizacion),
                $this->datosBase()
            )
            ->assertForbidden();

        $this->actingAs($this->almacen)
            ->get(route('cotizaciones-cliente.show', $this->cotizacion))
            ->assertOk()
            ->assertDontSee('Presupuesto interno de ejecución');
    }

    public function test_cotizacion_cerrada_no_admite_cambios_presupuestales(): void
    {
        $this->cotizacion->update(['estado' => 'CERRADA']);

        $this->actingAs($this->logistica)
            ->post(
                route('cotizaciones-cliente.presupuesto.store', $this->cotizacion),
                $this->datosBase()
            )
            ->assertSessionHasErrors('tipo_costo');

        $this->assertDatabaseCount('cotizacion_presupuestos', 0);
    }

    public function test_anulacion_conserva_historial_y_excluye_la_partida_del_resumen(): void
    {
        $partida = $this->crearPartida();

        $this->actingAs($this->logistica)
            ->patch(route('cotizacion-presupuestos.anular', $partida), [
                'motivo_anulacion' => 'El proveedor retiró este concepto.',
            ])
            ->assertRedirect();

        $partida = $partida->fresh();
        $this->assertSame('ANULADO', $partida->estado);
        $this->assertSame($this->logistica->id, $partida->anulado_por);

        $resumen = app(PresupuestoCotizacionService::class)
            ->resumen($this->cotizacion->presupuestos()->get());
        $this->assertSame(0, $resumen['lineas_vigentes']);
        $this->assertSame(1, $resumen['lineas_anuladas']);
        $this->assertSame(0.0, $resumen['neto_soles']);
    }

    public function test_nueva_version_copia_solo_partidas_vigentes(): void
    {
        $vigente = $this->crearPartida('Transporte vigente');
        $anulada = $this->crearPartida('Transporte descartado');
        $anulada->update([
            'estado' => 'ANULADO',
            'anulado_por' => $this->logistica->id,
            'anulado_en' => now(),
            'motivo_anulacion' => 'Ya no será necesario.',
        ]);
        $this->cotizacion->update(['estado' => 'CERRADA']);

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.version', $this->cotizacion))
            ->assertRedirect();

        $nueva = CotizacionCliente::query()->where('version', 2)->firstOrFail();
        $this->assertSame(1, $nueva->presupuestos()->count());
        $this->assertDatabaseHas('cotizacion_presupuestos', [
            'cotizacion_cliente_id' => $nueva->id,
            'descripcion' => $vigente->descripcion,
            'estado' => 'VIGENTE',
            'registrado_por' => $this->logistica->id,
        ]);
    }

    private function datosBase(): array
    {
        return [
            'tipo_costo' => 'TRANSPORTE',
            'descripcion' => 'Traslado estimado',
            'cantidad' => 1,
            'unidad' => 'VIAJE',
            'moneda' => 'PEN',
            'tipo_cambio' => 3.8,
            'costo_unitario' => 100,
            'carga_social_porcentaje' => 0,
            'igv_modo' => 'NO_APLICA',
            'igv_porcentaje' => 18,
        ];
    }

    private function crearPartida(string $descripcion = 'Transporte previsto'): CotizacionPresupuesto
    {
        return app(PresupuestoCotizacionService::class)->registrar(
            $this->cotizacion,
            [...$this->datosBase(), 'descripcion' => $descripcion],
            $this->logistica
        );
    }

    private function usuarioConRol(string $codigoRol, string $username): User
    {
        return User::query()->create([
            'role_id' => Role::query()->where('codigo', $codigoRol)->firstOrFail()->id,
            'username' => $username,
            'email' => $username.'@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
    }
}
