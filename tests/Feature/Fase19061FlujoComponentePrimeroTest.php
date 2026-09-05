<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase19061FlujoComponentePrimeroTest extends TestCase
{
    use RefreshDatabase;

    private User $logistica;
    private Cliente $cliente;
    private TipoOrden $mantenimiento;
    private TipoOrden $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistica = User::query()->create([
            'role_id' => Role::query()
                ->where('codigo', 'COMERCIAL_LOGISTICA')
                ->firstOrFail()
                ->id,
            'username' => 'logistica_19061',
            'email' => 'logistica_19061@example.com',
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
            'numero_documento' => '20619061001',
            'ruc' => '20619061001',
            'razon_social' => 'Cliente componente primero SAC',
            'estado' => true,
        ]);
        $this->mantenimiento = $this->tipoOrden('OM', 'Mantenimiento');
        $this->servicio = $this->tipoOrden('OS', 'Servicio');
    }

    public function test_formulario_crea_primero_el_trabajo_y_no_pide_productos(): void
    {
        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-cliente.create'))
            ->assertOk()
            ->assertSee('Paso 1 de 3 · Define la orden principal antes de cargar costos')
            ->assertSee('OM, OS y OP comparten el mismo formato')
            ->assertDontSee('name="detalles[', false);
    }

    public function test_crea_cotizacion_en_cero_y_continua_a_la_hoja_de_costos(): void
    {
        $respuesta = $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.store'), $this->estructura())
            ->assertSessionHasNoErrors();

        $cotizacion = CotizacionCliente::query()
            ->with('componentes')
            ->firstOrFail();

        $this->assertSame(0, $cotizacion->detalles()->count());
        $this->assertSame(0.0, (float) $cotizacion->total);
        $this->assertCount(1, $cotizacion->componentes);
        $this->assertSame($this->servicio->id, $cotizacion->componentes->first()->tipo_orden_id);
        $this->assertSame(
            'Servicio de arenado y pintura de cisterna',
            $cotizacion->componentes->first()->descripcion_componente
        );
        $this->assertSame(3.8, (float) $cotizacion->componentes->first()->tipo_cambio_comparacion);
        $respuesta->assertRedirect(
            route('cotizaciones-cliente.presupuesto.show', $cotizacion)
        );

        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-cliente.componentes.show', $cotizacion))
            ->assertOk()
            ->assertSee('Transición a planificación por áreas')
            ->assertSee('Contexto de la orden principal')
            ->assertDontSee('Agregar componente');

        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-cliente.presupuesto.show', [
                'cotizacionCliente' => $cotizacion,
                'componente_id' => $cotizacion->componentes->first()->id,
            ]))
            ->assertOk()
            ->assertSee('value="3.800000"', false);
    }

    public function test_mantenimiento_sigue_exigiendo_el_vehiculo_desde_el_primer_componente(): void
    {
        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.store'), $this->estructura([
                'tipo_orden_id' => $this->mantenimiento->id,
            ]))
            ->assertSessionHasErrors('vehiculo_id');

        $this->assertDatabaseCount('cotizaciones_cliente', 0);
        $this->assertDatabaseCount('cotizacion_componentes', 0);
    }

    private function estructura(array $cambios = []): array
    {
        return array_replace([
            'cliente_id' => $this->cliente->id,
            'tipo_orden_id' => $this->servicio->id,
            'cliente_direccion_id' => null,
            'vehiculo_id' => null,
            'descripcion_trabajo' => 'Servicio de arenado y pintura de cisterna',
            'fecha_emision' => now()->toDateString(),
            'fecha_validez' => now()->addDays(15)->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => null,
            'tipo_cambio_comparacion' => 3.8,
            'condiciones_pago' => '50 % de adelanto',
            'condiciones_entrega' => 'Según coordinación',
            'observacion' => null,
        ], $cambios);
    }

    private function tipoOrden(string $codigo, string $nombre): TipoOrden
    {
        return TipoOrden::query()->updateOrCreate(
            ['codigo' => $codigo],
            ['nombre' => $nombre, 'estado' => true]
        );
    }
}
