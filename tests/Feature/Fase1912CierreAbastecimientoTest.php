<?php

namespace Tests\Feature;

use App\Models\AlertaStock;
use App\Models\Cotizacion;
use App\Models\Inventario;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Repisa;
use App\Models\Requisicion;
use App\Models\Role;
use App\Models\SolicitudCompra;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\Compras\SeguimientoAbastecimientoRequerimientoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1912CierreAbastecimientoTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private User $logistica;
    private UnidadMedida $unidad;
    private Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = $this->crearUsuario('ALMACEN', 'almacen_1912');
        $this->logistica = $this->crearUsuario('COMERCIAL_LOGISTICA', 'logistica_1912');
        $this->unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $this->proveedor = Proveedor::query()->create([
            'ruc' => '20619120001',
            'razon_social' => 'Proveedor Cierre 1912 S.A.C.',
            'nombre_comercial' => 'Proveedor Cierre 1912',
            'estado' => true,
        ]);
    }

    public function test_recepcion_parcial_y_total_actualizan_requerimiento_y_resuelven_la_alerta(): void
    {
        $requerimiento = $this->crearRequerimiento('REQ-1912-CIERRE');
        [$linea, $repisa, $alerta] = $this->crearLineaConAlerta(
            $requerimiento,
            'MAT-1912-A',
            10
        );
        $requerimiento->alertasStock()->attach($alerta->id);
        $cotizacion = $this->crearCotizacion($requerimiento, 'CP-1912-CIERRE');
        $oferta = $this->crearOferta($cotizacion, $linea, 10);
        $orden = $this->crearOrden($cotizacion, $oferta, 'OC-1912-CIERRE', 10);

        $this->registrarRecepcion($orden, $repisa, 4);

        $requerimiento->refresh();
        $this->assertSame('PARCIAL', $requerimiento->estado_abastecimiento);
        $this->assertNull($requerimiento->abastecido_en);
        $this->assertSame(4.0, (float) $linea->fresh()->cantidad_atendida);
        $this->assertSame('ATENDIDA', $alerta->fresh()->estado);

        $this->actingAs($this->almacen)
            ->get(route('requerimientos-compra.index', ['abastecimiento' => 'PARCIAL']))
            ->assertOk()
            ->assertSee($requerimiento->codigo)
            ->assertSee('Recepción parcial')
            ->assertSee('40% recibido');

        $this->registrarRecepcion($orden->fresh(), $repisa, 6);

        $requerimiento->refresh();
        $this->assertSame('COMPLETO', $requerimiento->estado_abastecimiento);
        $this->assertNotNull($requerimiento->abastecido_en);
        $this->assertSame(10.0, (float) $linea->fresh()->cantidad_atendida);
        $this->assertSame('RESUELTA', $alerta->fresh()->estado);

        $this->actingAs($this->almacen)
            ->get(route('requerimientos-compra.show', $requerimiento))
            ->assertOk()
            ->assertSee('Abastecimiento completado con recepciones reales')
            ->assertSee('100% recibido');
    }

    public function test_una_oc_recibida_no_cierra_el_requerimiento_si_cubre_menos_de_lo_solicitado(): void
    {
        $requerimiento = $this->crearRequerimiento('REQ-1912-SALDO');
        [$linea, $repisa] = $this->crearLineaConAlerta(
            $requerimiento,
            'MAT-1912-B',
            10,
            false
        );
        $cotizacion = $this->crearCotizacion($requerimiento, 'CP-1912-SALDO');
        $oferta = $this->crearOferta($cotizacion, $linea, 5);
        $orden = $this->crearOrden($cotizacion, $oferta, 'OC-1912-SALDO', 5);

        $this->registrarRecepcion($orden, $repisa, 5);

        $resultado = app(SeguimientoAbastecimientoRequerimientoService::class)
            ->construir($requerimiento->fresh());
        $seguimiento = $resultado['lineas']->first();

        $this->assertSame('RECIBIDA', $orden->fresh()->estado);
        $this->assertSame('PARCIAL', $requerimiento->fresh()->estado_abastecimiento);
        $this->assertNull($requerimiento->fresh()->abastecido_en);
        $this->assertSame('PARCIALMENTE_RECIBIDO', $seguimiento['estado']);
        $this->assertSame(5.0, $seguimiento['cantidad_pendiente_recibir']);
        $this->assertSame(50.0, $seguimiento['avance_porcentaje']);
    }

    public function test_filtro_separa_pendientes_parciales_y_completos(): void
    {
        $pendiente = $this->crearRequerimiento('REQ-1912-PENDIENTE');
        $parcial = $this->crearRequerimiento('REQ-1912-PARCIAL', 'PARCIAL');
        $completo = $this->crearRequerimiento('REQ-1912-COMPLETO', 'COMPLETO');

        $this->actingAs($this->almacen)
            ->get(route('requerimientos-compra.index', ['abastecimiento' => 'PARCIAL']))
            ->assertOk()
            ->assertSee($parcial->codigo)
            ->assertDontSee($pendiente->codigo)
            ->assertDontSee($completo->codigo);
    }

    private function crearRequerimiento(
        string $codigo,
        string $estadoAbastecimiento = 'PENDIENTE'
    ): Requisicion
    {
        return Requisicion::query()->create([
            'codigo' => $codigo,
            'fecha_solicitud' => now()->toDateString(),
            'origen' => 'REPOSICION',
            'prioridad' => 'ALTA',
            'estado' => 'ATENDIDA',
            'estado_abastecimiento' => $estadoAbastecimiento,
            'solicitado_por' => $this->almacen->id,
            'enviado_por' => $this->almacen->id,
            'enviado_en' => now(),
            'recibido_por' => $this->logistica->id,
            'recibido_en' => now(),
            'atendido_por' => $this->logistica->id,
            'atendido_en' => now(),
            'abastecido_en' => $estadoAbastecimiento === 'COMPLETO' ? now() : null,
        ]);
    }

    /** @return array{mixed, Repisa, AlertaStock|null} */
    private function crearLineaConAlerta(
        Requisicion $requerimiento,
        string $codigo,
        float $cantidad,
        bool $conAlerta = true
    ): array
    {
        $producto = Producto::query()->create([
            'unidad_medida_id' => $this->unidad->id,
            'codigo' => $codigo,
            'descripcion' => 'Producto de abastecimiento '.$codigo,
            'estado' => true,
        ]);
        $repisa = Repisa::query()->create([
            'codigo' => 'R-'.$codigo,
            'descripcion' => 'Repisa '.$codigo,
            'estado' => true,
        ]);
        $inventario = Inventario::query()->create([
            'producto_id' => $producto->id,
            'repisa_id' => $repisa->id,
            'stock_actual' => 1,
            'stock_minimo' => 5,
            'stock_maximo' => 30,
            'costo_promedio_soles' => 10,
        ]);
        $linea = $requerimiento->detalles()->create([
            'producto_id' => $producto->id,
            'cantidad_solicitada' => $cantidad,
            'cantidad_sugerida' => $cantidad,
            'cantidad_atendida' => 0,
            'stock_fisico_snapshot' => 1,
            'reservado_snapshot' => 0,
            'disponible_snapshot' => 1,
            'stock_minimo_snapshot' => 5,
        ]);
        $alerta = $conAlerta
            ? AlertaStock::query()->create([
                'inventario_id' => $inventario->id,
                'producto_id' => $producto->id,
                'repisa_id' => $repisa->id,
                'tipo_alerta' => 'STOCK_MINIMO',
                'nivel' => 'ADVERTENCIA',
                'stock_actual' => 1,
                'stock_minimo' => 5,
                'mensaje' => 'Producto por debajo del mínimo.',
                'estado' => 'ATENDIDA',
                'detectada_en' => now(),
                'atendida_por' => $this->almacen->id,
                'atendida_en' => now(),
            ])
            : null;

        return [$linea, $repisa, $alerta];
    }

    private function crearCotizacion(Requisicion $requerimiento, string $codigo): Cotizacion
    {
        return Cotizacion::query()->create([
            'requisicion_id' => $requerimiento->id,
            'proveedor_id' => $this->proveedor->id,
            'codigo' => $codigo,
            'numero_documento' => 'DOC-'.$codigo,
            'fecha_cotizacion' => now()->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => 1,
            'descuento_global_modo' => 'SIN_DESCUENTO',
            'subtotal' => 0,
            'descuento_global_monto' => 0,
            'impuesto' => 0,
            'total_calculado' => 0,
            'ajuste_redondeo' => 0,
            'total' => 0,
            'estado' => 'SELECCIONADA',
            'registrado_por' => $this->logistica->id,
        ]);
    }

    private function crearOferta(Cotizacion $cotizacion, $linea, float $cantidad)
    {
        return $cotizacion->detalles()->create([
            'requisicion_detalle_id' => $linea->id,
            'tipo_vinculacion' => 'SOLICITADO',
            'vinculacion_origen' => 'MANUAL',
            'producto_id' => $linea->producto_id,
            'cantidad' => $cantidad,
            'precio_unitario' => 11.8,
            'descuento_porcentaje' => 0,
            'descuento_modo' => 'SIN_DESCUENTO',
            'igv_modo' => 'INCLUIDO',
            'igv_porcentaje' => 18,
            'subtotal' => round($cantidad * 10, 4),
            'impuesto' => round($cantidad * 1.8, 4),
            'total' => round($cantidad * 11.8, 4),
        ]);
    }

    private function crearOrden(
        Cotizacion $cotizacion,
        $oferta,
        string $codigo,
        float $cantidad
    ): OrdenCompra
    {
        $solicitud = SolicitudCompra::query()->create([
            'cotizacion_id' => $cotizacion->id,
            'codigo' => str_replace('OC-', 'SC-', $codigo),
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
            'producto_id' => $oferta->producto_id,
            'cantidad' => $cantidad,
            'precio_unitario' => 11.8,
            'descuento_porcentaje' => 0,
            'subtotal' => round($cantidad * 11.8, 4),
        ]);
        $orden = OrdenCompra::query()->create([
            'solicitud_compra_id' => $solicitud->id,
            'proveedor_id' => $this->proveedor->id,
            'codigo' => $codigo,
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
            'producto_id' => $oferta->producto_id,
            'cantidad_ordenada' => $cantidad,
            'cantidad_recibida' => 0,
            'precio_unitario' => 11.8,
            'descuento_porcentaje' => 0,
            'subtotal' => round($cantidad * 11.8, 4),
        ]);

        return $orden->load('detalles');
    }

    private function registrarRecepcion(OrdenCompra $orden, Repisa $repisa, float $cantidad): void
    {
        $detalle = $orden->detalles()->firstOrFail();

        $this->actingAs($this->almacen)
            ->post(route('notas-ingreso.store'), [
                'motivo_ingreso' => 'COMPRA',
                'orden_compra_id' => $orden->id,
                'fecha_ingreso' => now()->toDateString(),
                'numero_guia_remision' => 'GR-'.$orden->codigo.'-'.$cantidad,
                'detalles' => [[
                    'orden_compra_detalle_id' => $detalle->id,
                    'producto_id' => $detalle->producto_id,
                    'repisa_id' => $repisa->id,
                    'cantidad' => $cantidad,
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
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
