<?php

namespace Tests\Feature;

use App\Models\Cotizacion;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\Role;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\Documentos\GenerarCodigoDocumentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class Fase1909ComparativoCompraPorProveedorTest extends TestCase
{
    use RefreshDatabase;

    private User $logistica;
    private Requisicion $requerimiento;
    private Producto $productoA;
    private Producto $productoB;
    private Proveedor $proveedorA;
    private Proveedor $proveedorB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistica = User::query()->create([
            'role_id' => Role::query()->where('codigo', 'COMERCIAL_LOGISTICA')->firstOrFail()->id,
            'username' => 'logistica_1909',
            'email' => 'logistica_1909@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $this->productoA = $this->crearProducto($unidad->id, 'MAT-1909-A', 'Material comparativo A');
        $this->productoB = $this->crearProducto($unidad->id, 'MAT-1909-B', 'Material comparativo B');
        $this->proveedorA = $this->crearProveedor('20619090001', 'Proveedor Comparativo A');
        $this->proveedorB = $this->crearProveedor('20619090002', 'Proveedor Comparativo B');
        $this->requerimiento = Requisicion::query()->create([
            'codigo' => 'REQ-1909-001',
            'fecha_solicitud' => now()->toDateString(),
            'origen' => 'REPOSICION',
            'prioridad' => 'ALTA',
            'estado' => 'COTIZANDO',
            'solicitado_por' => $this->logistica->id,
        ]);
    }

    public function test_comparativo_muestra_la_mejor_oferta_por_cada_producto(): void
    {
        [$lineaA, $lineaB] = $this->crearLineas();
        $cotizacionA = $this->crearCotizacion($this->proveedorA, 'CP-1909-A');
        $cotizacionB = $this->crearCotizacion($this->proveedorB, 'CP-1909-B');
        $this->agregarOferta($cotizacionA, $lineaA->id, $this->productoA, 10);
        $this->agregarOferta($cotizacionA, $lineaB->id, $this->productoB, 30);
        $this->agregarOferta($cotizacionB, $lineaA->id, $this->productoA, 12);
        $this->agregarOferta($cotizacionB, $lineaB->id, $this->productoB, 20);

        $this->actingAs($this->logistica)
            ->get(route('requerimientos-compra.comparativo', $this->requerimiento))
            ->assertOk()
            ->assertSee('Comparar ofertas y dividir la compra')
            ->assertSee('MAT-1909-A')
            ->assertSee('MAT-1909-B')
            ->assertSee('Proveedor Comparativo A')
            ->assertSee('Proveedor Comparativo B')
            ->assertSee('Mejor precio');
    }

    public function test_genera_una_orden_por_proveedor_y_cierra_al_cubrir_todas_las_lineas(): void
    {
        [$lineaA, $lineaB] = $this->crearLineas();
        $cotizacionA = $this->crearCotizacion($this->proveedorA, 'CP-1909-A');
        $cotizacionB = $this->crearCotizacion($this->proveedorB, 'CP-1909-B');
        $ofertaA = $this->agregarOferta($cotizacionA, $lineaA->id, $this->productoA, 10);
        $this->agregarOferta($cotizacionA, $lineaB->id, $this->productoB, 30);
        $this->agregarOferta($cotizacionB, $lineaA->id, $this->productoA, 12);
        $ofertaB = $this->agregarOferta($cotizacionB, $lineaB->id, $this->productoB, 20);

        $this->actingAs($this->logistica)
            ->post(route('requerimientos-compra.comparativo.comprar', $this->requerimiento), [
                'selecciones' => [
                    $lineaA->id => $ofertaA->id,
                    $lineaB->id => $ofertaB->id,
                ],
                'fecha_emision' => now()->toDateString(),
                'fecha_entrega_requerida' => now()->addDays(5)->toDateString(),
                'observacion' => 'Compra dividida por mejor oferta de cada producto.',
            ])
            ->assertRedirect(route('requerimientos-compra.comparativo', $this->requerimiento))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('ordenes_compra', 2);
        $this->assertSame('ATENDIDA', $this->requerimiento->fresh()->estado);

        $ordenes = OrdenCompra::query()->with('detalles')->orderBy('proveedor_id')->get();
        $this->assertEqualsCanonicalizing(
            [$this->proveedorA->id, $this->proveedorB->id],
            $ordenes->pluck('proveedor_id')->all()
        );
        $this->assertTrue($ordenes->every(fn (OrdenCompra $orden): bool => $orden->detalles->count() === 1));
    }

    public function test_una_compra_parcial_mantiene_el_requerimiento_cotizando(): void
    {
        [$lineaA] = $this->crearLineas();
        $cotizacion = $this->crearCotizacion($this->proveedorA, 'CP-1909-PARCIAL');
        $oferta = $this->agregarOferta($cotizacion, $lineaA->id, $this->productoA, 10);

        $this->actingAs($this->logistica)
            ->post(route('requerimientos-compra.comparativo.comprar', $this->requerimiento), [
                'selecciones' => [$lineaA->id => $oferta->id],
                'fecha_emision' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('ordenes_compra', 1);
        $this->assertSame('COTIZANDO', $this->requerimiento->fresh()->estado);
    }

    public function test_no_permite_comprar_dos_veces_la_misma_linea_desde_otra_cotizacion(): void
    {
        [$lineaA] = $this->crearLineas();
        $cotizacionA = $this->crearCotizacion($this->proveedorA, 'CP-1909-DUP-A');
        $cotizacionB = $this->crearCotizacion($this->proveedorB, 'CP-1909-DUP-B');
        $ofertaA = $this->agregarOferta($cotizacionA, $lineaA->id, $this->productoA, 10);
        $ofertaB = $this->agregarOferta($cotizacionB, $lineaA->id, $this->productoA, 11);

        $this->actingAs($this->logistica)
            ->post(route('requerimientos-compra.comparativo.comprar', $this->requerimiento), [
                'selecciones' => [$lineaA->id => $ofertaA->id],
                'fecha_emision' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->requerimiento->update(['estado' => 'COTIZANDO']);

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-proveedor.aprobar-y-generar-orden', $cotizacionB), [
                'detalle_ids' => [$ofertaB->id],
                'es_compra_directa' => 0,
                'fecha_emision' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('detalle_ids');

        $this->assertDatabaseCount('ordenes_compra', 1);
    }

    public function test_revierte_todas_las_ordenes_si_falla_un_proveedor_del_mismo_lote(): void
    {
        [$lineaA, $lineaB] = $this->crearLineas();
        $cotizacionA = $this->crearCotizacion($this->proveedorA, 'CP-1909-ATOM-A');
        $cotizacionB = $this->crearCotizacion($this->proveedorB, 'CP-1909-ATOM-B');
        $ofertaA = $this->agregarOferta($cotizacionA, $lineaA->id, $this->productoA, 10);
        $ofertaB = $this->agregarOferta($cotizacionB, $lineaB->id, $this->productoB, 20);

        $llamadas = 0;
        $generadorControlado = Mockery::mock(GenerarCodigoDocumentoService::class);
        $generadorControlado
            ->shouldReceive('usarSiguientes')
            ->twice()
            ->andReturnUsing(function (array $documentos, \Closure $callback) use (&$llamadas): mixed {
                $llamadas++;
                if ($llamadas === 2) {
                    throw ValidationException::withMessages([
                        'selecciones' => 'Fallo controlado al generar la segunda orden.',
                    ]);
                }

                return $callback([
                    'solicitud' => 'SC-901-26',
                    'orden' => 'OC-901-26',
                ]);
            });
        $this->app->instance(GenerarCodigoDocumentoService::class, $generadorControlado);

        $this->actingAs($this->logistica)
            ->from(route('requerimientos-compra.comparativo', $this->requerimiento))
            ->post(route('requerimientos-compra.comparativo.comprar', $this->requerimiento), [
                'selecciones' => [
                    $lineaA->id => $ofertaA->id,
                    $lineaB->id => $ofertaB->id,
                ],
                'fecha_emision' => now()->toDateString(),
            ])
            ->assertRedirect(route('requerimientos-compra.comparativo', $this->requerimiento))
            ->assertSessionHasErrors('selecciones');

        $this->assertDatabaseCount('solicitudes_compra', 0);
        $this->assertDatabaseCount('ordenes_compra', 0);
        $this->assertSame('REGISTRADA', $cotizacionA->fresh()->estado);
        $this->assertSame('REGISTRADA', $cotizacionB->fresh()->estado);
        $this->assertSame('COTIZANDO', $this->requerimiento->fresh()->estado);
    }

    /** @return array<int, \App\Models\RequisicionDetalle> */
    private function crearLineas(): array
    {
        return [
            $this->requerimiento->detalles()->create([
                'producto_id' => $this->productoA->id,
                'cantidad_solicitada' => 2,
                'cantidad_sugerida' => 2,
                'cantidad_atendida' => 0,
            ]),
            $this->requerimiento->detalles()->create([
                'producto_id' => $this->productoB->id,
                'cantidad_solicitada' => 3,
                'cantidad_sugerida' => 3,
                'cantidad_atendida' => 0,
            ]),
        ];
    }

    private function crearProducto(int $unidadId, string $codigo, string $descripcion): Producto
    {
        return Producto::query()->create([
            'unidad_medida_id' => $unidadId,
            'codigo' => $codigo,
            'descripcion' => $descripcion,
            'estado' => true,
        ]);
    }

    private function crearProveedor(string $ruc, string $nombre): Proveedor
    {
        return Proveedor::query()->create([
            'ruc' => $ruc,
            'razon_social' => $nombre.' S.A.C.',
            'nombre_comercial' => $nombre,
            'estado' => true,
        ]);
    }

    private function crearCotizacion(Proveedor $proveedor, string $codigo): Cotizacion
    {
        return Cotizacion::query()->create([
            'requisicion_id' => $this->requerimiento->id,
            'proveedor_id' => $proveedor->id,
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
            'estado' => 'REGISTRADA',
            'registrado_por' => $this->logistica->id,
        ]);
    }

    private function agregarOferta(
        Cotizacion $cotizacion,
        int $lineaId,
        Producto $producto,
        float $precio
    ) {
        $detalle = $cotizacion->detalles()->create([
            'requisicion_detalle_id' => $lineaId,
            'tipo_vinculacion' => 'SOLICITADO',
            'vinculacion_origen' => 'MANUAL',
            'producto_id' => $producto->id,
            'cantidad' => 1,
            'precio_unitario' => $precio,
            'descuento_porcentaje' => 0,
            'descuento_modo' => 'SIN_DESCUENTO',
            'igv_modo' => 'INCLUIDO',
            'igv_porcentaje' => 18,
            'subtotal' => $precio,
            'impuesto' => 0,
            'total' => $precio,
        ]);

        $cotizacion->update([
            'subtotal' => $cotizacion->detalles()->sum('subtotal'),
            'total_calculado' => $cotizacion->detalles()->sum('total'),
            'total' => $cotizacion->detalles()->sum('total'),
        ]);

        return $detalle;
    }
}
