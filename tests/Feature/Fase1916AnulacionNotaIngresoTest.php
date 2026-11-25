<?php

namespace Tests\Feature;

use App\Models\Cotizacion;
use App\Models\FacturaProveedor;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
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
use Tests\TestCase;

class Fase1916AnulacionNotaIngresoTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private User $logistica;
    private Proveedor $proveedor;
    private Repisa $repisa;
    private UnidadMedida $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = $this->crearUsuario('ALMACEN', 'almacen_1916');
        $this->logistica = $this->crearUsuario('COMERCIAL_LOGISTICA', 'logistica_1916');
        $this->unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $this->proveedor = Proveedor::query()->create([
            'ruc' => '20619160001',
            'razon_social' => 'Proveedor Anulación 1916 S.A.C.',
            'estado' => true,
        ]);
        $this->repisa = Repisa::query()->create([
            'codigo' => 'R-1916',
            'descripcion' => 'Repisa recepción 1916',
            'estado' => true,
        ]);
    }

    public function test_anula_recepcion_total_y_revierte_stock_oc_y_requerimiento(): void
    {
        [$requerimiento, $linea, $orden] = $this->crearFlujo('TOTAL', 10);
        $nota = $this->recibir($orden, 10, 'GR-1916-01');

        $this->assertSame('RECIBIDA', $orden->fresh()->estado);
        $this->assertSame(10.0, (float) $linea->fresh()->cantidad_atendida);
        $this->assertSame(10.0, (float) Inventario::query()->firstOrFail()->stock_actual);

        $this->actingAs($this->almacen)
            ->get(route('notas-ingreso.show', $nota))
            ->assertOk()
            ->assertSee('Anular recepción');

        $this->actingAs($this->almacen)
            ->patch(route('notas-ingreso.anular', $nota), [
                'motivo_anulacion' => 'La cantidad fue registrada por error.',
            ])
            ->assertRedirect(route('notas-ingreso.show', $nota));

        $this->assertDatabaseHas('notas_ingreso', [
            'id' => $nota->id,
            'estado' => 'ANULADA',
            'anulado_por' => $this->almacen->id,
            'motivo_anulacion' => 'La cantidad fue registrada por error.',
        ]);
        $this->assertSame('APROBADA', $orden->fresh()->estado);
        $this->assertSame(0.0, (float) $orden->detalles()->firstOrFail()->cantidad_recibida);
        $this->assertSame(0.0, (float) $linea->fresh()->cantidad_atendida);
        $this->assertSame(0.0, (float) Inventario::query()->firstOrFail()->stock_actual);
        $this->assertDatabaseHas('movimientos_inventario', [
            'tipo_movimiento' => 'SALIDA',
            'motivo' => 'ANULACION_COMPRA',
            'origen_tipo' => 'ANULACION_NOTA_INGRESO',
            'origen_id' => $nota->id,
        ]);

        $this->actingAs($this->almacen)
            ->get(route('notas-ingreso.show', $nota))
            ->assertOk()
            ->assertSee('Recepción anulada')
            ->assertSee('La cantidad fue registrada por error.')
            ->assertDontSee('Anular recepción');

        $this->assertNotNull($requerimiento->fresh());
    }

    public function test_anula_solo_la_ultima_recepcion_y_conserva_la_anterior(): void
    {
        [, $linea, $orden] = $this->crearFlujo('PARCIAL', 10);
        $primera = $this->recibir($orden, 4, 'GR-1916-02');
        $segunda = $this->recibir($orden->fresh(), 3, 'GR-1916-03');

        $this->assertSame(7.0, (float) Inventario::query()->firstOrFail()->stock_actual);

        $this->actingAs($this->almacen)
            ->patch(route('notas-ingreso.anular', $segunda), [
                'motivo_anulacion' => 'La segunda guía fue duplicada.',
            ])
            ->assertRedirect(route('notas-ingreso.show', $segunda));

        $this->assertSame('CONFIRMADA', $primera->fresh()->estado);
        $this->assertSame('ANULADA', $segunda->fresh()->estado);
        $this->assertSame('PARCIALMENTE_RECIBIDA', $orden->fresh()->estado);
        $this->assertSame(4.0, (float) $orden->detalles()->firstOrFail()->cantidad_recibida);
        $this->assertSame(4.0, (float) $linea->fresh()->cantidad_atendida);
        $this->assertSame(4.0, (float) Inventario::query()->firstOrFail()->stock_actual);
    }

    public function test_bloquea_anulacion_si_el_producto_tiene_movimientos_posteriores(): void
    {
        [, $linea, $orden] = $this->crearFlujo('MOVIMIENTO', 10);
        $nota = $this->recibir($orden, 5, 'GR-1916-04');
        $inventario = Inventario::query()->firstOrFail();

        MovimientoInventario::query()->create([
            'inventario_id' => $inventario->id,
            'producto_id' => $inventario->producto_id,
            'repisa_id' => $inventario->repisa_id,
            'tipo_movimiento' => 'SALIDA',
            'motivo' => 'USO_INTERNO',
            'origen_tipo' => 'PRUEBA_MOVIMIENTO_POSTERIOR',
            'origen_id' => 1916,
            'cantidad' => 1,
            'stock_anterior' => 5,
            'stock_posterior' => 4,
            'costo_unitario' => $inventario->costo_promedio_soles,
            'costo_promedio_anterior' => $inventario->costo_promedio_soles,
            'costo_promedio_nuevo' => $inventario->costo_promedio_soles,
            'fecha_movimiento' => now(),
            'registrado_por' => $this->almacen->id,
        ]);
        $inventario->update(['stock_actual' => 4]);

        $this->actingAs($this->almacen)
            ->from(route('notas-ingreso.show', $nota))
            ->patch(route('notas-ingreso.anular', $nota), [
                'motivo_anulacion' => 'Se intentó corregir la recepción.',
            ])
            ->assertRedirect(route('notas-ingreso.show', $nota))
            ->assertSessionHasErrors('estado');

        $this->assertSame('CONFIRMADA', $nota->fresh()->estado);
        $this->assertSame('PARCIALMENTE_RECIBIDA', $orden->fresh()->estado);
        $this->assertSame(5.0, (float) $orden->detalles()->firstOrFail()->cantidad_recibida);
        $this->assertSame(5.0, (float) $linea->fresh()->cantidad_atendida);
        $this->assertSame(4.0, (float) $inventario->fresh()->stock_actual);
        $this->assertDatabaseMissing('movimientos_inventario', [
            'motivo' => 'ANULACION_COMPRA',
            'origen_id' => $nota->id,
        ]);
    }

    public function test_valida_motivo_y_restringe_la_accion_a_almacen(): void
    {
        [, , $orden] = $this->crearFlujo('PERMISO', 10);
        $nota = $this->recibir($orden, 2, 'GR-1916-05');

        $this->actingAs($this->almacen)
            ->from(route('notas-ingreso.show', $nota))
            ->patch(route('notas-ingreso.anular', $nota), [
                'motivo_anulacion' => 'no',
            ])
            ->assertSessionHasErrors('motivo_anulacion');

        $this->actingAs($this->logistica)
            ->patch(route('notas-ingreso.anular', $nota), [
                'motivo_anulacion' => 'Intento sin permiso de Almacén.',
            ])
            ->assertForbidden();

        $this->assertSame('CONFIRMADA', $nota->fresh()->estado);
    }

    public function test_bloquea_recepcion_conciliada_con_factura_activa(): void
    {
        [, , $orden] = $this->crearFlujo('FACTURA', 10);
        $nota = $this->recibir($orden, 5, 'GR-1916-06');
        $notaDetalle = $nota->detalles()->firstOrFail();
        $ordenDetalle = $orden->detalles()->firstOrFail();
        $factura = FacturaProveedor::query()->create([
            'orden_compra_id' => $orden->id,
            'proveedor_id' => $this->proveedor->id,
            'tipo_documento' => 'FACTURA',
            'serie' => 'F1916',
            'numero' => '000001',
            'fecha_emision' => now()->toDateString(),
            'moneda' => 'PEN',
            'subtotal' => 50,
            'impuesto' => 9,
            'total' => 59,
            'ajuste_redondeo' => 0,
            'estado' => 'REGISTRADA',
            'registrado_por' => $this->logistica->id,
        ]);
        $factura->detalles()->create([
            'orden_compra_detalle_id' => $ordenDetalle->id,
            'nota_ingreso_detalle_id' => $notaDetalle->id,
            'producto_id' => $ordenDetalle->producto_id,
            'descripcion' => 'Producto conciliado',
            'cantidad' => 5,
            'precio_unitario' => 10,
            'descuento_porcentaje' => 0,
            'igv_porcentaje' => 18,
            'subtotal' => 50,
            'impuesto' => 9,
            'total' => 59,
            'costo_provisional_soles' => 11.8,
            'ajuste_inventario_soles' => 0,
            'diferencia_contable_soles' => 0,
        ]);

        $this->actingAs($this->almacen)
            ->from(route('notas-ingreso.show', $nota))
            ->patch(route('notas-ingreso.anular', $nota), [
                'motivo_anulacion' => 'La recepción contiene un error.',
            ])
            ->assertRedirect(route('notas-ingreso.show', $nota))
            ->assertSessionHasErrors('estado');

        $this->assertSame('CONFIRMADA', $nota->fresh()->estado);
        $this->assertSame(5.0, (float) Inventario::query()->firstOrFail()->stock_actual);
        $this->assertDatabaseMissing('movimientos_inventario', [
            'motivo' => 'ANULACION_COMPRA',
            'origen_id' => $nota->id,
        ]);
    }

    private function recibir(
        OrdenCompra $orden,
        float $cantidad,
        string $guia
    ): NotaIngreso {
        $detalle = $orden->detalles()->firstOrFail();

        $this->actingAs($this->almacen)
            ->post(route('notas-ingreso.store'), [
                'motivo_ingreso' => 'COMPRA',
                'orden_compra_id' => $orden->id,
                'fecha_ingreso' => now()->toDateString(),
                'numero_guia_remision' => $guia,
                'detalles' => [[
                    'orden_compra_detalle_id' => $detalle->id,
                    'producto_id' => $detalle->producto_id,
                    'repisa_id' => $this->repisa->id,
                    'cantidad' => $cantidad,
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        return NotaIngreso::query()
            ->where('numero_guia_remision', $guia)
            ->firstOrFail();
    }

    private function crearFlujo(string $sufijo, float $cantidad): array
    {
        $producto = Producto::query()->create([
            'unidad_medida_id' => $this->unidad->id,
            'codigo' => "MAT-1916-{$sufijo}",
            'descripcion' => "Producto anulación {$sufijo}",
            'estado' => true,
        ]);
        $requerimiento = Requisicion::query()->create([
            'codigo' => "REQ-1916-{$sufijo}",
            'fecha_solicitud' => now()->toDateString(),
            'origen' => 'REPOSICION',
            'prioridad' => 'ALTA',
            'estado' => 'COTIZANDO',
            'solicitado_por' => $this->almacen->id,
            'enviado_por' => $this->almacen->id,
            'enviado_en' => now(),
            'recibido_por' => $this->logistica->id,
            'recibido_en' => now(),
        ]);
        $linea = $requerimiento->detalles()->create([
            'producto_id' => $producto->id,
            'cantidad_solicitada' => $cantidad,
            'cantidad_sugerida' => $cantidad,
            'cantidad_atendida' => 0,
            'stock_fisico_snapshot' => 0,
            'reservado_snapshot' => 0,
            'disponible_snapshot' => 0,
            'stock_minimo_snapshot' => $cantidad,
        ]);
        $cotizacion = Cotizacion::query()->create([
            'requisicion_id' => $requerimiento->id,
            'proveedor_id' => $this->proveedor->id,
            'codigo' => "CP-1916-{$sufijo}",
            'numero_documento' => "DOC-1916-{$sufijo}",
            'fecha_cotizacion' => now()->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => 1,
            'descuento_global_modo' => 'SIN_DESCUENTO',
            'subtotal' => $cantidad * 10,
            'descuento_global_monto' => 0,
            'impuesto' => $cantidad * 1.8,
            'total_calculado' => $cantidad * 11.8,
            'ajuste_redondeo' => 0,
            'total' => $cantidad * 11.8,
            'estado' => 'SELECCIONADA',
            'registrado_por' => $this->logistica->id,
        ]);
        $oferta = $cotizacion->detalles()->create([
            'requisicion_detalle_id' => $linea->id,
            'tipo_vinculacion' => 'SOLICITADO',
            'vinculacion_origen' => 'MANUAL',
            'producto_id' => $producto->id,
            'cantidad' => $cantidad,
            'precio_unitario' => 11.8,
            'descuento_porcentaje' => 0,
            'descuento_modo' => 'SIN_DESCUENTO',
            'igv_modo' => 'INCLUIDO',
            'igv_porcentaje' => 18,
            'subtotal' => $cantidad * 10,
            'impuesto' => $cantidad * 1.8,
            'total' => $cantidad * 11.8,
        ]);
        $solicitud = SolicitudCompra::query()->create([
            'cotizacion_id' => $cotizacion->id,
            'codigo' => "SC-1916-{$sufijo}",
            'fecha_solicitud' => now()->toDateString(),
            'total_lineas' => $cantidad * 11.8,
            'ajuste_redondeo' => 0,
            'total_seleccionado' => $cantidad * 11.8,
            'estado' => 'CONVERTIDA',
            'solicitado_por' => $this->logistica->id,
            'aprobado_por' => $this->logistica->id,
            'aprobado_en' => now(),
        ]);
        $solicitudDetalle = $solicitud->detalles()->create([
            'cotizacion_detalle_id' => $oferta->id,
            'producto_id' => $producto->id,
            'cantidad' => $cantidad,
            'precio_unitario' => 11.8,
            'descuento_porcentaje' => 0,
            'subtotal' => $cantidad * 11.8,
        ]);
        $orden = OrdenCompra::query()->create([
            'solicitud_compra_id' => $solicitud->id,
            'proveedor_id' => $this->proveedor->id,
            'codigo' => "OC-1916-{$sufijo}",
            'origen' => 'REQUERIMIENTO',
            'fecha_emision' => now()->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => 1,
            'subtotal' => $cantidad * 10,
            'impuesto' => $cantidad * 1.8,
            'ajuste_redondeo' => 0,
            'total' => $cantidad * 11.8,
            'estado' => 'APROBADA',
            'emitido_por' => $this->logistica->id,
            'aprobado_por' => $this->logistica->id,
            'aprobado_en' => now(),
        ]);
        $orden->detalles()->create([
            'solicitud_compra_detalle_id' => $solicitudDetalle->id,
            'producto_id' => $producto->id,
            'cantidad_ordenada' => $cantidad,
            'cantidad_recibida' => 0,
            'precio_unitario' => 11.8,
            'descuento_porcentaje' => 0,
            'subtotal' => $cantidad * 11.8,
        ]);

        return [$requerimiento, $linea, $orden->fresh('detalles')];
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
