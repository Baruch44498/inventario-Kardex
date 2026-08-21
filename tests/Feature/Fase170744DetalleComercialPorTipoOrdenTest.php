<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\Producto;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase170744DetalleComercialPorTipoOrdenTest extends TestCase
{
    use RefreshDatabase;

    private User $logistica;
    private User $almacen;
    private Cliente $cliente;
    private Producto $producto;
    private TipoOrden $produccion;
    private TipoOrden $mantenimiento;
    private TipoOrden $servicio;
    private Vehiculo $vehiculo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistica = $this->usuarioConRol('COMERCIAL_LOGISTICA', 'logistica_170744');
        $this->almacen = $this->usuarioConRol('ALMACEN', 'almacen_170744');

        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );

        $this->cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20617074401',
            'ruc' => '20617074401',
            'razon_social' => 'Cliente detalle comercial SAC',
            'estado' => true,
        ]);

        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );

        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'REP-170744',
            'descripcion' => 'Retén hidráulico de prueba',
            'estado' => true,
        ]);

        $this->produccion = $this->tipoOrden('OP', 'Producción');
        $this->mantenimiento = $this->tipoOrden('OM', 'Mantenimiento');
        $this->servicio = $this->tipoOrden('OS', 'Servicio');

        $this->vehiculo = Vehiculo::query()->create([
            'cliente_id' => $this->cliente->id,
            'placa' => 'T744-OM',
            'marca' => 'Prueba',
            'modelo' => 'Mantenimiento',
            'estado' => true,
        ]);
    }

    public function test_formulario_cambia_el_mensaje_segun_op_om_os(): void
    {
        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-cliente.create'))
            ->assertOk()
            ->assertSee('data-commercial-detail-title', false)
            ->assertSee('data-commercial-detail-note-title', false);

        $javascript = file_get_contents(public_path('js/proforma-documentos.js'));

        $this->assertStringContainsString("code === 'OP'", $javascript);
        $this->assertStringContainsString('Composición interna y valorización', $javascript);
        $this->assertStringContainsString("code === 'OM' || code === 'OS'", $javascript);
        $this->assertStringContainsString('Materiales y repuestos cotizados', $javascript);
        $this->assertStringContainsString('Detalle visible para el cliente', $javascript);
    }

    public function test_produccion_oculta_materiales_al_usuario_de_consulta(): void
    {
        $cotizacion = $this->crearCotizacion(
            $this->produccion,
            'Fabricación de cisterna de 10,000 L'
        );

        $this->actingAs($this->almacen)
            ->get(route('cotizaciones-cliente.show', $cotizacion))
            ->assertOk()
            ->assertSee('Resumen que corresponde al cliente')
            ->assertSee('Fabricación de cisterna de 10,000 L')
            ->assertDontSee('Trabajo, materiales y repuestos para el cliente')
            ->assertDontSee('REP-170744');
    }

    public function test_produccion_mantiene_composicion_visible_solo_para_logistica(): void
    {
        $cotizacion = $this->crearCotizacion(
            $this->produccion,
            'Fabricación de cisterna de 10,000 L'
        );

        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-cliente.show', $cotizacion))
            ->assertOk()
            ->assertSee('Composición interna valorizada')
            ->assertSee('REP-170744')
            ->assertSee('Referencia interna');
    }

    public function test_servicio_muestra_materiales_y_repuestos_en_detalle_comercial(): void
    {
        $cotizacion = $this->crearCotizacion(
            $this->servicio,
            'Servicio correctivo de sistema hidráulico'
        );

        $this->actingAs($this->almacen)
            ->get(route('cotizaciones-cliente.show', $cotizacion))
            ->assertOk()
            ->assertSee('Servicio cotizado al cliente')
            ->assertSee('Servicio correctivo de sistema hidráulico')
            ->assertSee('REP-170744')
            ->assertSee('Retén hidráulico de prueba')
            ->assertDontSee('Referencia interna')
            ->assertDontSee('Control interno de valorización');
    }

    public function test_mantenimiento_muestra_repuestos_en_detalle_comercial(): void
    {
        $cotizacion = $this->crearCotizacion(
            $this->mantenimiento,
            'Mantenimiento de sistema hidráulico',
            $this->vehiculo->id
        );

        $this->actingAs($this->almacen)
            ->get(route('cotizaciones-cliente.show', $cotizacion))
            ->assertOk()
            ->assertSee('Trabajo, materiales y repuestos para el cliente')
            ->assertSee('Mantenimiento de sistema hidráulico')
            ->assertSee('REP-170744')
            ->assertDontSee('Referencia interna');
    }

    public function test_logistica_conserva_control_interno_en_om_y_os(): void
    {
        $cotizacion = $this->crearCotizacion(
            $this->servicio,
            'Servicio con repuestos valorizados'
        );

        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-cliente.show', $cotizacion))
            ->assertOk()
            ->assertSee('Servicio cotizado al cliente')
            ->assertSee('Control interno de valorización')
            ->assertSee('Referencia interna');
    }

    private function crearCotizacion(
        TipoOrden $tipo,
        string $descripcion,
        ?int $vehiculoId = null
    ): CotizacionCliente {
        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.store'), [
                'cliente_id' => $this->cliente->id,
                'tipo_orden_id' => $tipo->id,
                'cliente_direccion_id' => null,
                'vehiculo_id' => $vehiculoId,
                'descripcion_trabajo' => $descripcion,
                'fecha_emision' => now()->toDateString(),
                'fecha_validez' => now()->addDays(15)->toDateString(),
                'moneda' => 'PEN',
                'tipo_cambio' => null,
                'condiciones_pago' => 'Contado',
                'condiciones_entrega' => 'Según coordinación',
                'detalles' => [[
                    'producto_id' => $this->producto->id,
                    'cantidad' => 2,
                    'precio_unitario' => 125,
                    'igv_modo' => 'AGREGAR',
                ]],
            ])
            ->assertRedirect();

        return CotizacionCliente::query()->latest('id')->firstOrFail();
    }

    private function tipoOrden(string $codigo, string $nombre): TipoOrden
    {
        return TipoOrden::query()->updateOrCreate(
            ['codigo' => $codigo],
            ['nombre' => $nombre, 'estado' => true]
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
