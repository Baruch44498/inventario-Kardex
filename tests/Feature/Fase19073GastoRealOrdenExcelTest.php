<?php

namespace Tests\Feature;

use App\Models\{Cliente, CostoDirectoOrden, CotizacionCliente, CotizacionPresupuesto, MaterialPlanificadoOrdenArea, MaterialRequeridoOrden, NotaIngreso, NotaIngresoDetalle, NotaSalida, NotaSalidaDetalle, OrdenArea, OrdenOperacion, Producto, Repisa, Role, TipoCliente, TipoOrden, UnidadMedida, User};
use App\Services\Ordenes\{ExportarGastoRealOrdenService, GastoRealOrdenService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class Fase19073GastoRealOrdenExcelTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private OrdenOperacion $orden;
    private CotizacionCliente $cotizacion;
    private Producto $producto;
    private Repisa $repisa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::create([
            'role_id' => Role::where('codigo', 'ADMINISTRADOR')->firstOrFail()->id,
            'username' => 'admin_19073',
            'email' => 'admin19073@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
        $tipoCliente = TipoCliente::firstOrCreate(['codigo' => 'FINAL'], ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]);
        $cliente = Cliente::create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20619073001',
            'ruc' => '20619073001',
            'razon_social' => 'Cliente 19073',
            'estado' => true
        ]);
        $tipo = TipoOrden::updateOrCreate(['codigo' => 'OP'], ['nombre' => 'Producción', 'estado' => true]);
        $this->cotizacion = CotizacionCliente::create([
            'origen' => 'DIRECTA_LOGISTICA',
            'tipo_orden_id' => $tipo->id,
            'cliente_id' => $cliente->id,
            'codigo_base' => 'COT-19073',
            'version' => 1,
            'codigo' => 'COT-19073',
            'fecha_emision' => today(),
            'cliente_documento' => $cliente->ruc,
            'cliente_nombre' => $cliente->razon_social,
            'moneda' => 'PEN',
            'tipo_cambio' => 1,
            'subtotal' => 1000,
            'impuesto' => 180,
            'total' => 1180,
            'estado' => 'CONVERTIDA_EN_ORDEN',
            'cotizado_por' => $this->usuario->id,
        ]);
        $this->orden = OrdenOperacion::create([
            'tipo_orden_id' => $tipo->id,
            'cliente_id' => $cliente->id,
            'cotizacion_cliente_id' => $this->cotizacion->id,
            'codigo_orden' => 'OP-19073',
            'numero_correlativo' => 19073,
            'anio' => 2026,
            'fecha_apertura' => today(),
            'descripcion' => 'Orden de prueba',
            'estado' => 'EN_PROCESO',
            'creado_por' => $this->usuario->id,
        ]);
        $this->cotizacion->update(['orden_operacion_id' => $this->orden->id]);
        $unidad = UnidadMedida::firstOrCreate(['codigo' => 'UND'], ['nombre' => 'Unidad', 'estado' => true]);
        $this->producto = Producto::create(['unidad_medida_id' => $unidad->id, 'codigo' => '00019073', 'descripcion' => '=1+1', 'estado' => true]);
        $this->repisa = Repisa::create(['codigo' => 'R19073', 'descripcion' => 'Repisa', 'estado' => true]);
    }

    public function test_valoriza_areas_sin_restar_malogrados_ni_contar_notas_anuladas_o_herramientas(): void
    {
        $area = $this->area('SISTEMA NEUMÁTICO');
        $otra = $this->area('TUBERÍAS');
        $this->plan($area, 10, 100);
        $this->plan($otra, 2, 20);
        $salida = $this->salida($area, 12, 144);
        $this->salida($otra, 1, 15);
        $this->retorno($salida, 2, 24, 'RETORNO_MATERIAL');
        $this->retorno($salida, 1, 12, 'DEVOLUCION_MATERIAL_MALOGRADO');
        $this->retorno($salida, 4, 48, 'RETORNO_MATERIAL', 'ANULADA');
        $this->salida($area, 99, 990, 'ANULADA');
        $this->salida($area, 50, 500, 'CONFIRMADA', 'USO_TEMPORAL');

        $r = app(GastoRealOrdenService::class)->construir($this->orden);
        $this->assertCount(2, $r['materiales']);
        $neumatico = collect($r['materiales'])->firstWhere('area', 'SISTEMA NEUMÁTICO');
        $this->assertSame(10.0, $neumatico['real']);
        $this->assertSame(1.0, $neumatico['malogrado']);
        $this->assertSame(120.0, $neumatico['costo_real']);
        $this->assertSame(20.0, $neumatico['diferencia_costo']);
        $this->assertSame(135.0, $r['totales']['costo_real']);
        $this->assertSame(120.0, $r['totales']['costo_estimado']);
        $this->assertSame(15.0, $r['totales']['diferencia']);
        $this->assertCount(4, $r['movimientos']);
        $this->assertEquals($r['totales']['materiales_reales'], array_sum(array_column($r['areas'], 'real')));
    }

    public function test_consolida_os_y_distingue_servicios_estimados_de_costos_reales(): void
    {
        $servicio = $this->presupuesto(200, 'INTERNO_HIDROIL');
        $this->presupuesto(75, 'EXTERNO');
        $os = $this->hija($servicio);
        $area = $this->area('SERVICIO INTERNO', $os);
        $this->plan($area, 1, 25);
        $this->salida($area, 1, 30);
        $this->costo($os, 40);
        $this->costo($this->orden, 10);
        $this->costo($os, 999, 'ANULADO');
        $anulada = $this->hija(null, 'ANULADA');
        $this->costo($anulada, 999);

        $r = app(GastoRealOrdenService::class)->construir($this->orden);
        $this->assertCount(2, $r['ordenes']);
        $this->assertSame(275.0, $r['totales']['otros_estimados']);
        $this->assertSame(275.0, $r['totales']['costo_estimado']);
        $this->assertSame(50.0, $r['totales']['otros_reales']);
        $this->assertSame(80.0, $r['totales']['costo_real']);
        $this->assertSame(1000.0, $r['totales']['ingreso']);
        $this->assertSame(920.0, $r['totales']['utilidad_registrada']);
        $hija = app(GastoRealOrdenService::class)->construir($os);
        $this->assertNull($hija['totales']['ingreso']);
        $this->assertSame(70.0, $hija['totales']['costo_real']);
        $this->assertSame(200.0, $hija['totales']['otros_estimados']);
        $this->assertSame(200.0, $hija['totales']['costo_estimado']);
    }

    public function test_venta_usd_se_convierte_una_vez_y_el_tipo_de_cambio_invalido_no_inventa_utilidad(): void
    {
        $this->cotizacion->update(['moneda' => 'USD', 'subtotal' => 100, 'tipo_cambio' => 3.8]);
        $this->costo($this->orden, 80);
        $r = app(GastoRealOrdenService::class)->construir($this->orden->fresh());
        $this->assertSame(380.0, $r['totales']['ingreso']);
        $this->assertSame(300.0, $r['totales']['utilidad_registrada']);
        $this->cotizacion->update(['tipo_cambio' => null]);
        $r = app(GastoRealOrdenService::class)->construir($this->orden->fresh());
        $this->assertNull($r['totales']['ingreso']);
        $this->assertNull($r['totales']['utilidad_registrada']);
    }

    public function test_no_inventa_costos_heredados_ni_modifica_el_cierre_guardado(): void
    {
        MaterialRequeridoOrden::create([
            'orden_operacion_id' => $this->orden->id,
            'producto_id' => $this->producto->id,
            'cantidad_requerida' => 3,
            'cantidad_prevista' => 3,
            'creado_por' => $this->usuario->id
        ]);
        $this->orden->update(['estado' => 'CERRADA', 'costo_real_cierre_soles' => 99]);
        $r = app(GastoRealOrdenService::class)->construir($this->orden);
        $this->assertNull($r['totales']['costo_estimado']);
        $this->assertNull($r['totales']['diferencia']);
        $this->assertSame(99.0, $r['ordenes'][0]['cierre_guardado']);
        $this->assertSame('99.0000', $this->orden->fresh()->costo_real_cierre_soles);
    }

    public function test_separa_subareas_con_el_mismo_nombre_y_respeta_el_area_de_la_salida_en_retornos(): void
    {
        $padreA = $this->area('A');
        $padreB = $this->area('B');
        $areaA = $this->area('MONTAJE', null, $padreA->id);
        $areaB = $this->area('MONTAJE', null, $padreB->id);
        $this->plan($areaA, 2, 20);
        $this->plan($areaB, 3, 30);
        $s = $this->salida($areaA, 2, 24);
        $this->salida($areaB, 3, 39);
        $retorno = $this->retorno($s, 1, 12, 'RETORNO_MATERIAL');
        $retorno->notaIngreso->update(['orden_area_id' => $areaB->id, 'area_trabajo' => 'B / MONTAJE']);
        $r = app(GastoRealOrdenService::class)->construir($this->orden);
        $this->assertCount(2, $r['areas']);
        $this->assertSame(12.0, collect($r['areas'])->firstWhere('area', 'A / MONTAJE')['real']);
        $this->assertSame(39.0, collect($r['areas'])->firstWhere('area', 'B / MONTAJE')['real']);
    }

    public function test_excel_conserva_numeros_y_textos_seguros_y_coincide_con_la_consulta(): void
    {
        $area = $this->area('TUBERÍAS');
        $this->plan($area, 2, 20);
        $this->salida($area, 1, 12.3456);
        $r = app(GastoRealOrdenService::class)->construir($this->orden);
        $libro = app(ExportarGastoRealOrdenService::class)->libro($r);
        $ruta = tempnam(sys_get_temp_dir(), 'gasto19073_');
        try {
            (new Xlsx($libro))->save($ruta);
            $leido = IOFactory::load($ruta);
            $this->assertCount(9, $leido->getSheetNames());
            $this->assertSame('Gasto real', $leido->getActiveSheet()->getTitle());
            $this->assertEqualsWithDelta($r['totales']['costo_real'], $leido->getActiveSheet()->getCell('F7')->getCalculatedValue(), 0.00001);
            $materiales = $leido->getSheetByName('Materiales');
            $this->assertSame('00019073', $materiales->getCell('C5')->getValue());
            $this->assertSame('=1+1', $materiales->getCell('D5')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $materiales->getCell('D5')->getDataType());
            $this->assertSame(DataType::TYPE_NUMERIC, $materiales->getCell('P5')->getDataType());
            $this->assertEqualsWithDelta(12.3456, $materiales->getCell('P5')->getValue(), 0.00001);
            $this->assertSame('12.35', $materiales->getCell('P5')->getFormattedValue());
            $this->assertEquals($r['totales']['costo_real'], $leido->getSheetByName('Resumen')->getCell('B10')->getValue());
            $leido->disconnectWorksheets();
        } finally {
            $libro->disconnectWorksheets();
            if (is_file($ruta)) {
                unlink($ruta);
            }
        }
        $this->actingAs($this->usuario)->get(route('ordenes-operacion.gasto-real', $this->orden))
            ->assertOk()->assertSee('Descargar Excel de gasto real')->assertSee('12.35');
        $respuesta = $this->get(route('ordenes-operacion.gasto-real.excel', $this->orden));
        $respuesta->assertOk()->assertDownload('GASTO_REAL_ORDEN_' . $this->orden->id . '.xlsx');
        $this->assertStringStartsWith('PK', $respuesta->streamedContent());
    }

    public function test_consulta_y_descarga_exigen_permiso_de_costos(): void
    {
        $usuario = User::create([
            'role_id' => Role::where('codigo', 'ALMACEN')->firstOrFail()->id,
            'username' => 'almacen19073',
            'email' => 'almacen19073@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now()
        ]);
        $this->assertFalse($usuario->puede('ordenes.ver_costos'));
        $this->actingAs($usuario)->get(route('ordenes-operacion.gasto-real', $this->orden))->assertForbidden();
        $this->get(route('ordenes-operacion.gasto-real.excel', $this->orden))->assertForbidden();
    }

    private function area(string $nombre, ?OrdenOperacion $orden = null, ?int $padreId = null): OrdenArea
    {
        return OrdenArea::create([
            'orden_operacion_id' => ($orden ?? $this->orden)->id,
            'area_padre_id' => $padreId,
            'nombre' => $nombre,
            'nombre_normalizado' => $nombre,
            'orden_secuencia' => OrdenArea::count() + 1,
            'origen' => 'MANUAL',
            'estado' => 'ACTIVA'
        ]);
    }

    private function plan(OrdenArea $area, float $cantidad, float $total): void
    {
        MaterialPlanificadoOrdenArea::create([
            'orden_operacion_id' => $area->orden_operacion_id,
            'orden_area_id' => $area->id,
            'producto_id' => $this->producto->id,
            'codigo_producto' => $this->producto->codigo,
            'descripcion_producto' => $this->producto->descripcion,
            'unidad' => 'UND',
            'cantidad_estimada' => $cantidad,
            'costo_unitario_estimado_soles' => $total / $cantidad,
            'costo_total_estimado_soles' => $total,
            'congelado_en' => now()
        ]);
    }

    private function salida(OrdenArea $area, float $cantidad, float $total, string $estado = 'CONFIRMADA', string $tratamiento = 'CONSUMO'): NotaSalidaDetalle
    {
        $nota = NotaSalida::create([
            'orden_operacion_id' => $area->orden_operacion_id,
            'orden_area_id' => $area->id,
            'area_trabajo' => $area->nombre,
            'codigo' => 'NS-19073-' . (NotaSalida::count() + 1),
            'fecha_salida' => today(),
            'estado' => $estado,
            'registrado_por' => $this->usuario->id
        ]);
        return NotaSalidaDetalle::create([
            'nota_salida_id' => $nota->id,
            'producto_id' => $this->producto->id,
            'repisa_id' => $this->repisa->id,
            'cantidad' => $cantidad,
            'costo_unitario_promedio' => $total / $cantidad,
            'subtotal' => $total,
            'tratamiento' => $tratamiento
        ]);
    }

    private function retorno(NotaSalidaDetalle $salida, float $cantidad, float $total, string $motivo, string $estado = 'CONFIRMADA'): NotaIngresoDetalle
    {
        $nota = NotaIngreso::create([
            'orden_operacion_id' => $salida->notaSalida->orden_operacion_id,
            'nota_salida_id' => $salida->nota_salida_id,
            'codigo' => 'NI-19073-' . (NotaIngreso::count() + 1),
            'fecha_ingreso' => today(),
            'motivo_ingreso' => $motivo,
            'estado' => $estado,
            'registrado_por' => $this->usuario->id
        ]);
        return NotaIngresoDetalle::create([
            'nota_ingreso_id' => $nota->id,
            'nota_salida_detalle_id' => $salida->id,
            'producto_id' => $this->producto->id,
            'repisa_id' => $this->repisa->id,
            'cantidad' => $cantidad,
            'costo_unitario' => $total / $cantidad,
            'subtotal' => $total,
            'afecta_stock' => $motivo === 'RETORNO_MATERIAL'
        ]);
    }

    private function costo(OrdenOperacion $orden, float $total, string $estado = 'VIGENTE'): void
    {
        CostoDirectoOrden::create([
            'orden_operacion_id' => $orden->id,
            'tipo' => 'MANO_OBRA',
            'fecha_costo' => today(),
            'descripcion' => 'Trabajo',
            'cantidad' => 1,
            'unidad' => 'HORA',
            'costo_unitario_soles' => $total,
            'total_soles' => $total,
            'estado' => $estado,
            'registrado_por' => $this->usuario->id,
            'registrado_en' => now()
        ]);
    }

    private function hija(?CotizacionPresupuesto $origen, string $estado = 'EN_PROCESO'): OrdenOperacion
    {
        $tipo = TipoOrden::updateOrCreate(['codigo' => 'OS'], ['nombre' => 'Servicio', 'estado' => true]);
        return OrdenOperacion::create([
            'tipo_orden_id' => $tipo->id,
            'cliente_id' => $this->orden->cliente_id,
            'cotizacion_cliente_id' => $this->cotizacion->id,
            'orden_padre_id' => $this->orden->id,
            'presupuesto_servicio_origen_id' => $origen?->id,
            'codigo_orden' => 'OS-19073-' . OrdenOperacion::count(),
            'numero_correlativo' => OrdenOperacion::count(),
            'anio' => 2026,
            'fecha_apertura' => today(),
            'descripcion' => 'Servicio interno',
            'estado' => $estado,
            'creado_por' => $this->usuario->id
        ]);
    }

    private function presupuesto(float $total, string $ejecucion): CotizacionPresupuesto
    {
        return CotizacionPresupuesto::create([
            'cotizacion_cliente_id' => $this->cotizacion->id,
            'tipo_costo' => 'SERVICIO_TERCERO',
            'ejecucion_servicio' => $ejecucion,
            'descripcion' => 'Servicio presupuestado',
            'cantidad' => 1,
            'unidad' => 'SERVICIO',
            'moneda' => 'PEN',
            'tipo_cambio' => 1,
            'costo_unitario' => $total,
            'igv_modo' => 'NO_APLICA',
            'igv_porcentaje' => 0,
            'costo_total_soles' => $total,
            'costo_neto_original' => $total,
            'costo_total_original' => $total,
            'costo_neto_soles' => $total,
            'costo_neto_dolares' => $total,
            'costo_total_dolares' => $total,
            'estado' => 'VIGENTE',
            'registrado_por' => $this->usuario->id,
            'registrado_en' => now()
        ]);
    }
}
