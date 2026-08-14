<?php

namespace Tests\Feature;

use App\Models\Cotizacion;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\Role;
use App\Models\SolicitudCompra;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase172OrdenesCompraTest extends TestCase
{
    use RefreshDatabase;

    private User $logistica;
    private User $contabilidad;
    private User $almacen;
    private Producto $producto;
    private Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistica = $this->crearUsuario('COMERCIAL_LOGISTICA', 'logistica_172');
        $this->contabilidad = $this->crearUsuario('CONTABILIDAD', 'contabilidad_172');
        $this->almacen = $this->crearUsuario('ALMACEN', 'almacen_172');
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'OC-172-001',
            'descripcion' => 'Producto para orden de compra',
            'estado' => true,
        ]);
        $this->proveedor = Proveedor::query()->create([
            'ruc' => '20617200001',
            'razon_social' => 'Proveedor Orden 172 SAC',
            'estado' => true,
        ]);
    }

    public function test_solicitud_rechazada_no_puede_generar_orden(): void
    {
        $solicitud = $this->crearSolicitud('RECHAZADA');

        $this->actingAs($this->logistica)
            ->get(route('ordenes-compra.create', $solicitud))
            ->assertRedirect(route('solicitudes-compra.show', $solicitud));

        $this->actingAs($this->logistica)
            ->post(route('ordenes-compra.store'), $this->datosOrden($solicitud))
            ->assertSessionHasErrors('solicitud_compra_id');

        $this->assertDatabaseCount('ordenes_compra', 0);
    }

    public function test_registro_pendiente_del_flujo_anterior_puede_generar_orden(): void
    {
        $solicitud = $this->crearSolicitud('PENDIENTE');

        $this->actingAs($this->logistica)
            ->post(route('ordenes-compra.store'), $this->datosOrden($solicitud))
            ->assertRedirect();

        $orden = OrdenCompra::query()->firstOrFail();
        $this->assertSame($this->logistica->id, $orden->aprobado_por);
        $this->assertSame('CONVERTIDA', $solicitud->fresh()->estado);
    }

    public function test_orden_copia_lineas_y_total_congelado_y_convierte_solicitud(): void
    {
        $solicitud = $this->crearSolicitud('APROBADA', -0.01);

        $this->actingAs($this->logistica)
            ->post(route('ordenes-compra.store'), $this->datosOrden($solicitud))
            ->assertRedirect();

        $orden = OrdenCompra::query()->with('detalles')->firstOrFail();
        $this->assertSame('APROBADA', $orden->estado);
        $this->assertSame('PEN', $orden->moneda);
        $this->assertSame(100.0, (float) $orden->subtotal);
        $this->assertSame(-0.01, (float) $orden->ajuste_redondeo);
        $this->assertSame(99.99, (float) $orden->total);
        $this->assertCount(1, $orden->detalles);
        $this->assertSame(5.0, (float) $orden->detalles->first()->cantidad_ordenada);
        $this->assertSame(0.0, (float) $orden->detalles->first()->cantidad_recibida);
        $this->assertSame('CONVERTIDA', $solicitud->fresh()->estado);
    }

    public function test_una_solicitud_no_puede_generar_dos_ordenes(): void
    {
        $solicitud = $this->crearSolicitud('APROBADA');
        $datos = $this->datosOrden($solicitud);

        $this->actingAs($this->logistica)->post(route('ordenes-compra.store'), $datos);
        $solicitud->update(['estado' => 'APROBADA']);

        $this->actingAs($this->logistica)
            ->post(route('ordenes-compra.store'), $datos)
            ->assertSessionHasErrors('solicitud_compra_id');

        $this->assertDatabaseCount('ordenes_compra', 1);
    }

    public function test_orden_aprobada_aparece_para_recepcion_de_almacen(): void
    {
        $solicitud = $this->crearSolicitud('APROBADA');
        $this->actingAs($this->logistica)->post(route('ordenes-compra.store'), $this->datosOrden($solicitud));
        $orden = OrdenCompra::query()->firstOrFail();

        $respuesta = $this->actingAs($this->almacen)
            ->getJson(route('catalogos.ordenes-compra.buscar', ['q' => $orden->codigo]));

        $respuesta->assertOk()->assertJsonFragment(['id' => $orden->id]);

        $this->actingAs($this->almacen)
            ->get(route('ordenes-compra.show', $orden))
            ->assertOk()
            ->assertSee('Registrar recepción');

        $this->actingAs($this->contabilidad)
            ->get(route('ordenes-compra.show', $orden))
            ->assertOk()
            ->assertDontSee('Registrar recepción');
    }

    public function test_anulacion_previa_a_recepcion_conserva_auditoria_y_bloquea_ingreso(): void
    {
        $solicitud = $this->crearSolicitud('APROBADA');
        $this->actingAs($this->logistica)->post(route('ordenes-compra.store'), $this->datosOrden($solicitud));
        $orden = OrdenCompra::query()->firstOrFail();

        $this->actingAs($this->logistica)
            ->patch(route('ordenes-compra.anular', $orden), [
                'motivo_anulacion' => 'El proveedor canceló la entrega antes del despacho.',
            ])
            ->assertRedirect();

        $this->assertSame('ANULADA', $orden->fresh()->estado);
        $this->actingAs($this->almacen)
            ->getJson(route('catalogos.ordenes-compra.buscar', ['q' => $orden->codigo]))
            ->assertJsonMissing(['id' => $orden->id]);
    }

    public function test_ajuste_visual_archivar_no_comprime_el_boton(): void
    {
        $css = file_get_contents(public_path('css/hidroil-admin.css'));

        $this->assertStringContainsString('.supplier-quote-archive-panel {', $css);
        $this->assertStringContainsString('white-space: nowrap;', $css);
        $this->assertStringContainsString('min-width: 180px;', $css);
    }

    private function crearSolicitud(string $estado, float $ajuste = 0.0): SolicitudCompra
    {
        $requisicion = Requisicion::query()->create([
            'codigo' => 'REQ-OC-'.(SolicitudCompra::query()->count() + 1),
            'fecha_solicitud' => now()->toDateString(),
            'origen' => 'REPOSICION',
            'prioridad' => 'MEDIA',
            'estado' => 'COTIZANDO',
            'solicitado_por' => $this->logistica->id,
        ]);
        $cotizacion = Cotizacion::query()->create([
            'requisicion_id' => $requisicion->id,
            'proveedor_id' => $this->proveedor->id,
            'codigo' => 'CP-OC-'.(Cotizacion::query()->count() + 1),
            'numero_documento' => 'COT-PROV-172',
            'fecha_cotizacion' => now()->toDateString(),
            'moneda' => 'PEN',
            'subtotal' => 100,
            'impuesto' => 0,
            'total' => 100 + $ajuste,
            'total_calculado' => 100,
            'ajuste_redondeo' => $ajuste,
            'estado' => 'SELECCIONADA',
            'registrado_por' => $this->logistica->id,
        ]);
        $solicitud = SolicitudCompra::query()->create([
            'cotizacion_id' => $cotizacion->id,
            'codigo' => 'SC-OC-'.(SolicitudCompra::query()->count() + 1),
            'fecha_solicitud' => now()->toDateString(),
            'total_lineas' => 100,
            'ajuste_redondeo' => $ajuste,
            'total_seleccionado' => 100 + $ajuste,
            'estado' => $estado,
            'solicitado_por' => $this->logistica->id,
            'aprobado_por' => $estado === 'APROBADA' ? $this->contabilidad->id : null,
            'aprobado_en' => $estado === 'APROBADA' ? now() : null,
        ]);
        $solicitud->detalles()->create([
            'producto_id' => $this->producto->id,
            'cantidad' => 5,
            'precio_unitario' => 20,
            'descuento_porcentaje' => 0,
            'subtotal' => 100,
        ]);

        return $solicitud;
    }

    /** @return array<string, mixed> */
    private function datosOrden(SolicitudCompra $solicitud): array
    {
        return [
            'solicitud_compra_id' => $solicitud->id,
            'fecha_emision' => now()->toDateString(),
            'fecha_entrega_requerida' => now()->addDays(7)->toDateString(),
            'condiciones_pago' => 'Crédito a 15 días.',
            'condiciones_entrega' => 'Entrega en almacén principal.',
        ];
    }

    private function crearUsuario(string $rol, string $username): User
    {
        return User::query()->create([
            'role_id' => Role::query()->where('codigo', $rol)->firstOrFail()->id,
            'username' => $username,
            'email' => "{$username}@example.com",
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
    }
}
