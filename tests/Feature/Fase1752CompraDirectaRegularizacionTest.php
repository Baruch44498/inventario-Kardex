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

class Fase1752CompraDirectaRegularizacionTest extends TestCase
{
    use RefreshDatabase;

    private User $logistica;
    private User $almacen;
    private Producto $producto;
    private Producto $segundoProducto;
    private Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistica = $this->crearUsuario('COMERCIAL_LOGISTICA', 'logistica_1752');
        $this->almacen = $this->crearUsuario('ALMACEN', 'almacen_1752');
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'CD-1752-001',
            'descripcion' => 'Producto de compra directa',
            'estado' => true,
        ]);
        $this->segundoProducto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'CD-1752-002',
            'descripcion' => 'Segundo producto de compra directa',
            'estado' => true,
        ]);
        $this->proveedor = Proveedor::query()->create([
            'ruc' => '20617520001',
            'razon_social' => 'Proveedor Compra Directa 1752 SAC',
            'estado' => true,
        ]);
    }

    public function test_cotizacion_sin_requerimiento_y_solo_adicionales_muestra_la_excepcion(): void
    {
        $cotizacion = $this->crearCotizacionDirecta();

        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-proveedor.show', $cotizacion))
            ->assertOk()
            ->assertSee('Compra sin requerimiento previo')
            ->assertSee('Origen de la excepción')
            ->assertSee('Aprobar excepción y generar OC');
    }

    public function test_compra_directa_exige_origen_y_justificacion_suficiente(): void
    {
        $cotizacion = $this->crearCotizacionDirecta();

        $this->actingAs($this->logistica)
            ->post(
                route('cotizaciones-proveedor.aprobar-y-generar-orden', $cotizacion),
                $this->datosDirectos($cotizacion, [
                    'origen_compra_directa' => '',
                    'justificacion_origen' => 'Corto',
                ])
            )
            ->assertSessionHasErrors(['origen_compra_directa', 'justificacion_origen']);

        $this->assertDatabaseCount('solicitudes_compra', 0);
        $this->assertDatabaseCount('ordenes_compra', 0);
    }

    public function test_compra_directa_genera_solicitud_y_orden_con_trazabilidad_congelada(): void
    {
        $cotizacion = $this->crearCotizacionDirecta();
        $justificacion = 'Compra urgente autorizada por falta de stock operativo.';

        $this->actingAs($this->logistica)
            ->post(
                route('cotizaciones-proveedor.aprobar-y-generar-orden', $cotizacion),
                $this->datosDirectos($cotizacion, [
                    'origen_compra_directa' => 'URGENTE',
                    'justificacion_origen' => $justificacion,
                ])
            )
            ->assertRedirect();

        $solicitud = SolicitudCompra::query()->with('detalles')->firstOrFail();
        $orden = OrdenCompra::query()->with('detalles')->firstOrFail();

        $this->assertSame('URGENTE', $solicitud->origen);
        $this->assertSame($justificacion, $solicitud->justificacion_origen);
        $this->assertSame('URGENTE', $orden->origen);
        $this->assertSame($justificacion, $orden->justificacion_origen);
        $this->assertCount(2, $solicitud->detalles);
        $this->assertCount(2, $orden->detalles);
        $this->assertSame('SELECCIONADA', $cotizacion->fresh()->estado);

        $this->actingAs($this->almacen)
            ->get(route('ordenes-compra.show', $orden))
            ->assertOk()
            ->assertSee('Compra urgente sin requerimiento previo')
            ->assertSee($justificacion);
    }

    public function test_no_permite_compra_directa_parcial(): void
    {
        $cotizacion = $this->crearCotizacionDirecta();
        $primerDetalle = $cotizacion->detalles()->firstOrFail();

        $datos = $this->datosDirectos($cotizacion);
        $datos['detalle_ids'] = [$primerDetalle->id];

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-proveedor.aprobar-y-generar-orden', $cotizacion), $datos)
            ->assertSessionHasErrors('es_compra_directa');

        $this->assertDatabaseCount('ordenes_compra', 0);
    }

    public function test_cotizacion_con_requerimiento_y_solo_adicionales_no_puede_forzar_compra_directa(): void
    {
        $requisicion = $this->crearRequisicion();
        $cotizacion = $this->crearCotizacion($requisicion, 'ADICIONAL');

        $this->actingAs($this->logistica)
            ->post(
                route('cotizaciones-proveedor.aprobar-y-generar-orden', $cotizacion),
                $this->datosDirectos($cotizacion)
            )
            ->assertSessionHasErrors('es_compra_directa');

        $this->assertDatabaseCount('ordenes_compra', 0);
    }

    public function test_cotizacion_normal_no_puede_forzar_el_indicador_de_compra_directa(): void
    {
        $requisicion = $this->crearRequisicion();
        $detalleRequerido = $requisicion->detalles()->create([
            'producto_id' => $this->producto->id,
            'cantidad_solicitada' => 2,
            'cantidad_sugerida' => 2,
            'cantidad_atendida' => 0,
        ]);
        $cotizacion = $this->crearCotizacion($requisicion, 'SOLICITADO', $detalleRequerido->id);

        $this->actingAs($this->logistica)
            ->post(
                route('cotizaciones-proveedor.aprobar-y-generar-orden', $cotizacion),
                $this->datosDirectos($cotizacion)
            )
            ->assertSessionHasErrors('es_compra_directa');

        $this->assertDatabaseCount('ordenes_compra', 0);
    }

    public function test_almacen_no_puede_aprobar_una_compra_directa(): void
    {
        $cotizacion = $this->crearCotizacionDirecta();

        $this->actingAs($this->almacen)
            ->post(
                route('cotizaciones-proveedor.aprobar-y-generar-orden', $cotizacion),
                $this->datosDirectos($cotizacion)
            )
            ->assertForbidden();

        $this->assertDatabaseCount('ordenes_compra', 0);
    }

    private function crearCotizacionDirecta(): Cotizacion
    {
        $cotizacion = $this->crearCotizacion(null, 'ADICIONAL');
        $this->agregarDetalle($cotizacion, $this->segundoProducto, 'ADICIONAL');
        $cotizacion->update([
            'subtotal' => 80,
            'total_calculado' => 80,
            'total' => 80,
        ]);

        return $cotizacion->fresh('detalles');
    }

    private function crearCotizacion(
        ?Requisicion $requisicion,
        string $tipoVinculacion,
        ?int $requisicionDetalleId = null
    ): Cotizacion {
        $secuencia = Cotizacion::query()->count() + 1;
        $cotizacion = Cotizacion::query()->create([
            'requisicion_id' => $requisicion?->id,
            'proveedor_id' => $this->proveedor->id,
            'codigo' => 'CP-CD-'.str_pad((string) $secuencia, 3, '0', STR_PAD_LEFT),
            'numero_documento' => 'COT-CD-'.$secuencia,
            'fecha_cotizacion' => now()->toDateString(),
            'moneda' => 'PEN',
            'descuento_global_modo' => 'SIN_DESCUENTO',
            'subtotal' => 40,
            'descuento_global_monto' => 0,
            'impuesto' => 0,
            'total_calculado' => 40,
            'ajuste_redondeo' => 0,
            'total' => 40,
            'estado' => 'REGISTRADA',
            'registrado_por' => $this->logistica->id,
        ]);

        $this->agregarDetalle($cotizacion, $this->producto, $tipoVinculacion, $requisicionDetalleId);

        return $cotizacion->fresh('detalles');
    }

    private function agregarDetalle(
        Cotizacion $cotizacion,
        Producto $producto,
        string $tipoVinculacion,
        ?int $requisicionDetalleId = null
    ): void {
        $cotizacion->detalles()->create([
            'requisicion_detalle_id' => $requisicionDetalleId,
            'tipo_vinculacion' => $tipoVinculacion,
            'vinculacion_origen' => 'MANUAL',
            'producto_id' => $producto->id,
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

    private function crearRequisicion(): Requisicion
    {
        return Requisicion::query()->create([
            'codigo' => 'REQ-CD-'.(Requisicion::query()->count() + 1),
            'fecha_solicitud' => now()->toDateString(),
            'origen' => 'REPOSICION',
            'prioridad' => 'ALTA',
            'estado' => 'COTIZANDO',
            'solicitado_por' => $this->logistica->id,
        ]);
    }

    /** @param array<string, mixed> $cambios */
    private function datosDirectos(Cotizacion $cotizacion, array $cambios = []): array
    {
        return array_merge([
            'detalle_ids' => $cotizacion->detalles()->pluck('id')->all(),
            'es_compra_directa' => true,
            'origen_compra_directa' => 'COMPRA_DIRECTA',
            'justificacion_origen' => 'Compra autorizada sin requerimiento previo documentado.',
            'fecha_emision' => now()->toDateString(),
            'fecha_entrega_requerida' => now()->addDays(3)->toDateString(),
        ], $cambios);
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
