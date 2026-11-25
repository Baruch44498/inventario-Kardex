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
use App\Services\Compras\CompararCotizacionesRequerimientoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1914RecuperacionTrasAnularOrdenCompraTest extends TestCase
{
    use RefreshDatabase;

    private User $logistica;
    private UnidadMedida $unidad;
    private int $secuencia = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistica = $this->crearUsuario('COMERCIAL_LOGISTICA', 'logistica_1914');
        $this->unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
    }

    public function test_anular_la_unica_oc_reabre_el_requerimiento_para_elegir_otro_proveedor(): void
    {
        [$requerimiento, $lineas] = $this->crearRequerimiento('REQ-1914-REABRIR', 1);
        $orden = $this->crearCompra($requerimiento, $lineas[0]);
        $ofertaNueva = $this->crearOfertaDisponible($requerimiento, $lineas[0]);

        $this->actingAs($this->logistica)
            ->patch(route('ordenes-compra.anular', $orden), [
                'motivo_anulacion' => 'El proveedor informó que no podrá realizar la entrega.',
            ])
            ->assertRedirect()
            ->assertSessionHas(
                'success',
                fn (string $mensaje): bool => str_contains($mensaje, 'volvió a Cotizando')
            );

        $requerimiento->refresh();
        $this->assertSame('ANULADA', $orden->fresh()->estado);
        $this->assertSame('COTIZANDO', $requerimiento->estado);
        $this->assertNull($requerimiento->atendido_por);
        $this->assertNull($requerimiento->atendido_en);
        $this->assertDatabaseHas('historial_requerimientos_compra', [
            'requisicion_id' => $requerimiento->id,
            'estado_anterior' => 'ATENDIDA',
            'estado_nuevo' => 'COTIZANDO',
            'registrado_por' => $this->logistica->id,
        ]);

        $comparativo = app(CompararCotizacionesRequerimientoService::class)
            ->construir($requerimiento);
        $linea = $comparativo['lineas']->first();
        $this->assertNull($linea['compra']);
        $this->assertTrue(
            $linea['ofertas']->firstWhere(
                'cotizacion_detalle_id',
                $ofertaNueva->id
            )['seleccionable']
        );
        $this->assertSame(1, $comparativo['lineas_cotizables']);

        $this->actingAs($this->logistica)
            ->get(route('requerimientos-compra.show', $requerimiento))
            ->assertOk()
            ->assertSee('Cotizando')
            ->assertSee('Comparar ofertas');
    }

    public function test_en_una_compra_dividida_reabre_si_una_linea_pierde_su_oc(): void
    {
        [$requerimiento, $lineas] = $this->crearRequerimiento('REQ-1914-DIVIDIDA', 2);
        $ordenAnulada = $this->crearCompra($requerimiento, $lineas[0]);
        $ordenVigente = $this->crearCompra($requerimiento, $lineas[1]);

        $this->actingAs($this->logistica)
            ->patch(route('ordenes-compra.anular', $ordenAnulada), [
                'motivo_anulacion' => 'La primera parte de la compra debe cotizarse nuevamente.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $comparativo = app(CompararCotizacionesRequerimientoService::class)
            ->construir($requerimiento->fresh());
        $resultadoPorLinea = $comparativo['lineas']->keyBy('requisicion_detalle_id');

        $this->assertSame('COTIZANDO', $requerimiento->fresh()->estado);
        $this->assertNull($resultadoPorLinea[$lineas[0]->id]['compra']);
        $this->assertSame(
            $ordenVigente->id,
            (int) $resultadoPorLinea[$lineas[1]->id]['compra']->orden_id
        );
    }

    public function test_no_reabre_si_otra_oc_vigente_aun_cubre_la_misma_linea(): void
    {
        [$requerimiento, $lineas] = $this->crearRequerimiento('REQ-1914-CUBIERTA', 1);
        $ordenAnulada = $this->crearCompra($requerimiento, $lineas[0]);
        $ordenVigente = $this->crearCompra($requerimiento, $lineas[0]);

        $this->actingAs($this->logistica)
            ->patch(route('ordenes-compra.anular', $ordenAnulada), [
                'motivo_anulacion' => 'Se conserva la segunda orden vigente para esta línea.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('ANULADA', $ordenAnulada->fresh()->estado);
        $this->assertSame('APROBADA', $ordenVigente->fresh()->estado);
        $this->assertSame('ATENDIDA', $requerimiento->fresh()->estado);
        $this->assertDatabaseMissing('historial_requerimientos_compra', [
            'requisicion_id' => $requerimiento->id,
            'estado_anterior' => 'ATENDIDA',
            'estado_nuevo' => 'COTIZANDO',
        ]);
    }

    public function test_compra_directa_se_anula_sin_crear_ni_modificar_requerimientos(): void
    {
        $producto = $this->crearProducto('MAT-1914-DIRECTA');
        $orden = $this->crearCompra(null, null, $producto);

        $this->actingAs($this->logistica)
            ->patch(route('ordenes-compra.anular', $orden), [
                'motivo_anulacion' => 'La compra directa fue registrada con datos incorrectos.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('ANULADA', $orden->fresh()->estado);
        $this->assertDatabaseCount('requisiciones', 0);
        $this->assertDatabaseCount('historial_requerimientos_compra', 0);
    }

    public function test_orden_con_estado_posterior_no_puede_anularse_ni_reabrir_el_requerimiento(): void
    {
        [$requerimiento, $lineas] = $this->crearRequerimiento('REQ-1914-RECIBIDA', 1);
        $orden = $this->crearCompra($requerimiento, $lineas[0]);
        $orden->update(['estado' => 'RECIBIDA']);

        $this->actingAs($this->logistica)
            ->patch(route('ordenes-compra.anular', $orden), [
                'motivo_anulacion' => 'Intento posterior a la recepción registrada.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('RECIBIDA', $orden->fresh()->estado);
        $this->assertSame('ATENDIDA', $requerimiento->fresh()->estado);
    }

    /** @return array{Requisicion, array<int, RequisicionDetalle>} */
    private function crearRequerimiento(string $codigo, int $cantidadLineas): array
    {
        $requerimiento = Requisicion::query()->create([
            'codigo' => $codigo,
            'fecha_solicitud' => now()->toDateString(),
            'origen' => 'REPOSICION',
            'descripcion' => 'Requerimiento para recuperación después de anular una OC.',
            'prioridad' => 'ALTA',
            'estado' => 'ATENDIDA',
            'estado_abastecimiento' => 'PENDIENTE',
            'solicitado_por' => $this->logistica->id,
            'enviado_por' => $this->logistica->id,
            'enviado_en' => now(),
            'recibido_por' => $this->logistica->id,
            'recibido_en' => now(),
            'atendido_por' => $this->logistica->id,
            'atendido_en' => now(),
        ]);

        $lineas = [];
        for ($indice = 1; $indice <= $cantidadLineas; $indice++) {
            $producto = $this->crearProducto("MAT-{$codigo}-{$indice}");
            $lineas[] = $requerimiento->detalles()->create([
                'producto_id' => $producto->id,
                'cantidad_solicitada' => 5,
                'cantidad_sugerida' => 5,
                'cantidad_atendida' => 0,
                'stock_fisico_snapshot' => 0,
                'reservado_snapshot' => 0,
                'disponible_snapshot' => 0,
                'stock_minimo_snapshot' => 5,
            ]);
        }

        return [$requerimiento, $lineas];
    }

    private function crearCompra(
        ?Requisicion $requerimiento,
        ?RequisicionDetalle $linea,
        ?Producto $productoDirecto = null
    ): OrdenCompra {
        $this->secuencia++;
        $sufijo = str_pad((string) $this->secuencia, 3, '0', STR_PAD_LEFT);
        $producto = $linea?->producto ?? $productoDirecto;
        $proveedor = Proveedor::query()->create([
            'ruc' => '2061914'.str_pad((string) $this->secuencia, 4, '0', STR_PAD_LEFT),
            'razon_social' => "Proveedor Recuperación {$sufijo} S.A.C.",
            'estado' => true,
        ]);
        $cotizacion = Cotizacion::query()->create([
            'requisicion_id' => $requerimiento?->id,
            'proveedor_id' => $proveedor->id,
            'codigo' => "CP-1914-{$sufijo}",
            'numero_documento' => "DOC-1914-{$sufijo}",
            'fecha_cotizacion' => now()->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => 1,
            'descuento_global_modo' => 'SIN_DESCUENTO',
            'subtotal' => 50,
            'descuento_global_monto' => 0,
            'impuesto' => 9,
            'total_calculado' => 59,
            'ajuste_redondeo' => 0,
            'total' => 59,
            'estado' => 'SELECCIONADA',
            'registrado_por' => $this->logistica->id,
        ]);
        $cotizacionDetalle = $cotizacion->detalles()->create([
            'requisicion_detalle_id' => $linea?->id,
            'tipo_vinculacion' => $linea ? 'SOLICITADO' : 'ADICIONAL',
            'vinculacion_origen' => 'MANUAL',
            'producto_id' => $producto->id,
            'cantidad' => 5,
            'precio_unitario' => 11.8,
            'descuento_porcentaje' => 0,
            'descuento_modo' => 'SIN_DESCUENTO',
            'igv_modo' => 'INCLUIDO',
            'igv_porcentaje' => 18,
            'subtotal' => 50,
            'impuesto' => 9,
            'total' => 59,
        ]);
        $solicitud = SolicitudCompra::query()->create([
            'cotizacion_id' => $cotizacion->id,
            'codigo' => "SC-1914-{$sufijo}",
            'fecha_solicitud' => now()->toDateString(),
            'origen' => $requerimiento ? 'REQUERIMIENTO' : 'COMPRA_DIRECTA',
            'justificacion_origen' => $requerimiento ? null : 'Compra directa controlada para la prueba.',
            'total_lineas' => 59,
            'ajuste_redondeo' => 0,
            'total_seleccionado' => 59,
            'estado' => 'CONVERTIDA',
            'solicitado_por' => $this->logistica->id,
            'aprobado_por' => $this->logistica->id,
            'aprobado_en' => now(),
        ]);
        $solicitudDetalle = $solicitud->detalles()->create([
            'cotizacion_detalle_id' => $cotizacionDetalle->id,
            'producto_id' => $producto->id,
            'cantidad' => 5,
            'precio_unitario' => 11.8,
            'descuento_porcentaje' => 0,
            'subtotal' => 59,
        ]);
        $orden = OrdenCompra::query()->create([
            'solicitud_compra_id' => $solicitud->id,
            'proveedor_id' => $proveedor->id,
            'codigo' => "OC-1914-{$sufijo}",
            'origen' => $requerimiento ? 'REQUERIMIENTO' : 'COMPRA_DIRECTA',
            'justificacion_origen' => $requerimiento ? null : 'Compra directa controlada para la prueba.',
            'fecha_emision' => now()->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => 1,
            'subtotal' => 50,
            'impuesto' => 9,
            'ajuste_redondeo' => 0,
            'total' => 59,
            'estado' => 'APROBADA',
            'emitido_por' => $this->logistica->id,
            'aprobado_por' => $this->logistica->id,
            'aprobado_en' => now(),
        ]);
        $orden->detalles()->create([
            'solicitud_compra_detalle_id' => $solicitudDetalle->id,
            'producto_id' => $producto->id,
            'cantidad_ordenada' => 5,
            'cantidad_recibida' => 0,
            'precio_unitario' => 11.8,
            'descuento_porcentaje' => 0,
            'subtotal' => 59,
        ]);

        return $orden;
    }

    private function crearOfertaDisponible(
        Requisicion $requerimiento,
        RequisicionDetalle $linea
    ) {
        $this->secuencia++;
        $sufijo = str_pad((string) $this->secuencia, 3, '0', STR_PAD_LEFT);
        $proveedor = Proveedor::query()->create([
            'ruc' => '2061914'.str_pad((string) $this->secuencia, 4, '0', STR_PAD_LEFT),
            'razon_social' => "Proveedor Alternativo {$sufijo} S.A.C.",
            'estado' => true,
        ]);
        $cotizacion = Cotizacion::query()->create([
            'requisicion_id' => $requerimiento->id,
            'proveedor_id' => $proveedor->id,
            'codigo' => "CP-1914-ALT-{$sufijo}",
            'numero_documento' => "DOC-1914-ALT-{$sufijo}",
            'fecha_cotizacion' => now()->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => 1,
            'descuento_global_modo' => 'SIN_DESCUENTO',
            'subtotal' => 55,
            'descuento_global_monto' => 0,
            'impuesto' => 9.9,
            'total_calculado' => 64.9,
            'ajuste_redondeo' => 0,
            'total' => 64.9,
            'estado' => 'REGISTRADA',
            'registrado_por' => $this->logistica->id,
        ]);

        return $cotizacion->detalles()->create([
            'requisicion_detalle_id' => $linea->id,
            'tipo_vinculacion' => 'SOLICITADO',
            'vinculacion_origen' => 'MANUAL',
            'producto_id' => $linea->producto_id,
            'cantidad' => 5,
            'precio_unitario' => 12.98,
            'descuento_porcentaje' => 0,
            'descuento_modo' => 'SIN_DESCUENTO',
            'igv_modo' => 'INCLUIDO',
            'igv_porcentaje' => 18,
            'subtotal' => 55,
            'impuesto' => 9.9,
            'total' => 64.9,
        ]);
    }

    private function crearProducto(string $codigo): Producto
    {
        return Producto::query()->create([
            'unidad_medida_id' => $this->unidad->id,
            'codigo' => $codigo,
            'descripcion' => 'Producto de recuperación '.$codigo,
            'estado' => true,
        ]);
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
