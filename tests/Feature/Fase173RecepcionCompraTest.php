<?php

namespace Tests\Feature;

use App\Models\Cotizacion;
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

class Fase173RecepcionCompraTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private User $logistica;
    private Proveedor $proveedor;
    private Repisa $repisa;
    private Producto $productoA;
    private Producto $productoB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = $this->crearUsuario('ALMACEN', 'almacen_173');
        $this->logistica = $this->crearUsuario('COMERCIAL_LOGISTICA', 'logistica_173');
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $this->productoA = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'REC-173-A',
            'descripcion' => 'Producto A para recepción parcial',
            'estado' => true,
        ]);
        $this->productoB = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'REC-173-B',
            'descripcion' => 'Producto B para recepción por líneas',
            'estado' => true,
        ]);
        $this->proveedor = Proveedor::query()->create([
            'ruc' => '20617300001',
            'razon_social' => 'Proveedor Recepción 173 SAC',
            'estado' => true,
        ]);
        $this->repisa = Repisa::query()->create([
            'codigo' => 'REC-173',
            'descripcion' => 'Repisa para pruebas de recepción',
            'estado' => true,
        ]);
    }

    public function test_registra_cantidad_menor_y_luego_el_saldo_hasta_recibir_completamente(): void
    {
        $orden = $this->crearOrden();
        $detalle = $orden->detalles->firstOrFail();

        $this->actingAs($this->almacen)
            ->get(route('notas-ingreso.create', [
                'motivo_ingreso' => 'COMPRA',
                'orden_compra_id' => $orden->id,
            ]))
            ->assertOk()
            ->assertSee('IGV incluido')
            ->assertSee('Costo total de compra con IGV incluido');

        $this->actingAs($this->almacen)
            ->post(route('notas-ingreso.store'), $this->datosRecepcion($orden, [
                $this->linea($detalle, 4),
            ]))
            ->assertRedirect();

        $this->assertSame('PARCIALMENTE_RECIBIDA', $orden->fresh()->estado);
        $this->assertSame(4.0, (float) $detalle->fresh()->cantidad_recibida);
        $this->assertSame(4.0, (float) Inventario::query()->firstOrFail()->stock_actual);
        $this->assertDatabaseCount('notas_ingreso', 1);
        $this->assertDatabaseCount('movimientos_inventario', 1);

        $this->actingAs($this->almacen)
            ->post(route('notas-ingreso.store'), $this->datosRecepcion($orden, [
                $this->linea($detalle, 6),
            ]))
            ->assertRedirect();

        $this->assertSame('RECIBIDA', $orden->fresh()->estado);
        $this->assertSame(10.0, (float) $detalle->fresh()->cantidad_recibida);
        $this->assertSame(10.0, (float) Inventario::query()->firstOrFail()->stock_actual);
        $this->assertDatabaseCount('notas_ingreso', 2);
        $this->assertDatabaseCount('movimientos_inventario', 2);

        $this->actingAs($this->almacen)
            ->get(route('ordenes-compra.show', $orden))
            ->assertOk()
            ->assertSee('Recibida completamente')
            ->assertSee('Recepción completada');
    }

    public function test_permite_recibir_solo_algunas_lineas_y_omite_las_cantidades_en_cero(): void
    {
        $orden = $this->crearOrden(dosLineas: true);
        $primera = $orden->detalles->firstWhere('producto_id', $this->productoA->id);
        $segunda = $orden->detalles->firstWhere('producto_id', $this->productoB->id);

        $this->actingAs($this->almacen)
            ->post(route('notas-ingreso.store'), $this->datosRecepcion($orden, [
                $this->linea($primera, 5),
                [
                    'orden_compra_detalle_id' => $segunda->id,
                    'producto_id' => $segunda->producto_id,
                    'cantidad' => 0,
                ],
            ]))
            ->assertRedirect();

        $nota = NotaIngreso::query()->with('detalles')->firstOrFail();
        $this->assertCount(1, $nota->detalles);
        $this->assertSame($this->productoA->id, $nota->detalles->first()->producto_id);
        $this->assertSame(5.0, (float) $primera->fresh()->cantidad_recibida);
        $this->assertSame(0.0, (float) $segunda->fresh()->cantidad_recibida);
        $this->assertSame('PARCIALMENTE_RECIBIDA', $orden->fresh()->estado);
    }

    public function test_bloquea_una_recepcion_mayor_al_saldo_sin_mover_inventario(): void
    {
        $orden = $this->crearOrden();
        $detalle = $orden->detalles->firstOrFail();

        $this->actingAs($this->almacen)
            ->post(route('notas-ingreso.store'), $this->datosRecepcion($orden, [
                $this->linea($detalle, 10.001),
            ]))
            ->assertSessionHasErrors('detalles.0.cantidad');

        $this->assertSame('APROBADA', $orden->fresh()->estado);
        $this->assertSame(0.0, (float) $detalle->fresh()->cantidad_recibida);
        $this->assertDatabaseCount('notas_ingreso', 0);
        $this->assertDatabaseCount('inventarios', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_oc_en_dolares_valoriza_inventario_en_soles_con_igv_y_no_acepta_override(): void
    {
        $orden = $this->crearOrden(moneda: 'USD', tipoCambio: 3.75);
        $detalle = $orden->detalles->firstOrFail();
        $linea = $this->linea($detalle, 2);
        $linea['costo_unitario'] = 999;

        $this->actingAs($this->almacen)
            ->post(route('notas-ingreso.store'), $this->datosRecepcion($orden, [$linea]))
            ->assertRedirect();

        $nota = NotaIngreso::query()->with('detalles')->firstOrFail();
        $inventario = Inventario::query()->firstOrFail();
        $movimiento = MovimientoInventario::query()->firstOrFail();

        $this->assertSame(44.25, (float) $nota->detalles->first()->costo_unitario);
        $this->assertSame(88.5, (float) $nota->detalles->first()->subtotal);
        $this->assertSame(44.25, (float) $inventario->costo_promedio_soles);
        $this->assertSame(44.25, (float) $movimiento->costo_unitario);
    }

    public function test_el_costo_de_compra_no_depende_de_un_valor_enviado_por_almacen(): void
    {
        $orden = $this->crearOrden();
        $detalle = $orden->detalles->firstOrFail();
        $linea = $this->linea($detalle, 1);
        unset($linea['costo_unitario']);

        $this->actingAs($this->almacen)
            ->post(route('notas-ingreso.store'), $this->datosRecepcion($orden, [$linea]))
            ->assertRedirect();

        $this->assertSame(
            11.8,
            (float) NotaIngreso::query()->with('detalles')->firstOrFail()->detalles->first()->costo_unitario
        );
    }

    private function crearOrden(
        string $moneda = 'PEN',
        ?float $tipoCambio = null,
        bool $dosLineas = false
    ): OrdenCompra {
        $productos = $dosLineas
            ? collect([$this->productoA, $this->productoB])
            : collect([$this->productoA]);
        $multiplicador = $productos->count();
        $requisicion = Requisicion::query()->create([
            'codigo' => 'REQ-REC-' . (Requisicion::query()->count() + 1),
            'fecha_solicitud' => now()->toDateString(),
            'origen' => 'REPOSICION',
            'prioridad' => 'MEDIA',
            'estado' => 'COTIZANDO',
            'solicitado_por' => $this->almacen->id,
        ]);
        $cotizacion = Cotizacion::query()->create([
            'requisicion_id' => $requisicion->id,
            'proveedor_id' => $this->proveedor->id,
            'codigo' => 'CP-REC-' . (Cotizacion::query()->count() + 1),
            'numero_documento' => 'PROV-REC-173',
            'fecha_cotizacion' => now()->toDateString(),
            'moneda' => $moneda,
            'tipo_cambio' => $tipoCambio,
            'descuento_global_modo' => 'SIN_DESCUENTO',
            'descuento_global_monto' => 0,
            'subtotal' => 100 * $multiplicador,
            'impuesto' => 18 * $multiplicador,
            'total' => 118 * $multiplicador,
            'total_calculado' => 118 * $multiplicador,
            'ajuste_redondeo' => 0,
            'estado' => 'SELECCIONADA',
            'registrado_por' => $this->logistica->id,
        ]);
        $solicitud = SolicitudCompra::query()->create([
            'cotizacion_id' => $cotizacion->id,
            'codigo' => 'SC-REC-' . (SolicitudCompra::query()->count() + 1),
            'fecha_solicitud' => now()->toDateString(),
            'total_lineas' => 118 * $multiplicador,
            'ajuste_redondeo' => 0,
            'total_seleccionado' => 118 * $multiplicador,
            'estado' => 'CONVERTIDA',
            'solicitado_por' => $this->logistica->id,
            'aprobado_por' => $this->logistica->id,
            'aprobado_en' => now(),
        ]);
        $orden = OrdenCompra::query()->create([
            'solicitud_compra_id' => $solicitud->id,
            'proveedor_id' => $this->proveedor->id,
            'codigo' => 'OC-REC-' . (OrdenCompra::query()->count() + 1),
            'fecha_emision' => now()->toDateString(),
            'moneda' => $moneda,
            'tipo_cambio' => $tipoCambio,
            'subtotal' => 100 * $multiplicador,
            'impuesto' => 18 * $multiplicador,
            'ajuste_redondeo' => 0,
            'total' => 118 * $multiplicador,
            'estado' => 'APROBADA',
            'emitido_por' => $this->logistica->id,
            'aprobado_por' => $this->logistica->id,
            'aprobado_en' => now(),
        ]);

        foreach ($productos as $producto) {
            $requisicionDetalle = $requisicion->detalles()->create([
                'producto_id' => $producto->id,
                'cantidad_solicitada' => 10,
                'cantidad_sugerida' => 10,
                'cantidad_atendida' => 0,
            ]);
            $cotizacionDetalle = $cotizacion->detalles()->create([
                'requisicion_detalle_id' => $requisicionDetalle->id,
                'tipo_vinculacion' => 'SOLICITADO',
                'vinculacion_origen' => 'MANUAL',
                'producto_id' => $producto->id,
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
            $solicitudDetalle = $solicitud->detalles()->create([
                'cotizacion_detalle_id' => $cotizacionDetalle->id,
                'producto_id' => $producto->id,
                'cantidad' => 10,
                'precio_unitario' => 11.8,
                'descuento_porcentaje' => 0,
                'subtotal' => 118,
            ]);
            $orden->detalles()->create([
                'solicitud_compra_detalle_id' => $solicitudDetalle->id,
                'producto_id' => $producto->id,
                'cantidad_ordenada' => 10,
                'cantidad_recibida' => 0,
                'precio_unitario' => 11.8,
                'descuento_porcentaje' => 0,
                'subtotal' => 118,
            ]);
        }

        return $orden->load('detalles');
    }

    /** @return array<string, mixed> */
    private function datosRecepcion(OrdenCompra $orden, array $detalles): array
    {
        return [
            'motivo_ingreso' => 'COMPRA',
            'orden_compra_id' => $orden->id,
            'fecha_ingreso' => now()->toDateString(),
            'numero_guia_remision' => 'T001-173',
            'detalles' => $detalles,
        ];
    }

    /** @return array<string, mixed> */
    private function linea($detalle, float $cantidad): array
    {
        return [
            'orden_compra_detalle_id' => $detalle->id,
            'producto_id' => $detalle->producto_id,
            'repisa_id' => $this->repisa->id,
            'cantidad' => $cantidad,
            'costo_unitario' => 1,
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
