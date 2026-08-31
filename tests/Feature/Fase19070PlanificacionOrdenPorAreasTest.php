<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\CotizacionPresupuesto;
use App\Models\OrdenOperacion;
use App\Models\Producto;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\Ordenes\AreasTrabajoOrdenService;
use App\Services\Ordenes\PlanificacionPorAreaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Fase19070PlanificacionOrdenPorAreasTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Cliente $cliente;
    private TipoOrden $tipoOp;
    private TipoOrden $tipoOs;
    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::query()->create([
            'role_id' => Role::query()->where('codigo', 'COMERCIAL_LOGISTICA')->firstOrFail()->id,
            'username' => 'logistica_19070',
            'email' => 'logistica_19070@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );
        $this->cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20619070001',
            'ruc' => '20619070001',
            'razon_social' => 'Cliente Planificación por Áreas SAC',
            'estado' => true,
        ]);
        $this->tipoOp = TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OP'],
            ['nombre' => 'Producción', 'estado' => true]
        );
        $this->tipoOs = TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OS'],
            ['nombre' => 'Servicio', 'estado' => true]
        );
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'MAT-19070',
            'descripcion' => 'Material común de prueba',
            'estado' => true,
        ]);
    }

    public function test_congela_varias_areas_dentro_de_una_sola_orden_y_agrega_por_producto(): void
    {
        $cotizacion = $this->cotizacion();
        $orden = $this->orden($cotizacion, $this->tipoOp, 'OP-19070-001');
        $servicio = app(PlanificacionPorAreaService::class);
        $tuberias = $servicio->crearArea($cotizacion, 'Tuberías y válvulas', 'EXCEL');
        $neumatico = $servicio->crearArea($cotizacion, 'Sistema neumático', 'EXCEL');

        $this->partidaMaterial($cotizacion, $tuberias->id, 2, 10);
        $this->partidaMaterial($cotizacion, $tuberias->id, 3, 12);
        $this->partidaMaterial($cotizacion, $neumatico->id, 1, 20);

        $this->assertSame(2, $servicio->congelar($cotizacion, $orden));
        $this->assertDatabaseCount('ordenes_operacion', 1);
        $this->assertDatabaseCount('orden_areas', 2);
        $this->assertDatabaseCount('materiales_planificados_orden_area', 2);
        $this->assertDatabaseHas('materiales_planificados_orden_area', [
            'orden_operacion_id' => $orden->id,
            'producto_id' => $this->producto->id,
            'cantidad_estimada' => 5,
            'costo_total_estimado_soles' => 56,
        ]);

        $areas = app(AreasTrabajoOrdenService::class)->areas($orden);
        $this->assertSame(['TUBERÍAS Y VÁLVULAS', 'SISTEMA NEUMÁTICO'], $areas->all());
        $planTuberias = app(AreasTrabajoOrdenService::class)
            ->materialesPlanificados($orden, 'Tuberías y válvulas');
        $this->assertSame(5.0, $planTuberias->get($this->producto->id));
    }

    public function test_servicio_sin_clasificar_bloquea_y_servicio_interno_puede_vincular_una_os_hija(): void
    {
        $cotizacion = $this->cotizacion();
        $ordenPrincipal = $this->orden($cotizacion, $this->tipoOp, 'OP-19070-002');
        $area = app(PlanificacionPorAreaService::class)
            ->crearArea($cotizacion, 'Sistema neumático');
        $servicio = $this->partidaServicio($cotizacion, null);

        try {
            app(PlanificacionPorAreaService::class)->congelar($cotizacion, $ordenPrincipal);
            $this->fail('Una cotización con servicios pendientes no debe congelarse.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('servicios', $exception->errors());
        }
        $this->assertDatabaseCount('orden_areas', 0);

        $servicio->update(['ejecucion_servicio' => 'INTERNO_HIDROIL']);
        $this->partidaMaterial($cotizacion, $area->id, 1, 10);
        app(PlanificacionPorAreaService::class)->congelar($cotizacion, $ordenPrincipal);

        $ordenHija = OrdenOperacion::query()->create([
            'tipo_orden_id' => $this->tipoOs->id,
            'cotizacion_cliente_id' => $cotizacion->id,
            'orden_padre_id' => $ordenPrincipal->id,
            'presupuesto_servicio_origen_id' => $servicio->id,
            'cliente_id' => $this->cliente->id,
            'codigo_orden' => 'OS-19070-001',
            'numero_correlativo' => 1,
            'anio' => (int) now()->format('Y'),
            'fecha_apertura' => now()->toDateString(),
            'descripcion' => 'Servicio interno HIDROIL',
            'estado' => 'ABIERTA',
            'creado_por' => $this->usuario->id,
        ]);

        $this->assertTrue($ordenHija->ordenPadre->is($ordenPrincipal));
        $this->assertTrue($ordenPrincipal->ordenesServicioInternas()->firstOrFail()->is($ordenHija));
        $this->assertTrue($servicio->ordenServicioGenerada->is($ordenHija));
    }

    private function cotizacion(): CotizacionCliente
    {
        return CotizacionCliente::query()->create([
            'origen' => 'DIRECTA_LOGISTICA',
            'cliente_id' => $this->cliente->id,
            'tipo_orden_id' => $this->tipoOp->id,
            'descripcion_trabajo' => 'Fabricación de cisterna',
            'codigo_base' => 'CC-19070',
            'version' => 1,
            'codigo' => 'CC-19070-' . fake()->unique()->numerify('####'),
            'cliente_documento' => $this->cliente->ruc,
            'cliente_nombre' => $this->cliente->razon_social,
            'fecha_emision' => now()->toDateString(),
            'moneda' => 'PEN',
            'subtotal' => 100,
            'impuesto' => 18,
            'total' => 118,
            'estado' => 'ABIERTA',
            'cotizado_por' => $this->usuario->id,
        ]);
    }

    private function orden(CotizacionCliente $cotizacion, TipoOrden $tipo, string $codigo): OrdenOperacion
    {
        return OrdenOperacion::query()->create([
            'tipo_orden_id' => $tipo->id,
            'cotizacion_cliente_id' => $cotizacion->id,
            'cliente_id' => $this->cliente->id,
            'codigo_orden' => $codigo,
            'numero_correlativo' => (int) preg_replace('/\D/', '', $codigo),
            'anio' => (int) now()->format('Y'),
            'fecha_apertura' => now()->toDateString(),
            'descripcion' => 'Orden principal de fabricación',
            'estado' => 'ABIERTA',
            'creado_por' => $this->usuario->id,
        ]);
    }

    private function partidaMaterial(
        CotizacionCliente $cotizacion,
        int $areaId,
        float $cantidad,
        float $costoUnitario
    ): CotizacionPresupuesto {
        return $this->partida($cotizacion, [
            'cotizacion_area_id' => $areaId,
            'producto_id' => $this->producto->id,
            'tipo_costo' => 'MATERIAL',
            'descripcion' => $this->producto->descripcion,
            'cantidad' => $cantidad,
            'unidad' => 'UND',
            'costo_unitario' => $costoUnitario,
            'costo_neto_original' => $cantidad * $costoUnitario,
            'costo_total_original' => $cantidad * $costoUnitario,
            'costo_neto_soles' => $cantidad * $costoUnitario,
            'costo_total_soles' => $cantidad * $costoUnitario,
            'costo_neto_dolares' => 0,
            'costo_total_dolares' => 0,
        ]);
    }

    private function partidaServicio(CotizacionCliente $cotizacion, ?string $ejecucion): CotizacionPresupuesto
    {
        return $this->partida($cotizacion, [
            'tipo_costo' => 'SERVICIO_TERCERO',
            'ejecucion_servicio' => $ejecucion,
            'descripcion' => 'Prueba especializada',
            'cantidad' => 1,
            'unidad' => 'SERVICIO',
            'costo_unitario' => 100,
            'costo_neto_original' => 100,
            'costo_total_original' => 100,
            'costo_neto_soles' => 100,
            'costo_total_soles' => 100,
            'costo_neto_dolares' => 0,
            'costo_total_dolares' => 0,
        ]);
    }

    private function partida(CotizacionCliente $cotizacion, array $datos): CotizacionPresupuesto
    {
        return CotizacionPresupuesto::query()->create([
            'cotizacion_cliente_id' => $cotizacion->id,
            'producto_id' => null,
            'tipo_costo' => 'OTRO',
            'descripcion' => 'Partida',
            'cantidad' => 1,
            'unidad' => 'UNIDAD',
            'moneda' => 'PEN',
            'tipo_cambio' => 1,
            'costo_unitario' => 0,
            'carga_social_porcentaje' => 0,
            'carga_social_original' => 0,
            'igv_modo' => 'NO_APLICA',
            'igv_porcentaje' => 18,
            'costo_neto_original' => 0,
            'igv_original' => 0,
            'costo_total_original' => 0,
            'costo_neto_soles' => 0,
            'igv_soles' => 0,
            'costo_total_soles' => 0,
            'costo_neto_dolares' => 0,
            'igv_dolares' => 0,
            'costo_total_dolares' => 0,
            'estado' => 'VIGENTE',
            'registrado_por' => $this->usuario->id,
            'registrado_en' => now(),
            ...$datos,
        ]);
    }
}
