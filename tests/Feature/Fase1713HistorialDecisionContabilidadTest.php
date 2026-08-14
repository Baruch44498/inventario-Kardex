<?php

namespace Tests\Feature;

use App\Models\Cotizacion;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\RequisicionDetalle;
use App\Models\Role;
use App\Models\SolicitudCompra;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1713HistorialDecisionContabilidadTest extends TestCase
{
    use RefreshDatabase;

    private User $logistica;
    private User $contabilidad;
    private Producto $producto;
    private Producto $adicional;
    private Requisicion $requisicion;
    private RequisicionDetalle $requerido;
    private Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistica = $this->crearUsuario('COMERCIAL_LOGISTICA', 'logistica_1713');
        $this->contabilidad = $this->crearUsuario('CONTABILIDAD', 'contabilidad_1713');

        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );

        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'HIS-1713-A',
            'descripcion' => 'Producto principal para decisión de compra',
            'estado' => true,
        ]);
        $this->adicional = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'HIS-1713-ADD',
            'descripcion' => 'Producto adicional no solicitado',
            'estado' => true,
        ]);

        $this->requisicion = Requisicion::query()->create([
            'codigo' => 'REQ-HIS-1713',
            'fecha_solicitud' => now()->toDateString(),
            'origen' => 'REPOSICION',
            'descripcion' => 'Necesidad usada para el puente contable.',
            'prioridad' => 'ALTA',
            'estado' => 'COTIZANDO',
            'solicitado_por' => $this->logistica->id,
        ]);
        $this->requerido = $this->requisicion->detalles()->create([
            'producto_id' => $this->producto->id,
            'cantidad_solicitada' => 5,
            'cantidad_sugerida' => 5,
            'cantidad_atendida' => 0,
        ]);

        $this->proveedor = Proveedor::query()->create([
            'ruc' => '20617130001',
            'razon_social' => 'Proveedor Historial 1713 SAC',
            'nombre_comercial' => 'Proveedor Historial 1713',
            'estado' => true,
        ]);
    }

    public function test_historial_filtra_por_requerimiento_y_distingue_decision_comercial(): void
    {
        $cotizacion = $this->crearCotizacion();

        $this->actingAs($this->logistica)
            ->patch(route('cotizaciones-proveedor.clasificar', $cotizacion), [
                'estado' => 'NO_UTILIZADA',
                'motivo_evaluacion' => 'Se eligió otra oferta con menor plazo.',
            ])
            ->assertRedirect();

        $respuesta = $this->actingAs($this->logistica)->get(route('historial-precios.index', [
            'producto_id' => $this->producto->id,
            'requisicion_id' => $this->requisicion->id,
            'estado' => 'NO_UTILIZADA',
        ]));

        $respuesta
            ->assertOk()
            ->assertSee('No utilizada')
            ->assertSee($this->requisicion->codigo)
            ->assertSee($this->proveedor->nombreVisible());

        $this->assertNotEmpty($respuesta->viewData('comparacion'));
        $this->assertSame('NO_UTILIZADA', $cotizacion->fresh()->estado);
    }

    public function test_invalidada_es_auditable_pero_no_participa_en_comparaciones(): void
    {
        $cotizacion = $this->crearCotizacion();

        $this->actingAs($this->logistica)
            ->patch(route('cotizaciones-proveedor.anular', $cotizacion), [
                'motivo_anulacion' => 'Documento duplicado durante el registro.',
            ])
            ->assertRedirect();

        $porDefecto = $this->actingAs($this->logistica)
            ->get(route('historial-precios.index', ['producto_id' => $this->producto->id]));

        $this->assertCount(0, $porDefecto->viewData('historial'));

        $auditada = $this->actingAs($this->logistica)->get(route('historial-precios.index', [
            'producto_id' => $this->producto->id,
            'estado' => 'ANULADA',
        ]));

        $this->assertCount(1, $auditada->viewData('historial'));
        $this->assertEmpty($auditada->viewData('comparacion'));
        $auditada->assertSee('Invalidada');
    }

    public function test_archivada_no_puede_aprobarse_para_compra_y_puede_reactivarse(): void
    {
        $cotizacion = $this->crearCotizacion();
        $detalleId = $cotizacion->detalles()->firstOrFail()->id;

        $this->actingAs($this->logistica)
            ->patch(route('cotizaciones-proveedor.clasificar', $cotizacion), [
                'estado' => 'NO_REQUERIDA',
                'motivo_evaluacion' => 'La necesidad de compra fue cancelada.',
            ]);

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-proveedor.aprobar-y-generar-orden', $cotizacion), $this->datosAprobacion([$detalleId]))
            ->assertSessionHasErrors('cotizacion');

        $this->assertDatabaseCount('solicitudes_compra', 0);

        $this->actingAs($this->logistica)
            ->patch(route('cotizaciones-proveedor.reactivar', $cotizacion))
            ->assertRedirect();

        $this->assertSame('REGISTRADA', $cotizacion->fresh()->estado);
    }

    public function test_una_accion_aprueba_compra_genera_orden_y_excluye_adicionales(): void
    {
        $cotizacion = $this->crearCotizacion(conAdicional: true);
        $principal = $cotizacion->detalles()->where('producto_id', $this->producto->id)->firstOrFail();
        $adicional = $cotizacion->detalles()->where('producto_id', $this->adicional->id)->firstOrFail();

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-proveedor.aprobar-y-generar-orden', $cotizacion), $this->datosAprobacion([
                $principal->id,
            ], [
                'descripcion' => 'Proveedor elegido por disponibilidad inmediata.',
            ]))
            ->assertRedirect();

        $solicitud = SolicitudCompra::query()->with('detalles')->firstOrFail();
        $orden = OrdenCompra::query()->with('detalles')->firstOrFail();
        $this->assertSame('CONVERTIDA', $solicitud->estado);
        $this->assertSame('SELECCIONADA', $cotizacion->fresh()->estado);
        $this->assertSame($this->logistica->id, $solicitud->aprobado_por);
        $this->assertSame($solicitud->id, $orden->solicitud_compra_id);
        $this->assertSame('APROBADA', $orden->estado);
        $this->assertCount(1, $solicitud->detalles);
        $this->assertCount(1, $orden->detalles);
        $this->assertSame(100.0, (float) $solicitud->detalles->sum('subtotal'));
        $this->assertSame(100.0, (float) $solicitud->total_lineas);
        $this->assertSame(0.0, (float) $solicitud->ajuste_redondeo);
        $this->assertSame(100.0, (float) $solicitud->total_seleccionado);
        $this->assertDatabaseHas('solicitud_compra_detalles', [
            'solicitud_compra_id' => $solicitud->id,
            'cotizacion_detalle_id' => $principal->id,
        ]);
        $this->assertDatabaseMissing('solicitud_compra_detalles', [
            'cotizacion_detalle_id' => $adicional->id,
        ]);
    }

    public function test_orden_generada_conserva_ajuste_documental_en_total_seleccionado(): void
    {
        $cotizacion = $this->crearCotizacion(ajusteRedondeo: -0.01);
        $detalle = $cotizacion->detalles()->firstOrFail();

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-proveedor.aprobar-y-generar-orden', $cotizacion), $this->datosAprobacion([$detalle->id]))
            ->assertRedirect();

        $solicitud = SolicitudCompra::query()->firstOrFail();
        $orden = OrdenCompra::query()->firstOrFail();
        $this->assertSame(100.0, (float) $solicitud->total_lineas);
        $this->assertSame(-0.01, (float) $solicitud->ajuste_redondeo);
        $this->assertSame(99.99, (float) $solicitud->total_seleccionado);
        $this->assertSame(-0.01, (float) $orden->ajuste_redondeo);
        $this->assertSame(99.99, (float) $orden->total);
    }

    public function test_contabilidad_solo_consulta_y_no_puede_aprobar_compras(): void
    {
        $cotizacion = $this->crearCotizacion();
        $detalle = $cotizacion->detalles()->firstOrFail();

        $this->actingAs($this->contabilidad)
            ->post(route('cotizaciones-proveedor.aprobar-y-generar-orden', $cotizacion), $this->datosAprobacion([$detalle->id]))
            ->assertForbidden();

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-proveedor.aprobar-y-generar-orden', $cotizacion), $this->datosAprobacion([$detalle->id]))
            ->assertRedirect();

        $solicitud = SolicitudCompra::query()->firstOrFail();
        $this->actingAs($this->contabilidad)
            ->get(route('solicitudes-compra.show', $solicitud))
            ->assertOk()
            ->assertSee('Contabilidad puede consultarlo, pero no aprobar ni rechazar la compra')
            ->assertDontSee('Aprobar para compra')
            ->assertDontSee('Rechazar');
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

    private function crearCotizacion(
        bool $conAdicional = false,
        float $ajusteRedondeo = 0.0
    ): Cotizacion {
        $secuencia = Cotizacion::query()->count() + 1;
        $cotizacion = Cotizacion::query()->create([
            'requisicion_id' => $this->requisicion->id,
            'proveedor_id' => $this->proveedor->id,
            'codigo' => 'CP-HIS-' . str_pad((string) $secuencia, 3, '0', STR_PAD_LEFT),
            'numero_documento' => 'PROV-HIS-' . $secuencia,
            'fecha_cotizacion' => now()->toDateString(),
            'moneda' => 'PEN',
            'descuento_global_modo' => 'SIN_DESCUENTO',
            'subtotal' => $conAdicional ? 140 : 100,
            'descuento_global_monto' => 0,
            'impuesto' => 18,
            'total' => ($conAdicional ? 140 : 100) + $ajusteRedondeo,
            'total_calculado' => $conAdicional ? 140 : 100,
            'ajuste_redondeo' => $ajusteRedondeo,
            'estado' => 'REGISTRADA',
            'registrado_por' => $this->logistica->id,
        ]);

        $cotizacion->detalles()->create([
            'requisicion_detalle_id' => $this->requerido->id,
            'tipo_vinculacion' => 'SOLICITADO',
            'vinculacion_origen' => 'MANUAL',
            'producto_id' => $this->producto->id,
            'cantidad' => 5,
            'precio_unitario' => 20,
            'descuento_porcentaje' => 0,
            'descuento_modo' => 'SIN_DESCUENTO',
            'igv_modo' => 'INCLUIDO',
            'igv_porcentaje' => 18,
            'subtotal' => 100,
            'impuesto' => 0,
            'total' => 100,
        ]);

        if ($conAdicional) {
            $cotizacion->detalles()->create([
                'requisicion_detalle_id' => null,
                'tipo_vinculacion' => 'ADICIONAL',
                'vinculacion_origen' => 'MANUAL',
                'producto_id' => $this->adicional->id,
                'cantidad' => 2,
                'precio_unitario' => 20,
                'descuento_porcentaje' => 0,
                'descuento_modo' => 'SIN_DESCUENTO',
                'igv_modo' => 'INCLUIDO',
                'igv_porcentaje' => 18,
                'subtotal' => 40,
                'impuesto' => 0,
                'total' => 40,
            ]);
        }

        return $cotizacion;
    }

    /**
     * @param array<int, int> $detalleIds
     * @param array<string, mixed> $adicionales
     * @return array<string, mixed>
     */
    private function datosAprobacion(array $detalleIds, array $adicionales = []): array
    {
        return array_merge([
            'detalle_ids' => $detalleIds,
            'fecha_emision' => now()->toDateString(),
            'fecha_entrega_requerida' => now()->addDays(7)->toDateString(),
            'condiciones_pago' => 'Crédito a 15 días.',
            'condiciones_entrega' => 'Entrega en almacén principal.',
        ], $adicionales);
    }
}
