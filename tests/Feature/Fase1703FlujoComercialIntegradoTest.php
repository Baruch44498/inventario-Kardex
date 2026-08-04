<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\OrdenOperacion;
use App\Models\Producto;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1703FlujoComercialIntegradoTest extends TestCase
{
    use RefreshDatabase;

    private User $logistica;

    private Cliente $cliente;

    private Vehiculo $vehiculo;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistica = $this->usuarioConRol(
            'COMERCIAL_LOGISTICA',
            'logistica_1703'
        );

        foreach ([
            'OM' => 'Orden de mantenimiento',
            'OV' => 'Orden de venta',
            'OS' => 'Orden de servicio',
            'OP' => 'Orden de producción',
        ] as $codigo => $nombre) {
            TipoOrden::query()->updateOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'estado' => true]
            );
        }

        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );
        $this->cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20617030001',
            'ruc' => '20617030001',
            'razon_social' => 'Cliente Flujo Integrado SAC',
            'estado' => true,
        ]);
        $this->vehiculo = Vehiculo::query()->create([
            'cliente_id' => $this->cliente->id,
            'placa' => 'HID-170',
            'marca' => 'Unidad',
            'modelo' => 'Prueba',
            'estado' => true,
        ]);
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'PRD-1703',
            'descripcion' => 'Producto del flujo comercial integrado',
            'estado' => true,
        ]);
    }

    public function test_aprobar_cotizacion_om_genera_orden_con_todos_los_datos_heredados(): void
    {
        $tipo = TipoOrden::query()->where('codigo', 'OM')->firstOrFail();
        $descripcion = 'Mantenimiento preventivo cotizado para la unidad del cliente';

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.store'), $this->datosCotizacion([
                'tipo_orden_id' => $tipo->id,
                'vehiculo_id' => $this->vehiculo->id,
                'descripcion_trabajo' => $descripcion,
            ]))
            ->assertRedirect();

        $cotizacion = CotizacionCliente::query()->firstOrFail();
        $this->assertSame('ABIERTA', $cotizacion->estado);

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.convertir-orden', $cotizacion), [
                'fecha_apertura' => now()->toDateString(),
            ])
            ->assertRedirect();

        $orden = OrdenOperacion::query()->firstOrFail();

        $this->assertSame($tipo->id, $orden->tipo_orden_id);
        $this->assertSame($this->cliente->id, $orden->cliente_id);
        $this->assertSame($this->vehiculo->id, $orden->vehiculo_id);
        $this->assertSame($descripcion, $orden->descripcion);
        $this->assertSame('CONVERTIDA_EN_ORDEN', $cotizacion->fresh()->estado);
        $this->assertSame($orden->id, $cotizacion->fresh()->orden_operacion_id);
        $this->assertDatabaseCount('movimientos_inventario', 0);

        $this->actingAs($this->logistica)
            ->get(route('ordenes-operacion.show', $orden))
            ->assertOk()
            ->assertSee($descripcion)
            ->assertSee($this->vehiculo->placa)
            ->assertSee($this->producto->codigo);
    }

    public function test_produccion_no_admite_vehiculo_y_conserva_los_productos_cotizados(): void
    {
        $tipo = TipoOrden::query()->where('codigo', 'OP')->firstOrFail();

        $this->actingAs($this->logistica)
            ->from(route('cotizaciones-cliente.create'))
            ->post(route('cotizaciones-cliente.store'), $this->datosCotizacion([
                'tipo_orden_id' => $tipo->id,
                'vehiculo_id' => $this->vehiculo->id,
                'descripcion_trabajo' => 'Fabricación de una unidad nueva',
            ]))
            ->assertRedirect(route('cotizaciones-cliente.create'))
            ->assertSessionHasErrors('vehiculo_id');

        $this->assertDatabaseCount('cotizaciones_cliente', 0);

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.store'), $this->datosCotizacion([
                'tipo_orden_id' => $tipo->id,
                'vehiculo_id' => null,
                'descripcion_trabajo' => 'Fabricación de una unidad nueva',
            ]))
            ->assertRedirect();

        $cotizacion = CotizacionCliente::query()->firstOrFail();

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.convertir-orden', $cotizacion), [
                'fecha_apertura' => now()->toDateString(),
            ])
            ->assertRedirect();

        $orden = OrdenOperacion::query()->firstOrFail();
        $this->assertNull($orden->vehiculo_id);
        $this->assertSame(1, $cotizacion->detalles()->count());

        $this->actingAs($this->logistica)
            ->get(route('ordenes-operacion.index'))
            ->assertOk()
            ->assertSee('<th>Materiales</th>', false)
            ->assertSee('1 producto planificado')
            ->assertDontSee('<th>Requisiciones</th>', false)
            ->assertDontSee('<th>Salidas</th>', false);
    }

    private function datosCotizacion(array $cambios = []): array
    {
        return array_replace([
            'cliente_id' => $this->cliente->id,
            'tipo_orden_id' => null,
            'cliente_direccion_id' => null,
            'vehiculo_id' => null,
            'descripcion_trabajo' => null,
            'fecha_emision' => now()->toDateString(),
            'fecha_validez' => now()->addDays(15)->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => null,
            'condiciones_pago' => 'Contado',
            'condiciones_entrega' => 'Recojo en HIDROIL',
            'observacion' => null,
            'detalles' => [[
                'producto_id' => $this->producto->id,
                'cantidad' => 2,
                'precio_unitario' => 125,
                'igv_modo' => 'AGREGAR',
                'observacion' => null,
            ]],
        ], $cambios);
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
