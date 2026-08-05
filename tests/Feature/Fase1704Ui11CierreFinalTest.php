<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ClienteDireccion;
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

class Fase1704Ui11CierreFinalTest extends TestCase
{
    use RefreshDatabase;

    private User $logistica;

    private User $almacen;

    private User $planta;

    private Cliente $cliente;

    private Vehiculo $vehiculo;

    private Producto $producto;

    private TipoOrden $tipoProduccion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistica = $this->usuarioConRol('COMERCIAL_LOGISTICA', 'logistica_ui_11');
        $this->almacen = $this->usuarioConRol('ALMACEN', 'almacen_ui_11');
        $this->planta = $this->usuarioConRol('JEFE_PLANTA', 'planta_ui_11');

        foreach (
            [
                'OM' => 'Orden de mantenimiento',
                'OV' => 'Orden de venta',
                'OS' => 'Orden de servicio',
                'OP' => 'Orden de producción',
            ] as $codigo => $nombre
        ) {
            TipoOrden::query()->updateOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'estado' => true]
            );
        }

        $this->tipoProduccion = TipoOrden::query()
            ->where('codigo', 'OP')
            ->firstOrFail();
        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Cliente final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );
        $this->cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20617041111',
            'ruc' => '20617041111',
            'razon_social' => 'Cliente UI 1.1 SAC',
            'estado' => true,
        ]);
        $this->vehiculo = Vehiculo::query()->create([
            'cliente_id' => $this->cliente->id,
            'placa' => 'UI1-100',
            'marca' => 'Hidroil',
            'modelo' => 'Prueba',
            'estado' => true,
        ]);
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'PRD-UI-11',
            'descripcion' => 'Producto con una descripción suficientemente larga para probar la tabla',
            'estado' => true,
        ]);
    }

    public function test_dashboard_de_ordenes_no_duplica_tipo_y_combina_cliente_vehiculo(): void
    {
        $tipoMantenimiento = TipoOrden::query()->where('codigo', 'OM')->firstOrFail();
        OrdenOperacion::query()->create([
            'tipo_orden_id' => $tipoMantenimiento->id,
            'cliente_id' => $this->cliente->id,
            'vehiculo_id' => $this->vehiculo->id,
            'codigo_orden' => 'OM-001-26',
            'numero_correlativo' => 1,
            'anio' => 2026,
            'fecha_apertura' => now()->toDateString(),
            'descripcion' => 'Mantenimiento de prueba para el cierre visual',
            'estado' => 'ABIERTA',
            'creado_por' => $this->logistica->id,
        ]);

        $this->actingAs($this->planta)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<th>Cliente / vehículo</th>', false)
            ->assertDontSee('<th>Tipo</th>', false)
            ->assertSee('OM-001-26')
            ->assertSee($this->vehiculo->placa);
    }

    public function test_clientes_activos_e_inactivos_conservan_texto_y_tono_neutro(): void
    {
        Cliente::query()->create([
            'tipo_cliente_id' => $this->cliente->tipo_cliente_id,
            'tipo_documento' => 'DNI',
            'numero_documento' => '76543210',
            'razon_social' => 'Cliente inactivo',
            'nombres' => 'Cliente inactivo',
            'estado' => false,
        ]);

        $this->actingAs($this->logistica)
            ->get(route('clientes.index'))
            ->assertOk()
            ->assertSee('ACTIVO')
            ->assertSee('INACTIVO')
            ->assertSee('badge--success', false)
            ->assertSee('badge--neutral', false);
    }

    public function test_direccion_fiscal_no_se_infiere_y_solo_puede_existir_una_activa(): void
    {
        $heredada = ClienteDireccion::query()->create([
            'cliente_id' => $this->cliente->id,
            'direccion' => 'Av. Antigua 100',
            'destino' => 'Sede anterior',
            'es_principal' => true,
            'estado' => true,
        ]);

        $this->assertFalse($heredada->fresh()->es_fiscal);

        $this->actingAs($this->logistica)
            ->get(route('clientes.show', $this->cliente))
            ->assertOk()
            ->assertSee('Dirección fiscal pendiente');

        $this->actingAs($this->logistica)
            ->post(
                route('clientes.direcciones.store', $this->cliente),
                $this->datosDireccion('Av. Fiscal 200')
            )
            ->assertRedirect(route('clientes.show', $this->cliente));

        $primeraFiscal = ClienteDireccion::query()
            ->where('direccion', 'Av. Fiscal 200')
            ->firstOrFail();

        $this->actingAs($this->logistica)
            ->post(
                route('clientes.direcciones.store', $this->cliente),
                $this->datosDireccion('Av. Fiscal 300')
            )
            ->assertRedirect(route('clientes.show', $this->cliente));

        $segundaFiscal = ClienteDireccion::query()
            ->where('direccion', 'Av. Fiscal 300')
            ->firstOrFail();

        $this->assertFalse($primeraFiscal->fresh()->es_fiscal);
        $this->assertTrue($segundaFiscal->fresh()->es_fiscal);
        $this->assertSame(
            1,
            $this->cliente->direcciones()->fiscalesActivas()->count()
        );

        $this->actingAs($this->logistica)
            ->patch(route('clientes.direcciones.toggle', [$this->cliente, $segundaFiscal]))
            ->assertSessionHasErrors('direccion');

        $this->assertTrue($segundaFiscal->fresh()->estado);
    }

    public function test_listado_oculta_origen_deriva_estado_y_muestra_sin_orden(): void
    {
        $cotizacion = $this->crearCotizacion();

        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-cliente.index'))
            ->assertOk()
            ->assertDontSee('<th>Origen</th>', false)
            ->assertSee('Abierta')
            ->assertSee('—');

        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-cliente.show', $cotizacion))
            ->assertOk()
            ->assertSee('<dt>Origen</dt>', false)
            ->assertSee('Cotización directa de Logística');
    }

    public function test_tabla_separa_estados_ordenes_monedas_y_acciones(): void
    {
        $this->crearCotizacion(null, [
            'codigo_base' => 'COT-000006',
            'version' => 2,
            'codigo' => 'COT-000006-VRS2',
            'cliente_nombre' => 'HIDROIL SERVICIOS INDUSTRIALES Y COMERCIALES S.A.C.',
            'moneda' => 'PEN',
            'total' => 708,
            'estado' => 'ABIERTA',
        ]);
        $this->crearCotizacion(null, [
            'codigo_base' => 'COT-000005',
            'codigo' => 'COT-000005-VRS1',
            'estado' => 'CERRADA',
        ]);
        $this->crearCotizacion(null, [
            'codigo_base' => 'COT-000004',
            'codigo' => 'COT-000004-VRS1',
            'estado' => 'ANULADA',
        ]);

        $tipoMantenimiento = TipoOrden::query()->where('codigo', 'OM')->firstOrFail();
        $orden = OrdenOperacion::query()->create([
            'tipo_orden_id' => $tipoMantenimiento->id,
            'cliente_id' => $this->cliente->id,
            'codigo_orden' => 'OM-007-26',
            'numero_correlativo' => 7,
            'anio' => 2026,
            'fecha_apertura' => now()->toDateString(),
            'descripcion' => 'Orden originada en una cotización convertida',
            'estado' => 'ABIERTA',
            'creado_por' => $this->logistica->id,
        ]);
        $this->crearCotizacion(null, [
            'codigo_base' => 'COT-000003',
            'codigo' => 'COT-000003-VRS1',
            'tipo_orden_id' => $tipoMantenimiento->id,
            'moneda' => 'USD',
            'tipo_cambio' => 3.75,
            'total' => 708,
            'estado' => 'CONVERTIDA_EN_ORDEN',
            'orden_operacion_id' => $orden->id,
        ]);

        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-cliente.index'))
            ->assertOk()
            ->assertSee('quote-list-table__status', false)
            ->assertSee('quote-list-table__order', false)
            ->assertSee('COT-000006-VRS2')
            ->assertSee('HIDROIL SERVICIOS INDUSTRIALES Y COMERCIALES S.A.C.')
            ->assertSee('Abierta')
            ->assertSee('Cerrada')
            ->assertSee('Anulada')
            ->assertSee('Convertida')
            ->assertDontSee('>Convertida en orden</span>', false)
            ->assertSee('badge--info', false)
            ->assertSee('badge--neutral', false)
            ->assertSee('badge--danger', false)
            ->assertSee('badge--success', false)
            ->assertSee('US$')
            ->assertSee('708.00')
            ->assertSee('aria-label="Sin orden asociada"', false)
            ->assertSee('href="' . route('ordenes-operacion.show', $orden) . '"', false)
            ->assertSee('aria-label="Ver orden OM-007-26"', false);
    }

    public function test_backend_ignora_tipo_de_cambio_en_pen_y_lo_exige_en_usd(): void
    {
        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.store'), $this->datosCotizacion([
                'moneda' => 'PEN',
                'tipo_cambio' => 9.999999,
            ]))
            ->assertRedirect();

        $this->assertNull(
            CotizacionCliente::query()->where('moneda', 'PEN')->value('tipo_cambio')
        );

        $this->actingAs($this->logistica)
            ->from(route('cotizaciones-cliente.create'))
            ->post(route('cotizaciones-cliente.store'), $this->datosCotizacion([
                'moneda' => 'USD',
                'tipo_cambio' => null,
            ]))
            ->assertRedirect(route('cotizaciones-cliente.create'))
            ->assertSessionHasErrors('tipo_cambio');

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.store'), $this->datosCotizacion([
                'moneda' => 'USD',
                'tipo_cambio' => 3.75,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('cotizaciones_cliente', [
            'moneda' => 'USD',
            'tipo_cambio' => 3.75,
        ]);
    }

    public function test_hidden_oculta_alerta_fiscal_resuelta_y_tipo_de_cambio_en_pen(): void
    {
        $direccionFiscal = ClienteDireccion::query()->create([
            'cliente_id' => $this->cliente->id,
            ...$this->datosDireccion('Av. Fiscal visible 400'),
        ]);
        $cotizacion = $this->crearCotizacion(null, [
            'cliente_direccion_id' => $direccionFiscal->id,
            'moneda' => 'PEN',
            'tipo_cambio' => null,
        ]);

        $respuesta = $this->actingAs($this->logistica)
            ->get(route('cotizaciones-cliente.edit', $cotizacion))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/data-fiscal-warning\s+hidden/',
            $respuesta->getContent()
        );
        $this->assertMatchesRegularExpression(
            '/data-exchange-field\s+hidden/',
            $respuesta->getContent()
        );
        $this->assertMatchesRegularExpression(
            '/\[hidden\]\s*\{\s*display\s*:\s*none\s*!important\s*;\s*\}/',
            file_get_contents(public_path('css/hidroil-admin.css'))
        );
    }

    public function test_formulario_elimina_observacion_por_producto_y_unifica_acciones(): void
    {
        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-cliente.create'))
            ->assertOk()
            ->assertSee('Tipo de cambio (PEN por USD)')
            ->assertDontSee('commercial-line__observation', false)
            ->assertDontSee('detalles[0][observacion]', false);

        $cotizacion = $this->crearCotizacion();

        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-cliente.show', $cotizacion))
            ->assertOk()
            ->assertSee('Acciones de la cotización')
            ->assertSee('Fecha de apertura')
            ->assertSee('Motivo de anulación')
            ->assertSee('data-confirm-title="Aprobar cotización"', false)
            ->assertSee('data-confirm-title="Anular cotización"', false);
    }

    public function test_editar_conserva_observacion_historica_del_detalle(): void
    {
        $cotizacion = $this->crearCotizacion('Dato histórico del producto');

        $this->actingAs($this->logistica)
            ->put(
                route('cotizaciones-cliente.update', $cotizacion),
                $this->datosCotizacion()
            )
            ->assertRedirect(route('cotizaciones-cliente.show', $cotizacion));

        $this->assertSame(
            'Dato histórico del producto',
            $cotizacion->detalles()->firstOrFail()->observacion
        );
    }

    public function test_aprobar_hereda_productos_sin_mover_inventario_y_almacen_no_gestiona(): void
    {
        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.store'), $this->datosCotizacion())
            ->assertRedirect();

        $cotizacion = CotizacionCliente::query()->firstOrFail();

        $this->actingAs($this->almacen)
            ->patch(route('cotizaciones-cliente.anular', $cotizacion), [
                'motivo_anulacion' => 'Intento sin permiso',
            ])
            ->assertForbidden();

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.convertir-orden', $cotizacion), [
                'fecha_apertura' => now()->toDateString(),
            ])
            ->assertRedirect();

        $orden = OrdenOperacion::query()->firstOrFail();
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertSame('CONVERTIDA_EN_ORDEN', $cotizacion->fresh()->estado);

        $this->actingAs($this->planta)
            ->get(route('ordenes-operacion.show', $orden))
            ->assertOk()
            ->assertSee($this->producto->codigo)
            ->assertSee('order-products-table', false);
    }

    private function datosDireccion(string $direccion): array
    {
        return [
            'direccion' => $direccion,
            'destino' => 'Domicilio fiscal',
            'departamento' => 'La Libertad',
            'provincia' => 'Trujillo',
            'distrito' => 'Trujillo',
            'ciudad' => 'Trujillo',
            'referencia' => null,
            'es_fiscal' => true,
            'es_principal' => false,
            'estado' => true,
        ];
    }

    private function datosCotizacion(array $cambios = []): array
    {
        return array_replace([
            'cliente_id' => $this->cliente->id,
            'tipo_orden_id' => $this->tipoProduccion->id,
            'cliente_direccion_id' => null,
            'vehiculo_id' => null,
            'descripcion_trabajo' => 'Fabricación cotizada para el cliente',
            'fecha_emision' => now()->toDateString(),
            'fecha_validez' => now()->addDays(15)->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => null,
            'condiciones_pago' => 'Contado',
            'condiciones_entrega' => 'Recojo en HIDROIL',
            'observacion' => 'Observación general',
            'detalles' => [[
                'producto_id' => $this->producto->id,
                'cantidad' => 2,
                'precio_unitario' => 125,
                'igv_modo' => 'AGREGAR',
            ]],
        ], $cambios);
    }

    private function crearCotizacion(
        ?string $observacionDetalle = null,
        array $atributos = []
    ): CotizacionCliente {
        $cotizacion = CotizacionCliente::query()->create(array_replace([
            'proforma_id' => null,
            'origen' => 'DIRECTA_LOGISTICA',
            'cliente_id' => $this->cliente->id,
            'tipo_orden_id' => $this->tipoProduccion->id,
            'descripcion_trabajo' => 'Fabricación cotizada para el cliente',
            'codigo_base' => 'COT-UI11001',
            'version' => 1,
            'codigo' => 'COT-UI11001-VRS1',
            'cliente_documento' => $this->cliente->documentoVisible(),
            'cliente_nombre' => $this->cliente->nombreVisible(),
            'fecha_emision' => now()->toDateString(),
            'fecha_validez' => now()->addDays(15)->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => null,
            'margen_cliente_porcentaje' => 20,
            'subtotal' => 250,
            'impuesto' => 45,
            'total' => 295,
            'estado' => 'ABIERTA',
            'cotizado_por' => $this->logistica->id,
        ], $atributos));
        $cotizacion->detalles()->create([
            'producto_id' => $this->producto->id,
            'codigo_producto' => $this->producto->codigo,
            'descripcion' => $this->producto->descripcion,
            'unidad_medida' => 'UND',
            'cantidad' => 2,
            'costo_referencia' => 100,
            'margen_sugerido' => 20,
            'precio_sugerido' => 120,
            'precio_unitario' => 125,
            'igv_modo' => 'AGREGAR',
            'igv_porcentaje' => 18,
            'subtotal' => 250,
            'impuesto' => 45,
            'total' => 295,
            'observacion' => $observacionDetalle,
        ]);

        return $cotizacion;
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
