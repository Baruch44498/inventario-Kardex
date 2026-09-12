<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\Inventario;
use App\Models\OrdenOperacion;
use App\Models\Producto;
use App\Models\Proforma;
use App\Models\Repisa;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase17022FlujoComercialAdministradorTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;

    private User $logistica;

    private User $planta;

    private User $administrador;

    private Cliente $cliente;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = $this->usuarioConRol('ALMACEN', 'almacen_17022');
        $this->logistica = $this->usuarioConRol('COMERCIAL_LOGISTICA', 'logistica_17022');
        $this->planta = $this->usuarioConRol('JEFE_PLANTA', 'planta_17022');
        $this->administrador = $this->usuarioConRol('ADMINISTRADOR', 'admin_17022');
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
        [$this->cliente, $this->producto] = $this->catalogosComerciales();
    }

    public function test_logistica_crea_cotizacion_directa_y_la_convierte_en_ov(): void
    {
        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.store'), $this->datosCotizacion())
            ->assertRedirect();

        $cotizacion = CotizacionCliente::query()->with('detalles')->firstOrFail();

        $this->assertNull($cotizacion->proforma_id);
        $this->assertSame('DIRECTA_LOGISTICA', $cotizacion->origen);
        $this->assertSame('COT-000001-VRS1', $cotizacion->codigo);
        $this->assertSame('ABIERTA', $cotizacion->estado);

        $this->actingAs($this->logistica)
            ->patch(route('cotizaciones-cliente.cerrar', $cotizacion))
            ->assertRedirect(route('cotizaciones-cliente.show', $cotizacion));

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.convertir-orden', $cotizacion), [
                'tipo_orden_id' => TipoOrden::query()->where('codigo', 'OP')->value('id'),
                'fecha_apertura' => now()->toDateString(),
                'descripcion' => 'Producción y fabricación cotizada al cliente',
            ])
            ->assertRedirect();

        $orden = OrdenOperacion::query()->firstOrFail();
        $cotizacion->refresh();

        $this->assertSame('OV-001-' . now()->format('y'), $orden->codigo_orden);
        $this->assertSame($cotizacion->id, $orden->cotizacionCliente?->id);
        $this->assertSame($orden->id, $cotizacion->orden_operacion_id);
        $this->assertSame('CONVERTIDA_EN_ORDEN', $cotizacion->estado);
        $this->assertDatabaseCount('movimientos_inventario', 0);

        $this->actingAs($this->planta)
            ->get(route('ordenes-operacion.show', $orden))
            ->assertOk()
            ->assertSee($cotizacion->codigo)
            ->assertSee($this->producto->codigo)
            ->assertSee('Productos vendidos');

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.version', $cotizacion))
            ->assertRedirect();

        $vrs2 = CotizacionCliente::query()->where('version', 2)->firstOrFail();
        $this->assertSame('COT-000001-VRS2', $vrs2->codigo);
        $this->assertSame('ABIERTA', $vrs2->estado);
        $this->assertNull($vrs2->orden_operacion_id);
        $this->assertSame($orden->id, $cotizacion->fresh()->orden_operacion_id);
    }

    public function test_las_proformas_de_almacen_y_el_costeo_avanzado_no_tienen_rutas(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('proformas.index'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('proformas.create'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('proformas.cotizar'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('plantillas-costeo.index'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('cotizaciones-cliente.presupuesto.show'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('cotizaciones-cliente.componentes.show'));
    }

    public function test_la_creacion_manual_redirige_al_documento_de_origen(): void
    {
        $this->actingAs($this->almacen)
            ->get(route('ordenes-operacion.create'))
            ->assertForbidden();

        $this->actingAs($this->logistica)
            ->get(route('ordenes-operacion.create'))
            ->assertRedirect(route('cotizaciones-cliente.create'));
    }

    public function test_administrador_ve_dashboard_y_menu_organizados_por_area(): void
    {
        $this->actingAs($this->administrador)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ventas')
            ->assertSee('Compras y proveedores')
            ->assertSee('Almacén')
            ->assertSee('Contabilidad')
            ->assertSee('Administración del sistema')
            ->assertSee('Cotizaciones al cliente')
            ->assertDontSee('Control de planta')
            ->assertDontSee('Órdenes OM, OS y OP')
            ->assertDontSee('Proformas de venta directa')
            ->assertDontSee('Plantillas de costeo')
            ->assertDontSee('Cuentas por cobrar');
    }

    private function datosCotizacion(): array
    {
        $tipoProduccion = TipoOrden::query()->where('codigo', 'OP')->firstOrFail();

        return [
            'cliente_id' => $this->cliente->id,
            'tipo_orden_id' => $tipoProduccion->id,
            'cliente_direccion_id' => null,
            'vehiculo_id' => null,
            'descripcion_trabajo' => 'Producción y fabricación cotizada al cliente',
            'fecha_emision' => now()->toDateString(),
            'fecha_validez' => now()->addDays(15)->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => null,
            'condiciones_pago' => 'Contado',
            'condiciones_entrega' => 'Recojo en Almacén',
            'observacion' => 'Cotización directa de prueba',
            'detalles' => [[
                'producto_id' => $this->producto->id,
                'cantidad' => 2,
                'precio_unitario' => 125,
                'igv_modo' => 'AGREGAR',
                'observacion' => null,
            ]],
        ];
    }

    private function catalogosComerciales(): array
    {
        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );
        $cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20987654321',
            'ruc' => '20987654321',
            'razon_social' => 'Cliente Fase 17.0.2.2 SAC',
            'estado' => true,
        ]);
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $repisa = Repisa::query()->create([
            'codigo' => 'R-17022',
            'descripcion' => 'Repisa de pruebas fase 17.0.2.2',
            'estado' => true,
        ]);
        $producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'PRD-17022',
            'descripcion' => 'Producto de prueba fase 17.0.2.2',
            'estado' => true,
        ]);
        Inventario::query()->create([
            'producto_id' => $producto->id,
            'repisa_id' => $repisa->id,
            'stock_actual' => 25,
            'stock_minimo' => 5,
            'stock_maximo' => 50,
            'costo_promedio_soles' => 100,
        ]);

        return [$cliente, $producto];
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
