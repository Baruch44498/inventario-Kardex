<?php

namespace Tests\Feature;

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
use App\Services\Inventario\DisponibilidadMaterialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1910SeguimientoAbastecimientoTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private User $logistica;
    private Proveedor $proveedor;
    private UnidadMedida $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = $this->crearUsuario('ALMACEN', 'almacen_1910');
        $this->logistica = $this->crearUsuario('COMERCIAL_LOGISTICA', 'logistica_1910');
        $this->unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $this->proveedor = Proveedor::query()->create([
            'ruc' => '20619100001',
            'razon_social' => 'Proveedor Seguimiento 1910 S.A.C.',
            'nombre_comercial' => 'Proveedor Seguimiento',
            'estado' => true,
        ]);
    }

    public function test_muestra_el_estado_independiente_de_cada_producto_hasta_la_recepcion(): void
    {
        $requerimiento = $this->crearRequerimiento('REQ-1910-SEGUIMIENTO');
        $pendiente = $this->crearLinea($requerimiento, 'MAT-1910-P', 'Pendiente de cotizar');
        $cotizada = $this->crearLinea($requerimiento, 'MAT-1910-C', 'Producto cotizado');
        $ordenada = $this->crearLinea($requerimiento, 'MAT-1910-O', 'Producto ordenado');
        $parcial = $this->crearLinea($requerimiento, 'MAT-1910-RP', 'Producto parcial');
        $recibida = $this->crearLinea($requerimiento, 'MAT-1910-RC', 'Producto recibido');

        $cotizacion = $this->crearCotizacion($requerimiento, 'CP-1910-SEGUIMIENTO');
        $ofertaCotizada = $this->crearOferta($cotizacion, $cotizada);
        $ofertaOrdenada = $this->crearOferta($cotizacion, $ordenada);
        $ofertaParcial = $this->crearOferta($cotizacion, $parcial);
        $ofertaRecibida = $this->crearOferta($cotizacion, $recibida);
        $orden = $this->crearOrden($cotizacion, 'OC-1910-SEGUIMIENTO', [
            [$ofertaOrdenada, 0],
            [$ofertaParcial, 2],
            [$ofertaRecibida, 5],
        ]);
        $cotizacion->update(['estado' => 'SELECCIONADA']);
        $orden->update(['estado' => 'PARCIALMENTE_RECIBIDA']);

        $resultado = app(SeguimientoAbastecimientoRequerimientoService::class)
            ->construir($requerimiento);
        $estados = $resultado['lineas']->keyBy('requisicion_detalle_id');

        $this->assertSame('PENDIENTE_COTIZAR', $estados[$pendiente->id]['estado']);
        $this->assertSame('COTIZADO', $estados[$cotizada->id]['estado']);
        $this->assertSame('ORDENADO', $estados[$ordenada->id]['estado']);
        $this->assertSame('PARCIALMENTE_RECIBIDO', $estados[$parcial->id]['estado']);
        $this->assertSame('RECIBIDO', $estados[$recibida->id]['estado']);
        $this->assertSame(1, $resultado['pendientes_cotizar']);
        $this->assertSame(1, $resultado['cotizadas']);
        $this->assertSame(1, $resultado['ordenadas']);
        $this->assertSame(1, $resultado['parciales']);
        $this->assertSame(1, $resultado['recibidas']);
        $this->assertSame(28.0, $resultado['avance_porcentaje']);

        $this->actingAs($this->logistica)
            ->get(route('requerimientos-compra.show', $requerimiento))
            ->assertOk()
            ->assertSee('Seguimiento por producto')
            ->assertSee('Pendiente de cotizar')
            ->assertSee('Con ofertas')
            ->assertSee('Orden de compra emitida')
            ->assertSee('Recepción parcial')
            ->assertSee('Recibido completamente')
            ->assertSee($orden->codigo);

        $this->assertNotNull($ofertaCotizada->id);
    }

    public function test_una_nota_de_ingreso_sincroniza_la_cantidad_atendida_del_requerimiento(): void
    {
        $requerimiento = $this->crearRequerimiento('REQ-1910-RECEPCION');
        $linea = $this->crearLinea($requerimiento, 'MAT-1910-NI', 'Producto para Nota de Ingreso', 10);
        $cotizacion = $this->crearCotizacion($requerimiento, 'CP-1910-RECEPCION');
        $oferta = $this->crearOferta($cotizacion, $linea, 10);
        $orden = $this->crearOrden($cotizacion, 'OC-1910-RECEPCION', [[$oferta, 0]], 10);
        $cotizacion->update(['estado' => 'SELECCIONADA']);
        $detalleOrden = $orden->detalles()->firstOrFail();
        $repisa = Repisa::query()->create([
            'codigo' => 'R-1910',
            'descripcion' => 'Repisa seguimiento',
            'estado' => true,
        ]);

        $this->actingAs($this->almacen)
            ->post(route('notas-ingreso.store'), [
                'motivo_ingreso' => 'COMPRA',
                'orden_compra_id' => $orden->id,
                'fecha_ingreso' => now()->toDateString(),
                'numero_guia_remision' => 'GR-1910-01',
                'detalles' => [[
                    'orden_compra_detalle_id' => $detalleOrden->id,
                    'producto_id' => $detalleOrden->producto_id,
                    'repisa_id' => $repisa->id,
                    'cantidad' => 4,
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(4.0, (float) $linea->fresh()->cantidad_atendida);
        $this->assertSame(4.0, (float) $detalleOrden->fresh()->cantidad_recibida);

        $resultado = app(SeguimientoAbastecimientoRequerimientoService::class)
            ->construir($requerimiento->fresh());

        $this->assertSame('PARCIALMENTE_RECIBIDO', $resultado['lineas']->first()['estado']);
        $this->assertSame(6.0, $resultado['lineas']->first()['cantidad_pendiente_recibir']);
        $this->assertCount(1, $resultado['lineas']->first()['ordenes']->first()['recepciones']);
    }

    public function test_reposicion_busca_el_objetivo_y_descuenta_lo_que_ya_viene_en_compra(): void
    {
        $requerimiento = $this->crearRequerimiento('REQ-1910-OBJETIVO');
        $linea = $this->crearLinea($requerimiento, 'MAT-1910-OBJ', 'Producto con nivel objetivo', 10);
        $repisa = Repisa::query()->create([
            'codigo' => 'R-1910-OBJ',
            'descripcion' => 'Repisa nivel objetivo',
            'estado' => true,
        ]);
        Inventario::query()->create([
            'producto_id' => $linea->producto_id,
            'repisa_id' => $repisa->id,
            'stock_actual' => 2,
            'stock_minimo' => 4,
            'stock_maximo' => 10,
            'costo_promedio_soles' => 10,
        ]);

        $cotizacion = $this->crearCotizacion($requerimiento, 'CP-1910-OBJETIVO');
        $oferta = $this->crearOferta($cotizacion, $linea, 5);
        $orden = $this->crearOrden($cotizacion, 'OC-1910-OBJETIVO', [[$oferta, 1]], 5);
        $orden->update(['estado' => 'PARCIALMENTE_RECIBIDA']);

        $resumen = app(DisponibilidadMaterialService::class)
            ->resumenProducto((int) $linea->producto_id);

        $this->assertSame(10.0, (float) $resumen['stock_objetivo']);
        $this->assertSame(4.0, (float) $resumen['pendiente_compra']);
        $this->assertSame(6.0, (float) $resumen['disponible_proyectado']);
        $this->assertSame(4.0, (float) $resumen['necesidad_abastecimiento']);
        $this->assertTrue($resumen['stock_objetivo_configurado']);
    }

    private function crearRequerimiento(string $codigo): Requisicion
    {
        return Requisicion::query()->create([
            'codigo' => $codigo,
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
    }

    private function crearLinea(
        Requisicion $requerimiento,
        string $codigo,
        string $descripcion,
        float $cantidad = 5
    ) {
        $producto = Producto::query()->create([
            'unidad_medida_id' => $this->unidad->id,
            'codigo' => $codigo,
            'descripcion' => $descripcion,
            'estado' => true,
        ]);

        return $requerimiento->detalles()->create([
            'producto_id' => $producto->id,
            'cantidad_solicitada' => $cantidad,
            'cantidad_sugerida' => $cantidad,
            'cantidad_atendida' => 0,
            'stock_fisico_snapshot' => 0,
            'reservado_snapshot' => 0,
            'disponible_snapshot' => 0,
            'stock_minimo_snapshot' => $cantidad,
        ]);
    }

    private function crearCotizacion(Requisicion $requerimiento, string $codigo): Cotizacion
    {
        return Cotizacion::query()->create([
            'requisicion_id' => $requerimiento->id,
            'proveedor_id' => $this->proveedor->id,
            'codigo' => $codigo,
            'numero_documento' => 'DOC-' . $codigo,
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
            'estado' => 'REGISTRADA',
            'registrado_por' => $this->logistica->id,
        ]);
    }

    private function crearOferta(Cotizacion $cotizacion, $linea, float $cantidad = 5)
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

    /** @param array<int, array{0: mixed, 1: float|int}> $ofertas */
    private function crearOrden(
        Cotizacion $cotizacion,
        string $codigo,
        array $ofertas,
        float $cantidadOrdenada = 5
    ): OrdenCompra {
        $solicitud = SolicitudCompra::query()->create([
            'cotizacion_id' => $cotizacion->id,
            'codigo' => str_replace('OC-', 'SC-', $codigo),
            'fecha_solicitud' => now()->toDateString(),
            'total_lineas' => 59 * count($ofertas),
            'ajuste_redondeo' => 0,
            'total_seleccionado' => 59 * count($ofertas),
            'estado' => 'CONVERTIDA',
            'solicitado_por' => $this->logistica->id,
            'aprobado_por' => $this->logistica->id,
            'aprobado_en' => now(),
        ]);
        $orden = OrdenCompra::query()->create([
            'solicitud_compra_id' => $solicitud->id,
            'proveedor_id' => $this->proveedor->id,
            'codigo' => $codigo,
            'origen' => 'REQUERIMIENTO',
            'fecha_emision' => now()->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => 1,
            'subtotal' => 50 * count($ofertas),
            'impuesto' => 9 * count($ofertas),
            'ajuste_redondeo' => 0,
            'total' => 59 * count($ofertas),
            'estado' => 'APROBADA',
            'emitido_por' => $this->logistica->id,
            'aprobado_por' => $this->logistica->id,
            'aprobado_en' => now(),
        ]);

        foreach ($ofertas as [$oferta, $cantidadRecibida]) {
            $solicitudDetalle = $solicitud->detalles()->create([
                'cotizacion_detalle_id' => $oferta->id,
                'producto_id' => $oferta->producto_id,
                'cantidad' => $cantidadOrdenada,
                'precio_unitario' => 11.8,
                'descuento_porcentaje' => 0,
                'subtotal' => round($cantidadOrdenada * 11.8, 4),
            ]);
            $orden->detalles()->create([
                'solicitud_compra_detalle_id' => $solicitudDetalle->id,
                'producto_id' => $oferta->producto_id,
                'cantidad_ordenada' => $cantidadOrdenada,
                'cantidad_recibida' => $cantidadRecibida,
                'precio_unitario' => 11.8,
                'descuento_porcentaje' => 0,
                'subtotal' => round($cantidadOrdenada * 11.8, 4),
            ]);
        }

        return $orden->load('detalles');
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
