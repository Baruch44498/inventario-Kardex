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
use App\Services\Inventario\RegistrarNotaIngresoService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
        $this->asegurarRecepcion($orden);

        $this->actingAs($this->almacen)
            ->get(route('facturas-proveedor.create', $orden))
            ->assertOk()
            ->assertSee('Importes no editables')
            ->assertSee('name="detalles[0][cantidad]"', false)
            ->assertDontSee('name="detalles[0][costo_unitario_total]"', false)
            ->assertDontSee('name="subtotal_documento"', false)
            ->assertDontSee('name="impuesto_documento"', false)
            ->assertDontSee('name="total_documento"', false)
            ->assertDontSee('name="tipo_cambio"', false);

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

    public function test_factura_redondea_solo_el_total_pagable_y_conserva_las_lineas_a_cuatro_decimales(): void
    {
        foreach ([
            ['numero' => 'RED-UP', 'total_linea' => 118.0270, 'total_factura' => 118.03, 'ajuste' => 0.0030],
            ['numero' => 'RED-DOWN', 'total_linea' => 118.0130, 'total_factura' => 118.01, 'ajuste' => -0.0030],
        ] as $caso) {
            $orden = $this->crearOrden();
            $cotizacionDetalle = $orden->detalles->firstOrFail()->solicitudCompraDetalle->cotizacionDetalle;
            $cotizacionDetalle->update(['total' => $caso['total_linea']]);

            $this->actingAs($this->almacen)
                ->post(route('facturas-proveedor.store'), $this->datosFactura($orden, $caso['numero'], 10, 100, 18, 118))
                ->assertRedirect()->assertSessionHasNoErrors();

            $factura = FacturaProveedor::query()->where('numero', $caso['numero'])->firstOrFail();
            $this->assertEqualsWithDelta($caso['total_linea'], (float) $factura->detalles()->sum('total'), 0.00005);
            $this->assertEqualsWithDelta($caso['total_factura'], (float) $factura->total, 0.00005);
            $this->assertEqualsWithDelta($caso['ajuste'], (float) $factura->ajuste_redondeo, 0.00005);
            $this->assertEqualsWithDelta(
                (float) $factura->total,
                (float) $factura->subtotal + (float) $factura->impuesto + (float) $factura->ajuste_redondeo,
                0.00005
            );
            $this->assertSame('CONCILIADA', $orden->fresh()->conciliacionFacturas()['estado']);
        }
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

    public function test_documento_repetido_se_bloquea_y_los_importes_manipulados_se_ignoran(): void
    {
        $orden = $this->crearOrden();

        $this->actingAs($this->almacen)
            ->post(route('facturas-proveedor.store'), $this->datosFactura($orden, '0004', 5, 50, 9, 59))
            ->assertRedirect();

        $this->actingAs($this->almacen)
            ->post(route('facturas-proveedor.store'), $this->datosFactura($orden, '0004', 5, 50, 9, 59))
            ->assertSessionHasErrors('numero');

        $manipulada = $this->datosFactura($orden, '0005', 5, 999, 999, 1998, 999);
        $manipulada['moneda'] = 'USD';
        $manipulada['detalles'][0]['producto_id'] = 999999;
        $manipulada['detalles'][0]['costo_unitario_total'] = 999;
        $manipulada['detalles'][0]['afecto_igv'] = 0;
        $this->actingAs($this->almacen)
            ->post(route('facturas-proveedor.store'), $manipulada)
            ->assertRedirect();

        $factura = FacturaProveedor::query()->where('numero', '0005')->firstOrFail();
        $this->assertSame('PEN', $factura->moneda);
        $this->assertNull($factura->tipo_cambio);
        $this->assertSame(50.0, (float) $factura->subtotal);
        $this->assertSame(9.0, (float) $factura->impuesto);
        $this->assertSame(59.0, (float) $factura->total);
        $this->assertSame(11.8, $factura->detalles()->firstOrFail()->costoUnitarioTotalDocumento());
        $this->assertDatabaseCount('facturas_proveedor', 2);
    }

    public function test_no_permite_facturar_antes_de_una_recepcion_confirmada(): void
    {
        $orden = $this->crearOrden();
        $this->actingAs($this->almacen)
            ->get(route('ordenes-compra.show', $orden))
            ->assertOk()
            ->assertDontSee('Registrar factura');

        $this->actingAs($this->almacen)
            ->get(route('facturas-proveedor.create', $orden))
            ->assertRedirect(route('ordenes-compra.show', $orden))
            ->assertSessionHas('warning');

        $this->actingAs($this->almacen)
            ->post(route('facturas-proveedor.store'), $this->datosFactura($orden, '0005A', 10, 100, 18, 118, null, false))
            ->assertSessionHasErrors('detalles.0.nota_ingreso_detalle_id');

        $this->assertDatabaseCount('facturas_proveedor', 0);
        $this->assertDatabaseCount('notas_ingreso', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_con_recepcion_previa_no_permite_facturar_mas_de_lo_recibido(): void
    {
        $ordenParcial = $this->crearOrden();
        $this->registrarRecepcion($ordenParcial, 4);
        $sobreRecibido = $this->datosFactura($ordenParcial, '0005B', 5, 50, 9, 59);

        $this->actingAs($this->almacen)
            ->post(route('facturas-proveedor.store'), $sobreRecibido)
            ->assertSessionHasErrors('detalles.0.cantidad');
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

    public function test_factura_usd_hereda_tipo_cambio_de_oc_sin_revalorizar_la_recepcion(): void
    {
        $orden = $this->crearOrden('USD', 3.75);
        $this->actingAs($this->almacen)
            ->post(route('facturas-proveedor.store'), $this->datosFactura($orden, '0007', 10, 100, 18, 118, 3.80))
            ->assertRedirect();
        $factura = FacturaProveedor::query()->firstOrFail();
        $this->assertSame('CONCILIADA', $orden->fresh()->conciliacionFacturas()['estado']);

        $nota = NotaIngreso::query()->with('detalles')->firstOrFail();
        $this->assertNull($nota->factura_proveedor_id);
        $this->assertSame(3.75, (float) $factura->tipo_cambio);
        $this->assertSame(44.25, (float) $nota->detalles->first()->costo_unitario);
        $this->assertSame(44.25, (float) Inventario::query()->firstOrFail()->costo_promedio_soles);
        $this->assertDatabaseMissing('movimientos_inventario', [
            'tipo_movimiento' => 'AJUSTE_COSTO',
            'motivo' => 'FACTURA_POSTERIOR',
        ]);

        $this->actingAs($this->contabilidad)
            ->get(route('notas-ingreso.show', $nota))
            ->assertOk()
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

    public function test_no_permite_reusar_factura_de_cinco_ya_conciliada_con_recepcion_previa(): void
    {
        $orden = $this->crearOrden();
        $primera = $this->registrarRecepcion($orden, 5);
        $this->assertSame('PARCIALMENTE_RECIBIDA', $orden->fresh()->estado);

        $this->actingAs($this->almacen)
            ->post(route('facturas-proveedor.store'), $this->datosFactura($orden, 'REUSO-5', 5, 50, 9, 59))
            ->assertRedirect();

        $factura = FacturaProveedor::query()->with('detalles')->firstOrFail();
        $this->assertSame($primera->detalles->firstOrFail()->id, $factura->detalles->firstOrFail()->nota_ingreso_detalle_id);
        $this->assertNull($primera->fresh()->factura_proveedor_id);
        $this->assertSame(5.0, (float) $orden->detalles->firstOrFail()->fresh()->cantidadPendiente());

        // Debe fallar por saldo de la factura, aunque aún queden 5 unidades en la OC.
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('La cantidad supera el saldo de 0 respaldado por la factura.');
        $this->registrarRecepcion($orden, 5, $factura->id);
    }

    public function test_factura_heredada_de_diez_admite_dos_recepciones_vinculadas_de_cinco(): void
    {
        // El servicio actual no emite facturas antes de recibir: simula un documento heredado.
        $orden = $this->crearOrden();
        $factura = $this->facturaHeredada($orden, 10);

        $this->registrarRecepcion($orden, 5, $factura->id);
        $this->registrarRecepcion($orden, 5, $factura->id);

        $this->assertSame(10.0, (float) $orden->detalles->firstOrFail()->fresh()->cantidad_recibida);
        $this->assertSame(0.0, $factura->detalles->firstOrFail()->cantidadPendienteRecepcion());
    }

    public function test_factura_heredada_parcial_de_cinco_admite_tres_y_dos_pero_no_una_mas(): void
    {
        $orden = $this->crearOrden();
        $factura = $this->facturaHeredada($orden, 5);

        $this->registrarRecepcion($orden, 3, $factura->id);
        $this->registrarRecepcion($orden, 2, $factura->id);
        $this->assertSame(5.0, (float) $orden->detalles->firstOrFail()->fresh()->cantidadPendiente());

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('La cantidad supera el saldo de 0 respaldado por la factura.');
        $this->registrarRecepcion($orden, 1, $factura->id);
    }

    public function test_no_cuenta_dos_veces_una_recepcion_con_ambos_vinculos_a_la_factura(): void
    {
        $orden = $this->crearOrden();
        $factura = $this->facturaHeredada($orden, 5);
        $primera = $this->registrarRecepcion($orden, 3, $factura->id);
        $factura->detalles()->firstOrFail()->update([
            'nota_ingreso_detalle_id' => $primera->detalles->firstOrFail()->id,
            'cantidad' => 3,
            'subtotal' => 30,
            'impuesto' => 5.4,
            'total' => 35.4,
        ]);
        $factura->detalles()->create([
            'orden_compra_detalle_id' => $orden->detalles->firstOrFail()->id,
            'producto_id' => $this->producto->id,
            'descripcion' => $this->producto->descripcion,
            'cantidad' => 2,
            'precio_unitario' => 10,
            'igv_porcentaje' => 18,
            'subtotal' => 20,
            'impuesto' => 3.6,
            'total' => 23.6,
        ]);

        $this->registrarRecepcion($orden, 2, $factura->id);
        $this->assertSame(5.0, (float) $orden->detalles->firstOrFail()->fresh()->cantidad_recibida);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('La cantidad supera el saldo de 0 respaldado por la factura.');
        $this->registrarRecepcion($orden, 1, $factura->id);
    }

    public function test_no_acepta_factura_anulada_para_recepciones_vinculadas(): void
    {
        $orden = $this->crearOrden();
        $factura = $this->facturaHeredada($orden, 5);
        $factura->update(['estado' => 'ANULADA', 'anulado_por' => $this->almacen->id, 'anulado_en' => now()]);

        $this->expectException(ModelNotFoundException::class);
        $this->registrarRecepcion($orden, 1, $factura->id);
    }

    public function test_estados_por_cantidades_siguen_recepcion_parcial_factura_parcial_y_cierre(): void
    {
        $orden = $this->crearOrden();
        $this->actingAs($this->almacen)->get(route('ordenes-compra.show', $orden))
            ->assertOk()->assertSee('Facturación: Sin recepciones')->assertDontSee('Registrar factura de lo recibido');

        $primera = $this->registrarRecepcion($orden, 4);
        $this->get(route('ordenes-compra.show', $orden))
            ->assertOk()->assertSee('Facturación: Pendiente de facturar')
            ->assertSee('Registrar factura de lo recibido');
        $this->get(route('notas-ingreso.show', $primera))
            ->assertOk()->assertSee('Pendiente de factura')->assertSee('Registrar factura de lo recibido');

        $this->post(route('facturas-proveedor.store'), $this->datosFactura($orden, 'ESTADO-2', 2, 20, 3.6, 23.6))
            ->assertRedirect();
        $this->get(route('ordenes-compra.show', $orden))
            ->assertOk()->assertSee('Facturación: Facturación parcial');
        $this->get(route('notas-ingreso.show', $primera))
            ->assertOk()->assertSee('Facturada parcialmente')->assertSee('ESTADO-2')
            ->assertDontSee('La OC ya no tiene saldo facturable para estos productos');

        $this->post(route('facturas-proveedor.store'), $this->datosFactura($orden, 'ESTADO-4', 2, 20, 3.6, 23.6))
            ->assertRedirect();
        $this->get(route('ordenes-compra.show', $orden))
            ->assertOk()->assertSee('Facturación: Facturado al día');
        $this->get(route('notas-ingreso.show', $primera))
            ->assertOk()->assertSee('Facturada')->assertSee('ESTADO-2')->assertSee('ESTADO-4');

        $segunda = $this->registrarRecepcion($orden, 6);
        $this->get(route('ordenes-compra.show', $orden))
            ->assertOk()->assertSee('Facturación: Facturación parcial');
        $datos = $this->datosFactura($orden, 'ESTADO-10', 6, 60, 10.8, 70.8);
        $datos['detalles'][0]['nota_ingreso_detalle_id'] = $segunda->detalles->firstOrFail()->id;
        $this->post(route('facturas-proveedor.store'), $datos)->assertRedirect();
        $this->get(route('ordenes-compra.show', $orden))
            ->assertOk()->assertSee('Facturación: Facturado al día')
            ->assertSee('Recibida y facturada');
    }

    public function test_conciliacion_de_importes_no_sustituye_el_estado_por_cantidades(): void
    {
        $orden = $this->crearOrden();
        $this->registrarRecepcion($orden, 4);
        $this->actingAs($this->almacen)
            ->post(route('facturas-proveedor.store'), $this->datosFactura($orden, 'IMPORTE', 2, 20, 3.6, 23.6))
            ->assertRedirect();
        FacturaProveedor::query()->where('numero', 'IMPORTE')->firstOrFail()->update(['total' => 118]);

        $this->assertSame('CONCILIADA', $orden->fresh()->conciliacionFacturas()['estado']);
        $this->get(route('ordenes-compra.show', $orden))
            ->assertOk()->assertSee('Facturación: Facturación parcial')
            ->assertSee('Factura conciliada')->assertDontSee('Facturación: Facturado al día');
    }

    public function test_factura_previa_sin_vinculo_directo_a_recepcion_no_deja_estado_parcial_falso(): void
    {
        $orden = $this->crearOrden();
        $facturaPrevia = $this->facturaHeredada($orden, 5);
        $nota = $this->registrarRecepcion($orden, 10);

        $this->assertNull($facturaPrevia->detalles()->firstOrFail()->nota_ingreso_detalle_id);
        $this->assertSame(10.0, $nota->detalles()->firstOrFail()->cantidadPendienteFacturar());
        $this->assertSame(5.0, $orden->detalles->firstOrFail()->cantidadPendienteFacturar());

        $this->actingAs($this->almacen)
            ->get(route('ordenes-compra.show', $orden))
            ->assertOk()
            ->assertSee('Facturación: Facturación parcial')
            ->assertSee('Registrar factura de lo recibido');

        $this->post(route('facturas-proveedor.store'), $this->datosFactura($orden, 'LEG-RESTO', 5, 50, 9, 59))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(0.0, $orden->detalles->firstOrFail()->cantidadPendienteFacturar());
        $this->assertSame(5.0, $nota->detalles()->firstOrFail()->cantidadPendienteFacturar());
        $this->assertSame('CONCILIADA', $orden->fresh()->conciliacionFacturas()['estado']);

        $this->get(route('ordenes-compra.show', $orden))
            ->assertOk()
            ->assertSee('Facturación: Facturado al día')
            ->assertSee('Recibida y facturada')
            ->assertSee('Factura conciliada')
            ->assertDontSee('Registrar factura de lo recibido');

        $this->get(route('notas-ingreso.show', $nota))
            ->assertOk()
            ->assertSee('Facturación vinculada a esta recepción')
            ->assertSee('Facturada parcialmente')
            ->assertSee('La OC ya no tiene saldo facturable para estos productos')
            ->assertDontSee('Registrar factura de lo recibido');
    }

    public function test_recepcion_completa_sin_factura_sigue_pendiente_y_anulada_no_cuenta(): void
    {
        $orden = $this->crearOrden();
        $nota = $this->registrarRecepcion($orden, 10);
        $this->actingAs($this->almacen)->get(route('ordenes-compra.show', $orden))
            ->assertOk()->assertSee('Recibida completamente')
            ->assertSee('Facturación: Pendiente de facturar')
            ->assertSee('Registrar factura de lo recibido');

        $this->post(route('facturas-proveedor.store'), $this->datosFactura($orden, 'ANULADA-10', 10, 100, 18, 118))
            ->assertRedirect();
        $factura = FacturaProveedor::query()->where('numero', 'ANULADA-10')->firstOrFail();
        $factura->update(['estado' => 'ANULADA', 'anulado_por' => $this->almacen->id, 'anulado_en' => now()]);

        $this->get(route('notas-ingreso.show', $nota))
            ->assertOk()->assertSee('Pendiente de factura')->assertDontSee('ANULADA-10');
        $this->get(route('ordenes-compra.show', $orden))
            ->assertOk()->assertSee('Facturación: Pendiente de facturar');
    }

    public function test_pendientes_de_facturar_se_ven_en_listado_sin_conceder_permiso_de_registro(): void
    {
        $orden = $this->crearOrden();
        $this->actingAs($this->almacen)->get(route('facturas-proveedor.index'))
            ->assertOk()->assertSee('No hay recepciones pendientes de facturar');
        $nota = $this->registrarRecepcion($orden, 4);

        $this->get(route('facturas-proveedor.index'))
            ->assertOk()->assertSee('Pendientes de facturar')->assertSee($orden->codigo)
            ->assertSee('Registrar factura');
        $this->actingAs($this->contabilidad)->get(route('facturas-proveedor.index'))
            ->assertOk()->assertSee($orden->codigo)->assertSee('Ver OC')
            ->assertDontSee(route('facturas-proveedor.create', $orden));
        $this->get(route('ordenes-compra.show', $orden))
            ->assertOk()->assertDontSee('Registrar factura de lo recibido');
        $this->get(route('notas-ingreso.show', $nota))
            ->assertOk()->assertDontSee('Registrar factura de lo recibido');
    }

    public function test_factura_en_query_se_conserva_oculta_y_saldo_conciliado_no_se_ofrece(): void
    {
        $orden = $this->crearOrden();
        $primera = $this->registrarRecepcion($orden, 5);
        $this->actingAs($this->almacen)
            ->post(route('facturas-proveedor.store'), $this->datosFactura($orden, 'QUERY-5', 5, 50, 9, 59))
            ->assertRedirect();
        $factura = FacturaProveedor::query()->where('numero', 'QUERY-5')->firstOrFail();

        $this->get(route('notas-ingreso.create', [
            'motivo_ingreso' => 'COMPRA', 'orden_compra_id' => $orden->id,
            'factura_proveedor_id' => $factura->id,
        ]))->assertOk()
            ->assertSee('name="factura_proveedor_id"', false)
            ->assertDontSee('data-invoice-selector', false)
            ->assertDontSee('name="detalles[0][cantidad]"', false);
        $this->assertNull($primera->fresh()->factura_proveedor_id);
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
        ?float $tipoCambio = null,
        bool $conRecepcion = true
    ): array {
        $detalle = $orden->detalles->firstOrFail();
        $ingresoDetalle = $conRecepcion
            ? $this->asegurarRecepcion($orden)->detalles->firstOrFail()
            : null;

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
                'nota_ingreso_detalle_id' => $ingresoDetalle?->id,
                'producto_id' => $detalle->producto_id,
                'cantidad' => $cantidad,
                'costo_unitario_total' => 11.8,
                'afecto_igv' => 1,
            ]],
        ];
    }

    private function asegurarRecepcion(OrdenCompra $orden): NotaIngreso
    {
        $existente = NotaIngreso::query()
            ->with('detalles')
            ->where('orden_compra_id', $orden->id)
            ->where('motivo_ingreso', 'COMPRA')
            ->where('estado', 'CONFIRMADA')
            ->first();
        if ($existente) {
            return $existente;
        }

        return $this->registrarRecepcion($orden, 10);
    }

    private function registrarRecepcion(OrdenCompra $orden, float $cantidad, ?int $facturaId = null): NotaIngreso
    {
        $detalle = $orden->detalles->firstOrFail();

        return app(RegistrarNotaIngresoService::class)->registrarYConfirmar([
            'motivo_ingreso' => 'COMPRA',
            'orden_compra_id' => $orden->id,
            'factura_proveedor_id' => $facturaId,
            'fecha_ingreso' => now()->toDateString(),
            'numero_guia_remision' => 'T001-174',
            'detalles' => [[
                'orden_compra_detalle_id' => $detalle->id,
                'producto_id' => $detalle->producto_id,
                'repisa_id' => $this->repisa->id,
                'cantidad' => $cantidad,
                'costo_unitario' => 0,
            ]],
        ], $this->almacen);
    }

    private function facturaHeredada(OrdenCompra $orden, float $cantidad): FacturaProveedor
    {
        $factura = FacturaProveedor::query()->create([
            'orden_compra_id' => $orden->id,
            'proveedor_id' => $this->proveedor->id,
            'tipo_documento' => 'FACTURA',
            'serie' => 'F001',
            'numero' => 'LEG-'.$orden->id,
            'fecha_emision' => now()->toDateString(),
            'moneda' => $orden->moneda,
            'subtotal' => $cantidad * 10,
            'impuesto' => $cantidad * 1.8,
            'total' => $cantidad * 11.8,
            'estado' => 'REGISTRADA',
            'registrado_por' => $this->almacen->id,
        ]);
        $factura->detalles()->create([
            'orden_compra_detalle_id' => $orden->detalles->firstOrFail()->id,
            'producto_id' => $this->producto->id,
            'descripcion' => $this->producto->descripcion,
            'cantidad' => $cantidad,
            'precio_unitario' => 10,
            'igv_porcentaje' => 18,
            'subtotal' => $cantidad * 10,
            'impuesto' => $cantidad * 1.8,
            'total' => $cantidad * 11.8,
        ]);

        return $factura;
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
