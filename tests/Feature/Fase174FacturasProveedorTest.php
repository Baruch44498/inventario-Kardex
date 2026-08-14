<?php

namespace Tests\Feature;

use App\Models\Cotizacion;
use App\Models\FacturaProveedor;
use App\Models\Inventario;
use App\Models\NotaIngreso;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Repisa;
use App\Models\Requisicion;
use App\Models\Role;
use App\Models\SolicitudCompra;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Fase174FacturasProveedorTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private User $contabilidad;
    private User $logistica;
    private Producto $producto;
    private Proveedor $proveedor;
    private Repisa $repisa;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->almacen = $this->crearUsuario('ALMACEN', 'almacen_174');
        $this->contabilidad = $this->crearUsuario('CONTABILIDAD', 'contabilidad_174');
        $this->logistica = $this->crearUsuario('COMERCIAL_LOGISTICA', 'logistica_174');

        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'FAC-174-A',
            'descripcion' => 'Producto para factura y recepción',
            'estado' => true,
        ]);
        $this->proveedor = Proveedor::query()->create([
            'ruc' => '20617400001',
            'razon_social' => 'Proveedor Facturas 174 SAC',
            'estado' => true,
        ]);
        $this->repisa = Repisa::query()->create([
            'codigo' => 'FAC-174',
            'descripcion' => 'Repisa para facturas 17.4',
            'estado' => true,
        ]);
    }

    public function test_almacen_registra_factura_con_original_base_igv_y_total_incluido(): void
    {
        $orden = $this->crearOrden();

        $this->actingAs($this->almacen)
            ->post(route('facturas-proveedor.store'), $this->datosFactura($orden, '0001', 10, 100, 18, 118))
            ->assertRedirect();

        $factura = FacturaProveedor::query()->with('detalles')->firstOrFail();
        $detalle = $factura->detalles->firstOrFail();

        $this->assertSame('REGISTRADA', $factura->estado);
        $this->assertSame(100.0, (float) $factura->subtotal);
        $this->assertSame(18.0, (float) $factura->impuesto);
        $this->assertSame(118.0, (float) $factura->total);
        $this->assertSame(10.0, (float) $detalle->cantidad);
        $this->assertSame(118.0, (float) $detalle->total);
        $this->assertSame(11.8, $detalle->costoUnitarioTotalDocumento());
        Storage::disk('local')->assertExists($factura->archivo_original_path);
        $this->assertSame('CONCILIADA', $orden->fresh()->conciliacionFacturas()['estado']);
    }

    public function test_facturas_parciales_concilian_acumuladas_y_bloquean_sobre_facturacion(): void
    {
        $orden = $this->crearOrden();

        $this->actingAs($this->almacen)
            ->post(route('facturas-proveedor.store'), $this->datosFactura($orden, '0002', 5, 50, 9, 59))
            ->assertRedirect();

        $this->assertSame('PARCIAL', $orden->fresh()->conciliacionFacturas()['estado']);

        $this->actingAs($this->almacen)
            ->post(route('facturas-proveedor.store'), $this->datosFactura($orden, '0003', 5.001, 50.01, 9, 59.01))
            ->assertSessionHasErrors('detalles.0.cantidad');

        $this->assertDatabaseCount('facturas_proveedor', 1);

        $this->actingAs($this->almacen)
            ->post(route('facturas-proveedor.store'), $this->datosFactura($orden, '0003', 5, 50, 9, 59))
            ->assertRedirect();

        $this->assertSame('CONCILIADA', $orden->fresh()->conciliacionFacturas()['estado']);
        $this->assertSame(10.0, (float) $orden->detalles->first()->fresh()->cantidadFacturada());
    }

    public function test_documento_repetido_y_totales_inconsistentes_no_se_registran(): void
    {
        $orden = $this->crearOrden();

        $this->actingAs($this->almacen)
            ->post(route('facturas-proveedor.store'), $this->datosFactura($orden, '0004', 5, 50, 9, 59))
            ->assertRedirect();

        $this->actingAs($this->almacen)
            ->post(route('facturas-proveedor.store'), $this->datosFactura($orden, '0004', 5, 50, 9, 59))
            ->assertSessionHasErrors('numero');

        $inconsistente = $this->datosFactura($orden, '0005', 5, 50, 9, 59);
        $inconsistente['total_documento'] = 60;
        $this->actingAs($this->almacen)
            ->post(route('facturas-proveedor.store'), $inconsistente)
            ->assertSessionHasErrors('total_documento');

        $this->assertDatabaseCount('facturas_proveedor', 1);
    }

    public function test_contabilidad_consulta_pero_no_registra_ni_anula(): void
    {
        $orden = $this->crearOrden();
        $this->actingAs($this->almacen)
            ->post(route('facturas-proveedor.store'), $this->datosFactura($orden, '0006', 10, 100, 18, 118));
        $factura = FacturaProveedor::query()->firstOrFail();

        $this->actingAs($this->contabilidad)
            ->get(route('facturas-proveedor.index'))
            ->assertOk()
            ->assertSee('Crédito fiscal IGV')
            ->assertSee('F001-0006');

        $this->actingAs($this->contabilidad)
            ->get(route('facturas-proveedor.show', $factura))
            ->assertOk()
            ->assertSee('Resumen fiscal')
            ->assertSee('IGV 18% / crédito fiscal');

        $this->actingAs($this->contabilidad)
            ->get(route('facturas-proveedor.create', $orden))
            ->assertForbidden();
        $this->actingAs($this->contabilidad)
            ->patch(route('facturas-proveedor.anular', $factura), ['motivo_anulacion' => 'No autorizado'])
            ->assertForbidden();
    }

    public function test_recepcion_con_factura_usd_usa_costo_total_real_en_soles_y_bloquea_anulacion(): void
    {
        $orden = $this->crearOrden('USD', 3.75);
        $this->actingAs($this->almacen)
            ->post(route('facturas-proveedor.store'), $this->datosFactura($orden, '0007', 10, 100, 18, 118, 3.80))
            ->assertRedirect();
        $factura = FacturaProveedor::query()->firstOrFail();
        $this->assertSame('CONCILIADA', $orden->fresh()->conciliacionFacturas()['estado']);

        $detalleOrden = $orden->detalles->firstOrFail();
        $this->actingAs($this->almacen)
            ->post(route('notas-ingreso.store'), [
                'motivo_ingreso' => 'COMPRA',
                'orden_compra_id' => $orden->id,
                'factura_proveedor_id' => $factura->id,
                'fecha_ingreso' => now()->toDateString(),
                'numero_guia_remision' => 'T001-174',
                'detalles' => [[
                    'orden_compra_detalle_id' => $detalleOrden->id,
                    'producto_id' => $this->producto->id,
                    'repisa_id' => $this->repisa->id,
                    'cantidad' => 2,
                    'costo_unitario' => 1,
                ]],
            ])
            ->assertRedirect();

        $nota = NotaIngreso::query()->with('detalles')->firstOrFail();
        $this->assertSame($factura->id, $nota->factura_proveedor_id);
        $this->assertSame(44.84, (float) $nota->detalles->first()->costo_unitario);
        $this->assertSame(44.84, (float) Inventario::query()->firstOrFail()->costo_promedio_soles);

        $this->actingAs($this->contabilidad)
            ->get(route('notas-ingreso.show', $nota))
            ->assertOk()
            ->assertSee('Costo real del documento aplicado')
            ->assertSee('F001-0007');

        $rutaOriginal = $factura->archivo_original_path;
        $this->actingAs($this->almacen)
            ->patch(route('facturas-proveedor.anular', $factura), [
                'motivo_anulacion' => 'Documento registrado por error',
            ])
            ->assertSessionHas('error');

        $this->assertSame('REGISTRADA', $factura->fresh()->estado);
        Storage::disk('local')->assertExists($rutaOriginal);
    }

    private function crearOrden(string $moneda = 'PEN', ?float $tipoCambio = null): OrdenCompra
    {
        $sufijo = OrdenCompra::query()->count() + 1;
        $requisicion = Requisicion::query()->create([
            'codigo' => "REQ-FAC-{$sufijo}",
            'fecha_solicitud' => now()->toDateString(),
            'origen' => 'REPOSICION',
            'prioridad' => 'MEDIA',
            'estado' => 'COTIZANDO',
            'solicitado_por' => $this->almacen->id,
        ]);
        $requisicionDetalle = $requisicion->detalles()->create([
            'producto_id' => $this->producto->id,
            'cantidad_solicitada' => 10,
            'cantidad_sugerida' => 10,
            'cantidad_atendida' => 0,
        ]);
        $cotizacion = Cotizacion::query()->create([
            'requisicion_id' => $requisicion->id,
            'proveedor_id' => $this->proveedor->id,
            'codigo' => "CP-FAC-{$sufijo}",
            'numero_documento' => "PROV-FAC-{$sufijo}",
            'fecha_cotizacion' => now()->toDateString(),
            'moneda' => $moneda,
            'tipo_cambio' => $tipoCambio,
            'descuento_global_modo' => 'SIN_DESCUENTO',
            'descuento_global_monto' => 0,
            'subtotal' => 100,
            'impuesto' => 18,
            'total' => 118,
            'total_calculado' => 118,
            'ajuste_redondeo' => 0,
            'estado' => 'SELECCIONADA',
            'registrado_por' => $this->logistica->id,
        ]);
        $cotizacionDetalle = $cotizacion->detalles()->create([
            'requisicion_detalle_id' => $requisicionDetalle->id,
            'tipo_vinculacion' => 'SOLICITADO',
            'vinculacion_origen' => 'MANUAL',
            'producto_id' => $this->producto->id,
            'cantidad' => 10,
            'precio_unitario' => 11.8,
            'descuento_porcentaje' => 0,
            'descuento_modo' => 'SIN_DESCUENTO',
            'igv_modo' => 'INCLUIDO',
            'igv_porcentaje' => 18,
            'subtotal' => 100,
            'impuesto' => 18,
            'total' => 118,
        ]);
        $solicitud = SolicitudCompra::query()->create([
            'cotizacion_id' => $cotizacion->id,
            'codigo' => "SC-FAC-{$sufijo}",
            'fecha_solicitud' => now()->toDateString(),
            'total_lineas' => 118,
            'ajuste_redondeo' => 0,
            'total_seleccionado' => 118,
            'estado' => 'CONVERTIDA',
            'solicitado_por' => $this->logistica->id,
            'aprobado_por' => $this->logistica->id,
            'aprobado_en' => now(),
        ]);
        $solicitudDetalle = $solicitud->detalles()->create([
            'cotizacion_detalle_id' => $cotizacionDetalle->id,
            'producto_id' => $this->producto->id,
            'cantidad' => 10,
            'precio_unitario' => 11.8,
            'descuento_porcentaje' => 0,
            'subtotal' => 118,
        ]);
        $orden = OrdenCompra::query()->create([
            'solicitud_compra_id' => $solicitud->id,
            'proveedor_id' => $this->proveedor->id,
            'codigo' => "OC-FAC-{$sufijo}",
            'fecha_emision' => now()->toDateString(),
            'moneda' => $moneda,
            'tipo_cambio' => $tipoCambio,
            'subtotal' => 100,
            'impuesto' => 18,
            'ajuste_redondeo' => 0,
            'total' => 118,
            'estado' => 'APROBADA',
            'emitido_por' => $this->logistica->id,
            'aprobado_por' => $this->logistica->id,
            'aprobado_en' => now(),
        ]);
        $orden->detalles()->create([
            'solicitud_compra_detalle_id' => $solicitudDetalle->id,
            'producto_id' => $this->producto->id,
            'cantidad_ordenada' => 10,
            'cantidad_recibida' => 0,
            'precio_unitario' => 11.8,
            'descuento_porcentaje' => 0,
            'subtotal' => 118,
        ]);

        return $orden->load('detalles');
    }

    /** @return array<string, mixed> */
    private function datosFactura(
        OrdenCompra $orden,
        string $numero,
        float $cantidad,
        float $base,
        float $igv,
        float $total,
        ?float $tipoCambio = null
    ): array {
        $detalle = $orden->detalles->firstOrFail();

        return [
            'orden_compra_id' => $orden->id,
            'tipo_documento' => 'FACTURA',
            'serie' => 'F001',
            'numero' => $numero,
            'fecha_emision' => now()->toDateString(),
            'moneda' => $orden->moneda,
            'tipo_cambio' => $tipoCambio,
            'subtotal_documento' => $base,
            'impuesto_documento' => $igv,
            'total_documento' => $total,
            'archivo_original' => UploadedFile::fake()->create("factura-{$numero}.pdf", 64, 'application/pdf'),
            'detalles' => [[
                'orden_compra_detalle_id' => $detalle->id,
                'producto_id' => $detalle->producto_id,
                'cantidad' => $cantidad,
                'costo_unitario_total' => 11.8,
                'afecto_igv' => 1,
            ]],
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
