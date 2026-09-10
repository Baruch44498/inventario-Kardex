<?php

namespace Tests\Feature;

use App\Models\Cotizacion;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\SolicitudCompra;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Fase1915ControlEntregasOrdenCompraTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private User $logistica;
    private Producto $producto;
    private Proveedor $proveedor;
    private int $secuencia = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-09 10:00:00');
        $this->almacen = $this->crearUsuario('ALMACEN', 'almacen_1915');
        $this->logistica = $this->crearUsuario('COMERCIAL_LOGISTICA', 'logistica_1915');
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'MAT-1915-CONTROL',
            'descripcion' => 'Producto para controlar fechas de entrega',
            'estado' => true,
        ]);
        $this->proveedor = Proveedor::query()->create([
            'ruc' => '20619150001',
            'razon_social' => 'Proveedor Control de Entregas S.A.C.',
            'estado' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_clasifica_entregas_atrasadas_de_hoy_futuras_y_sin_fecha(): void
    {
        $atrasada = $this->crearOrden('OC-1915-ATRASADA', '2026-09-07', 'APROBADA');
        $hoy = $this->crearOrden('OC-1915-HOY', '2026-09-09', 'APROBADA');
        $futura = $this->crearOrden('OC-1915-FUTURA', '2026-09-12', 'APROBADA');
        $sinFecha = $this->crearOrden('OC-1915-SIN-FECHA', null, 'APROBADA');
        $recibida = $this->crearOrden('OC-1915-RECIBIDA', '2026-09-06', 'RECIBIDA', 10);

        $this->assertSame('ATRASADA', $atrasada->situacionEntrega());
        $this->assertSame('2 días de retraso', $atrasada->detallePlazoEntrega());
        $this->assertSame('VENCE_HOY', $hoy->situacionEntrega());
        $this->assertSame('EN_PLAZO', $futura->situacionEntrega());
        $this->assertSame('3 días restantes', $futura->detallePlazoEntrega());
        $this->assertSame('SIN_FECHA', $sinFecha->situacionEntrega());
        $this->assertSame('CERRADA', $recibida->situacionEntrega());
    }

    public function test_listado_resume_prioriza_y_ofrece_recepcion_directa_a_almacen(): void
    {
        $atrasada = $this->crearOrden('OC-1915-A', '2026-09-08', 'PARCIALMENTE_RECIBIDA', 4);
        $venceHoy = $this->crearOrden('OC-1915-B', '2026-09-09', 'APROBADA');
        $enPlazo = $this->crearOrden('OC-1915-C', '2026-09-15', 'APROBADA');
        $sinFecha = $this->crearOrden('OC-1915-D', null, 'APROBADA');
        $recibida = $this->crearOrden('OC-1915-E', '2026-09-07', 'RECIBIDA', 10);

        $this->actingAs($this->almacen)
            ->get(route('ordenes-compra.index'))
            ->assertOk()
            ->assertViewHas('resumen', fn (array $resumen): bool =>
                $resumen['recepcion'] === 4
                && $resumen['atrasadas'] === 1
                && $resumen['vence_hoy'] === 1
                && $resumen['parciales'] === 1
            )
            ->assertSeeInOrder([
                $atrasada->codigo,
                $venceHoy->codigo,
                $enPlazo->codigo,
                $sinFecha->codigo,
                $recibida->codigo,
            ])
            ->assertSee('Entregas atrasadas')
            ->assertSee('Vencen hoy')
            ->assertSee('Productos / saldo')
            ->assertSee('Recibir');
    }

    public function test_filtros_separan_cada_situacion_de_entrega(): void
    {
        $atrasada = $this->crearOrden('OC-1915-F-A', '2026-09-08', 'PARCIALMENTE_RECIBIDA', 2);
        $venceHoy = $this->crearOrden('OC-1915-F-B', '2026-09-09', 'APROBADA');
        $enPlazo = $this->crearOrden('OC-1915-F-C', '2026-09-10', 'APROBADA');
        $sinFecha = $this->crearOrden('OC-1915-F-D', null, 'APROBADA');
        $recibida = $this->crearOrden('OC-1915-F-E', '2026-09-08', 'RECIBIDA', 10);

        foreach ([
            'ATRASADA' => $atrasada,
            'VENCE_HOY' => $venceHoy,
            'EN_PLAZO' => $enPlazo,
            'SIN_FECHA' => $sinFecha,
        ] as $situacion => $esperada) {
            $respuesta = $this->actingAs($this->logistica)
                ->get(route('ordenes-compra.index', ['situacion' => $situacion]));

            $respuesta->assertOk()->assertSee($esperada->codigo);
            collect([$atrasada, $venceHoy, $enPlazo, $sinFecha, $recibida])
                ->reject(fn (OrdenCompra $orden): bool => $orden->is($esperada))
                ->each(fn (OrdenCompra $orden) => $respuesta->assertDontSee($orden->codigo));
        }

        $this->actingAs($this->logistica)
            ->get(route('ordenes-compra.index', ['situacion' => 'PENDIENTE']))
            ->assertOk()
            ->assertSee($atrasada->codigo)
            ->assertSee($venceHoy->codigo)
            ->assertSee($enPlazo->codigo)
            ->assertSee($sinFecha->codigo)
            ->assertDontSee($recibida->codigo);

        $this->actingAs($this->logistica)
            ->get(route('ordenes-compra.index', ['situacion' => 'PARCIAL']))
            ->assertOk()
            ->assertSee($atrasada->codigo)
            ->assertDontSee($venceHoy->codigo)
            ->assertDontSee($recibida->codigo);

        $this->actingAs($this->logistica)
            ->get(route('ordenes-compra.index', ['situacion' => 'RECIBIDA']))
            ->assertOk()
            ->assertSee($recibida->codigo)
            ->assertDontSee($atrasada->codigo)
            ->assertDontSee($venceHoy->codigo);
    }

    public function test_detalle_atrasado_muestra_aviso_y_acceso_a_nota_de_ingreso(): void
    {
        $orden = $this->crearOrden('OC-1915-DETALLE', '2026-09-06', 'PARCIALMENTE_RECIBIDA', 3);

        $this->actingAs($this->almacen)
            ->get(route('ordenes-compra.show', $orden))
            ->assertOk()
            ->assertSee('Entrega atrasada')
            ->assertSee('3 días de retraso')
            ->assertSee('Registrar recepción')
            ->assertSee(route('notas-ingreso.create', [
                'motivo_ingreso' => 'COMPRA',
                'orden_compra_id' => $orden->id,
            ]));
    }

    private function crearOrden(
        string $codigo,
        ?string $fechaEntrega,
        string $estado,
        float $cantidadRecibida = 0
    ): OrdenCompra {
        $this->secuencia++;
        $sufijo = str_pad((string) $this->secuencia, 3, '0', STR_PAD_LEFT);
        $cotizacion = Cotizacion::query()->create([
            'proveedor_id' => $this->proveedor->id,
            'codigo' => "CP-1915-{$sufijo}",
            'numero_documento' => "DOC-1915-{$sufijo}",
            'fecha_cotizacion' => now()->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => 1,
            'descuento_global_modo' => 'SIN_DESCUENTO',
            'subtotal' => 100,
            'descuento_global_monto' => 0,
            'impuesto' => 18,
            'total_calculado' => 118,
            'ajuste_redondeo' => 0,
            'total' => 118,
            'estado' => 'SELECCIONADA',
            'registrado_por' => $this->logistica->id,
        ]);
        $cotizacionDetalle = $cotizacion->detalles()->create([
            'tipo_vinculacion' => 'ADICIONAL',
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
            'codigo' => "SC-1915-{$sufijo}",
            'fecha_solicitud' => now()->toDateString(),
            'origen' => 'COMPRA_DIRECTA',
            'justificacion_origen' => 'Compra directa para probar el seguimiento de entrega.',
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
            'codigo' => $codigo,
            'origen' => 'COMPRA_DIRECTA',
            'justificacion_origen' => 'Compra directa para probar el seguimiento de entrega.',
            'fecha_emision' => now()->subDays(5)->toDateString(),
            'fecha_entrega_requerida' => $fechaEntrega,
            'moneda' => 'PEN',
            'tipo_cambio' => 1,
            'subtotal' => 100,
            'impuesto' => 18,
            'ajuste_redondeo' => 0,
            'total' => 118,
            'estado' => $estado,
            'emitido_por' => $this->logistica->id,
            'aprobado_por' => $this->logistica->id,
            'aprobado_en' => now(),
        ]);
        $orden->detalles()->create([
            'solicitud_compra_detalle_id' => $solicitudDetalle->id,
            'producto_id' => $this->producto->id,
            'cantidad_ordenada' => 10,
            'cantidad_recibida' => $cantidadRecibida,
            'precio_unitario' => 11.8,
            'descuento_porcentaje' => 0,
            'subtotal' => 118,
        ]);

        return $orden->refresh();
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
