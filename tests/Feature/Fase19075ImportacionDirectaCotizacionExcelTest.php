<?php

namespace Tests\Feature;

use App\Models\{Cliente, CotizacionCliente, Empleado, ImportacionPlantillaCosteo, Inventario, NotaSalida, OrdenOperacion, PlantillaCosteo, Producto, Repisa, Role, TipoCliente, TipoOrden, UnidadMedida, User};
use App\Services\Ordenes\{GastoRealOrdenService, PlanificacionPorAreaService};
use App\Services\Ventas\{ExportarCotizacionCosteoExcelService, ExtractorPlantillaCosteoExcel, PresupuestoCotizacionService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class Fase19075ImportacionDirectaCotizacionExcelTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Cliente $cliente;
    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->usuario = $this->usuario('COMERCIAL_LOGISTICA', 'logistica_excel');
        $tipoCliente = TipoCliente::firstOrCreate(['codigo' => 'FINAL'], ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]);
        $this->cliente = Cliente::create(['tipo_cliente_id' => $tipoCliente->id, 'tipo_documento' => 'RUC',
            'numero_documento' => '20619075001', 'ruc' => '20619075001', 'razon_social' => 'Cliente Excel SAC', 'estado' => true]);
        $unidad = UnidadMedida::firstOrCreate(['codigo' => 'UND'], ['nombre' => 'Unidad', 'estado' => true]);
        $this->producto = Producto::create(['unidad_medida_id' => $unidad->id, 'codigo' => '00017',
            'descripcion' => '=Material de prueba', 'estado' => true, 'permite_fraccionamiento' => false]);
        $this->actingAs($this->usuario);
    }

    public function test_editar_y_agregar_costos_conserva_subareas_homonimas_por_id(): void
    {
        $cotizacion = $this->cotizacion();
        $componente = $cotizacion->componentes()->create([
            'tipo_orden_id' => $cotizacion->tipo_orden_id,
            'descripcion_componente' => 'Fabricación de prueba',
            'tipo_cambio_comparacion' => 3.8,
            'orden_secuencia' => 1,
        ]);
        $plan = app(PlanificacionPorAreaService::class);
        $hijaA = $plan->crearArea($cotizacion, 'MONTAJE', 'MANUAL', $plan->crearArea($cotizacion, 'ESTRUCTURA'));
        $hijaB = $plan->crearArea($cotizacion, 'MONTAJE', 'MANUAL', $plan->crearArea($cotizacion, 'NEUMÁTICA'));
        $datosMaterial = [
            'componente_id' => $componente->id, 'tipo_costo' => 'MATERIAL',
            'producto_id' => $this->producto->id, 'descripcion' => $this->producto->descripcion,
            'cantidad' => 1, 'unidad' => 'UND', 'moneda' => 'PEN',
            'tipo_cambio' => 3.8, 'costo_unitario' => 10,
            'margen_porcentaje' => 20, 'igv_modo' => 'NO_APLICA',
        ];
        $partida = app(PresupuestoCotizacionService::class)->registrar(
            $cotizacion,
            [...$datosMaterial, 'cotizacion_area_id' => $hijaA->id, 'igv_porcentaje' => 0, 'igv_venta_porcentaje' => 18],
            $this->usuario
        );

        $this->get(route('cotizacion-presupuestos.edit', $partida))
            ->assertOk()->assertSee('ESTRUCTURA / MONTAJE')->assertSee('NEUMÁTICA / MONTAJE');
        $this->put(route('cotizacion-presupuestos.update', $partida), [
            ...$datosMaterial, 'cotizacion_area_id' => $hijaA->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($hijaA->id, $partida->fresh()->cotizacion_area_id);

        $this->post(route('cotizaciones-cliente.presupuesto.materiales.store', $cotizacion), [
            'componente_id' => $componente->id, 'cotizacion_area_id' => $hijaB->id,
            'area_nombre' => 'MONTAJE', 'moneda' => 'PEN', 'igv_modo' => 'NO_APLICA',
            'materiales' => [['producto_id' => $this->producto->id, 'cantidad' => 2, 'costo_unitario' => 15]],
        ])->assertSessionHasNoErrors();
        $this->assertSame($hijaB->id, $cotizacion->presupuestos()->latest('id')->firstOrFail()->cotizacion_area_id);

        $this->get(route('cotizaciones-cliente.presupuesto.show', [
            'cotizacionCliente' => $cotizacion, 'paso' => 'costos', 'area_id' => $hijaB->id,
            'tipo_costo' => 'SERVICIO_TERCERO',
        ]))->assertOk()->assertSee('NEUMÁTICA / MONTAJE');
        $this->post(route('cotizaciones-cliente.presupuesto.store', $cotizacion), [
            'componente_id' => $componente->id, 'tipo_costo' => 'SERVICIO_TERCERO',
            'cotizacion_area_id' => $hijaB->id, 'ejecucion_servicio' => 'EXTERNO',
            'descripcion' => 'Servicio montaje', 'cantidad' => 1, 'unidad' => 'SERVICIO',
            'moneda' => 'PEN', 'costo_unitario' => 50, 'igv_modo' => 'NO_APLICA',
        ])->assertSessionHasNoErrors();
        $this->assertSame($hijaB->id, $cotizacion->presupuestos()->latest('id')->firstOrFail()->cotizacion_area_id);
        $this->assertSame(4, $cotizacion->todasLasAreas()->count());
    }

    public function test_no_admite_subarea_de_otra_cotizacion_ni_area_nueva_y_existente_a_la_vez(): void
    {
        $cotizacion = $this->cotizacion();
        $externa = app(PlanificacionPorAreaService::class)->crearArea($this->cotizacion(), 'AJENA');
        $datos = [
            'tipo_costo' => 'SERVICIO_TERCERO', 'ejecucion_servicio' => 'EXTERNO',
            'descripcion' => 'Servicio', 'cantidad' => 1, 'unidad' => 'SERVICIO',
            'moneda' => 'PEN', 'costo_unitario' => 10, 'igv_modo' => 'NO_APLICA',
        ];
        $this->post(route('cotizaciones-cliente.presupuesto.store', $cotizacion), [
            ...$datos, 'cotizacion_area_id' => $externa->id,
        ])->assertSessionHasErrors('cotizacion_area_id');
        $local = app(PlanificacionPorAreaService::class)->crearArea($cotizacion, 'LOCAL');
        $this->post(route('cotizaciones-cliente.presupuesto.store', $cotizacion), [
            ...$datos, 'cotizacion_area_id' => $local->id, 'area_nombre' => 'OTRA',
        ])->assertSessionHasErrors('area_nombre');
        $this->assertSame(0, $cotizacion->presupuestos()->count());
    }

    public function test_importa_directamente_para_om_os_op_sin_crear_plantilla_y_sin_confirmar_dos_veces(): void
    {
        foreach (['OM', 'OS', 'OP'] as $tipo) {
            $cotizacion = $this->cotizacion($tipo);
            $this->get(route('cotizaciones-cliente.excel.create', $cotizacion))->assertOk()->assertSee('Importar Excel en '.$cotizacion->codigo);
            $importacion = $this->subir($cotizacion, $this->archivo());
            $this->assertSame($cotizacion->id, (int) $importacion->cotizacion_cliente_id);
            $this->get(route('plantillas-costeo.importaciones.show', $importacion))->assertOk()->assertSee('Confirmar y añadir a cotización');
            $this->post(route('plantillas-costeo.importaciones.confirmar', $importacion))
                ->assertSessionHasNoErrors()->assertRedirect(route('cotizaciones-cliente.presupuesto.show', ['cotizacionCliente' => $cotizacion, 'paso' => 'revision']));
            $this->assertSame(1, $cotizacion->presupuestos()->count());
            $this->assertSame(2, $cotizacion->todasLasAreas()->count());
            $linea = $cotizacion->presupuestos()->firstOrFail();
            $this->assertSame('236.0000', $linea->costo_total_soles);
            $this->assertSame('MANTA', $linea->area->nombre);
            $this->assertSame('ESTRUCTURA', $linea->area->padre->nombre);
            $this->assertNull($cotizacion->fresh()->costeo_sincronizado_en);
            $this->post(route('plantillas-costeo.importaciones.confirmar', $importacion))->assertSessionHasErrors('importacion');
            $this->assertSame(1, $cotizacion->presupuestos()->count());
        }
        $this->assertDatabaseCount('plantillas_costeo', 0);
        $this->assertDatabaseCount('ordenes_operacion', 0);
    }

    public function test_excel_directo_usa_margen_y_tipo_de_cambio_de_la_cotizacion_para_la_venta(): void
    {
        $cotizacion = $this->cotizacion();
        $cotizacion->update(['margen_cliente_porcentaje' => 25, 'tipo_cambio' => 4]);
        // El archivo de prueba trae margen 10% y TC 3.8.
        $importacion = $this->subir($cotizacion, $this->archivo());
        $this->post(route('plantillas-costeo.importaciones.confirmar', $importacion))
            ->assertSessionHasNoErrors();

        $partida = $cotizacion->presupuestos()->sole();
        $this->assertSame(25.0, (float) $partida->margen_porcentaje);
        $this->assertSame(4.0, (float) $partida->tipo_cambio);
        $this->assertSame(236.0, (float) $partida->costo_total_soles);
        $this->assertSame(250.0, (float) $partida->precio_venta_neto_soles);
    }

    public function test_fila_pendiente_revierte_toda_la_carga_y_omitirla_permite_confirmar(): void
    {
        $cotizacion = $this->cotizacion();
        $importacion = $this->subir($cotizacion, $this->archivo(true));
        $this->post(route('plantillas-costeo.importaciones.confirmar', $importacion))->assertSessionHasErrors('importacion');
        $this->assertSame(0, $cotizacion->presupuestos()->count());
        $this->assertSame(0, $cotizacion->todasLasAreas()->count());
        $this->assertSame('BORRADOR', $importacion->fresh()->estado);
        $pendiente = $importacion->partidas()->whereNull('producto_id')->firstOrFail();
        $this->patch(route('plantillas-costeo.importaciones.partidas.update', $pendiente), ['accion' => 'OMITIR'])->assertSessionHasNoErrors();
        $this->post(route('plantillas-costeo.importaciones.confirmar', $importacion))->assertSessionHasNoErrors();
        $this->assertSame(1, $cotizacion->presupuestos()->count());
    }

    public function test_cotizacion_cerrada_y_usuario_ajeno_no_pueden_confirmar(): void
    {
        $cotizacion = $this->cotizacion();
        $importacion = $this->subir($cotizacion, $this->archivo());
        $otro = $this->usuario('COMERCIAL_LOGISTICA', 'otro_excel');
        $this->actingAs($otro)->post(route('plantillas-costeo.importaciones.confirmar', $importacion))->assertForbidden();
        $this->actingAs($this->usuario);
        $cotizacion->update(['estado' => 'CERRADA']);
        $this->post(route('plantillas-costeo.importaciones.confirmar', $importacion))->assertSessionHasErrors('importacion');
        $this->assertSame(0, $cotizacion->presupuestos()->count());
        $almacen = $this->usuario('ALMACEN', 'almacen_excel');
        $this->actingAs($almacen)->get(route('cotizaciones-cliente.excel.download', $cotizacion))->assertForbidden();
        $this->get(route('cotizaciones-cliente.excel.create', $cotizacion))->assertForbidden();
    }

    public function test_servicio_importado_requiere_clasificacion_y_se_agrega_como_presupuesto(): void
    {
        $cotizacion = $this->cotizacion();
        $importacion = $this->subir($cotizacion, $this->archivo(false, true));
        $this->post(route('plantillas-costeo.importaciones.confirmar', $importacion))->assertSessionHasErrors('importacion');
        $servicio = $importacion->partidas()->where('tipo_costo', 'SERVICIO_TERCERO')->firstOrFail();
        $this->patch(route('plantillas-costeo.importaciones.partidas.update', $servicio), [
            'accion' => 'GUARDAR', 'tipo_costo' => 'SERVICIO_TERCERO', 'ejecucion_servicio' => 'EXTERNO',
            'unidad' => 'SERVICIO', 'grupo_costo' => 'SERVICIOS', 'descripcion' => 'Servicio externo',
            'cantidad' => 1, 'costo_unitario' => 50, 'moneda' => 'PEN', 'igv_modo' => 'INCLUIDO',
        ])->assertSessionHasNoErrors();
        $this->post(route('plantillas-costeo.importaciones.confirmar', $importacion))->assertSessionHasNoErrors();
        $this->assertSame(2, $cotizacion->presupuestos()->count());
        $this->assertSame('EXTERNO', $cotizacion->presupuestos()->where('tipo_costo', 'SERVICIO_TERCERO')->firstOrFail()->ejecucion_servicio);
        $this->assertDatabaseCount('ordenes_operacion', 0);
    }

    public function test_descarga_y_reimportacion_preservan_moneda_igv_cargas_y_subareas_homonimas(): void
    {
        $cotizacion = $this->cotizacion();
        $plan = app(PlanificacionPorAreaService::class);
        $presupuestos = app(PresupuestoCotizacionService::class);
        $datos = ['tipo_costo' => 'MATERIAL', 'producto_id' => $this->producto->id, 'descripcion' => $this->producto->descripcion,
            'cantidad' => 2, 'unidad' => 'UND', 'moneda' => 'USD', 'tipo_cambio' => 3.8, 'costo_unitario' => 12.3456,
            'margen_porcentaje' => 10, 'igv_modo' => 'AGREGAR', 'igv_porcentaje' => 18, 'igv_venta_porcentaje' => 18, 'carga_social_porcentaje' => 0];
        foreach (['A', 'B'] as $nombre) {
            $padre = $plan->crearArea($cotizacion, $nombre);
            $hija = $plan->crearArea($cotizacion, 'MONTAJE', 'MANUAL', $padre);
            $presupuestos->registrar($cotizacion, [...$datos, 'cotizacion_area_id' => $hija->id], $this->usuario);
        }
        $presupuestos->registrar($cotizacion, [...$datos, 'tipo_costo' => 'MANO_OBRA', 'producto_id' => null,
            'descripcion' => 'Personal PLAME', 'unidad' => 'DIA', 'grupo_costo' => 'PERSONAL', 'igv_modo' => 'NO_APLICA',
            'igv_porcentaje' => 0, 'carga_social_porcentaje' => 35], $this->usuario);
        $ruta = tempnam(sys_get_temp_dir(), 'costeo_roundtrip_');
        $libro = app(ExportarCotizacionCosteoExcelService::class)->libro($cotizacion);
        try {
            (new Xlsx($libro))->save($ruta);
            $extraido = app(ExtractorPlantillaCosteoExcel::class)->extraer($ruta);
            $this->assertCount(3, $extraido['partidas']);
            $this->assertSame(['A', 'MONTAJE'], $extraido['partidas'][0]['ruta_areas']);
            $this->assertSame(['B', 'MONTAJE'], $extraido['partidas'][1]['ruta_areas']);
            $this->assertSame('USD', $extraido['partidas'][0]['moneda']);
            $this->assertEquals(12.3456, $extraido['partidas'][0]['costo_unitario']);
            $this->assertSame('AGREGAR', $extraido['partidas'][0]['igv_modo']);
            $this->assertEquals(35, $extraido['partidas'][2]['carga_social_porcentaje']);
            $destino = $this->cotizacion();
            $importacion = $this->subir($destino, $ruta, false);
            $this->post(route('plantillas-costeo.importaciones.confirmar', $importacion))->assertSessionHasNoErrors();
            $this->assertEqualsWithDelta((float) $cotizacion->presupuestos()->sum('costo_total_soles'),
                (float) $destino->presupuestos()->sum('costo_total_soles'), 0.0001);
            $this->get(route('cotizaciones-cliente.excel.download', $destino))->assertOk()->assertDownload('COSTEO_COTIZACION_'.$destino->id.'.xlsx');
            // Si el ingeniero edita importes o títulos, no recuperar datos de origen desactualizados.
            $hoja = $libro->getActiveSheet();
            $hoja->setCellValue('C5', 'ÁREA EDITADA')->setCellValue('F7', 200)->setCellValue('G7', 400);
            (new Xlsx($libro))->save($ruta);
            $editado = app(ExtractorPlantillaCosteoExcel::class)->extraer($ruta);
            $this->assertSame('PEN', $editado['partidas'][0]['moneda']);
            $this->assertEquals(200, $editado['partidas'][0]['costo_unitario']);
            $this->assertSame(['ÁREA EDITADA', 'MONTAJE'], $editado['partidas'][0]['ruta_areas']);
            $this->assertNotEmpty($editado['advertencias']);
        } finally {
            $libro->disconnectWorksheets();
            if (is_file($ruta)) { unlink($ruta); }
        }
    }

    public function test_el_mismo_archivo_retoma_el_borrador_y_no_se_importa_de_nuevo_despues_de_confirmar(): void
    {
        $cotizacion = $this->cotizacion();
        $ruta = $this->archivo();
        try {
            $primera = $this->subir($cotizacion, $ruta, false);
            $segunda = $this->subir($cotizacion, $ruta, false);
            $this->assertSame($primera->id, $segunda->id);
            $this->assertDatabaseCount('importaciones_plantilla_costeo', 1);
            $this->post(route('plantillas-costeo.importaciones.confirmar', $primera))->assertSessionHasNoErrors();
            $this->post(route('cotizaciones-cliente.excel.store', $cotizacion), [
                'documento' => new UploadedFile($ruta, 'otra_nombre.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ])->assertSessionHasErrors('documento');
            $this->assertSame(1, $cotizacion->presupuestos()->count());
        } finally {
            if (is_file($ruta)) { unlink($ruta); }
        }
    }

    public function test_recorrido_excel_cotizacion_orden_salidas_retornos_y_excel_final(): void
    {
        $cotizacion = $this->cotizacion();
        $importacion = $this->subir($cotizacion, $this->archivo(false, true));
        $servicio = $importacion->partidas()->where('tipo_costo', 'SERVICIO_TERCERO')->firstOrFail();
        $this->patch(route('plantillas-costeo.importaciones.partidas.update', $servicio), [
            'accion' => 'GUARDAR', 'tipo_costo' => 'SERVICIO_TERCERO', 'ejecucion_servicio' => 'EXTERNO',
            'unidad' => 'SERVICIO', 'grupo_costo' => 'SERVICIOS', 'descripcion' => 'Servicio externo',
            'cantidad' => 1, 'costo_unitario' => 50, 'moneda' => 'PEN', 'igv_modo' => 'INCLUIDO',
        ])->assertSessionHasNoErrors();
        $this->post(route('plantillas-costeo.importaciones.confirmar', $importacion))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('plantillas_costeo', 0);
        $this->assertSame(2, $cotizacion->presupuestos()->count());

        $admin = $this->usuario('ADMINISTRADOR', 'admin_recorrido_19075');
        $this->actingAs($admin);
        $this->post(route('cotizaciones-cliente.presupuesto.sincronizar', $cotizacion))
            ->assertSessionHasNoErrors();
        $this->assertNotNull($cotizacion->fresh()->costeo_sincronizado_en);
        $this->assertGreaterThan(0, (float) $cotizacion->fresh()->total);
        $this->patch(route('cotizaciones-cliente.cerrar', $cotizacion))
            ->assertSessionHasNoErrors();
        $this->assertSame('CERRADA', $cotizacion->fresh()->estado);
        $this->post(route('cotizaciones-cliente.convertir-orden', $cotizacion), [
            'fecha_apertura' => today()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame('CONVERTIDA_EN_ORDEN', $cotizacion->fresh()->estado);
        $orden = OrdenOperacion::query()->whereNull('orden_padre_id')->sole();
        $this->assertSame($orden->id, $cotizacion->fresh()->orden_operacion_id);
        $this->assertDatabaseCount('ordenes_operacion', 1); // Servicio externo: costo, no OS hija.
        $areaMaterial = $orden->todasLasAreas()->where('nombre_normalizado', 'MANTA')->sole();
        $areaServicio = $orden->todasLasAreas()->where('nombre_normalizado', 'SERVICIOS')->sole();
        $plan = $orden->materialesPlanificadosPorArea()->sole();
        $this->assertSame($areaMaterial->id, $plan->orden_area_id);
        $this->assertSame(2.0, (float) $plan->cantidad_estimada);
        $this->assertSame(236.0, (float) $plan->costo_total_estimado_soles);

        $repisa = Repisa::create(['codigo' => 'R-RECORRIDO-19075', 'descripcion' => 'Repisa', 'estado' => true]);
        $inventario = Inventario::create(['producto_id' => $this->producto->id, 'repisa_id' => $repisa->id,
            'stock_actual' => 10, 'stock_minimo' => 1, 'stock_maximo' => 20, 'costo_promedio_soles' => 5]);
        $receptor = Empleado::create(['nombre_completo' => 'Operario recorrido', 'dni' => '73456075',
            'estado' => true, 'registrado_por' => $admin->id]);
        $this->patch(route('ordenes-operacion.iniciar', $orden))->assertSessionHasNoErrors();
        $this->assertSame('EN_PROCESO', $orden->fresh()->estado);
        $this->post(route('notas-salida.store'), [
            'motivo_salida' => 'ORDEN_OPERACION', 'orden_operacion_id' => $orden->id,
            'orden_area_id' => $areaMaterial->id, 'area_trabajo' => 'MANTA',
            'recibido_por_empleado_id' => $receptor->id, 'fecha_salida' => today()->toDateString(),
            'detalles' => [[
                'inventario_id' => $inventario->id, 'producto_id' => $this->producto->id,
                'repisa_id' => $repisa->id, 'tratamiento' => 'CONSUMO', 'cantidad' => 4,
                'motivo_excedente' => 'NECESIDAD_OPERATIVA',
            ]],
        ])->assertSessionHasNoErrors();
        $salida = NotaSalida::query()->where('orden_operacion_id', $orden->id)->sole();
        $detalleSalida = $salida->detalles()->sole();
        $this->assertSame($areaMaterial->id, $salida->orden_area_id);
        $this->assertSame(2.0, (float) $detalleSalida->cantidad_excedente);
        foreach (['RETORNO_MATERIAL', 'DEVOLUCION_MATERIAL_MALOGRADO'] as $motivo) {
            $this->post(route('notas-ingreso.store'), [
                'motivo_ingreso' => $motivo, 'nota_salida_id' => $salida->id,
                'devuelto_por_empleado_id' => $receptor->id, 'fecha_ingreso' => today()->toDateString(),
                'detalles' => [[
                    'nota_salida_detalle_id' => $detalleSalida->id, 'producto_id' => $this->producto->id,
                    'repisa_id' => $repisa->id, 'cantidad' => 1,
                ]],
            ])->assertSessionHasNoErrors();
        }
        $this->assertSame(7.0, (float) $inventario->fresh()->stock_actual);

        $this->post(route('ordenes-operacion.costos-directos.store', $orden), [
            'orden_area_id' => $areaServicio->id, 'tipo' => 'SERVICIO_TERCERO',
            'fecha_costo' => today()->toDateString(), 'descripcion' => 'Servicio externo ejecutado',
            'cantidad' => 1, 'unidad' => 'SERVICIO', 'costo_unitario_soles' => 80,
        ])->assertSessionHasNoErrors();
        $reporte = app(GastoRealOrdenService::class)->construir($orden->fresh());
        $material = collect($reporte['materiales'])->firstWhere('area', 'ESTRUCTURA / MANTA');
        $this->assertNotNull($material);
        $this->assertSame(2.0, $material['estimado']);
        $this->assertSame(3.0, $material['real']);
        $this->assertSame(1.0, $material['diferencia']);
        $this->assertSame(1.0, $material['malogrado']);
        $this->assertSame(15.0, $material['costo_real']);
        $this->assertSame(80.0, collect($reporte['otros_reales'])->firstWhere('area', 'SERVICIOS')['importe']);
        $this->assertSame(95.0, $reporte['totales']['costo_real']);
        $areaMaterialComparada = collect($reporte['areas'])->firstWhere('area', 'ESTRUCTURA / MANTA');
        $areaServicioComparada = collect($reporte['areas'])->firstWhere('area', 'SERVICIOS');
        $this->assertSame(236.0, $areaMaterialComparada['total_estimado']);
        $this->assertSame(15.0, $areaMaterialComparada['total_real']);
        $this->assertSame(80.0, $areaServicioComparada['otros_reales']);
        $this->assertEqualsWithDelta(
            (float) $cotizacion->presupuestos()->where('tipo_costo', 'SERVICIO_TERCERO')->firstOrFail()->costo_total_soles,
            $areaServicioComparada['otros_estimados'],
            0.0001
        );
        $this->assertSame(95.0, array_sum(array_column($reporte['areas'], 'total_real')));

        $this->get(route('ordenes-operacion.gasto-real', $orden))
            ->assertOk()
            ->assertSee('ESTRUCTURA / MANTA')
            ->assertSee('SERVICIOS');

        $respuesta = $this->get(route('ordenes-operacion.gasto-real.excel', $orden));
        $respuesta->assertOk()->assertDownload('GASTO_REAL_ORDEN_'.$orden->id.'.xlsx');
        $ruta = tempnam(sys_get_temp_dir(), 'recorrido_19075_');
        try {
            file_put_contents($ruta, $respuesta->streamedContent());
            $libro = IOFactory::load($ruta);
            $this->assertEqualsWithDelta(95, $libro->getActiveSheet()->getCell('F7')->getCalculatedValue(), 0.00001);
            $this->assertSame('00017', $libro->getSheetByName('Materiales')->getCell('C5')->getValue());
            $this->assertEquals(3, $libro->getSheetByName('Materiales')->getCell('J5')->getValue());
            $this->assertSame('SERVICIOS', $libro->getSheetByName('Otros reales')->getCell('B5')->getValue());
            $libro->disconnectWorksheets();
        } finally {
            if (is_file($ruta)) { unlink($ruta); }
        }
    }

    public function test_recorrido_manual_con_dos_areas_servicio_interno_y_excel_final(): void
    {
        $cotizacion = $this->cotizacion();
        $tipo = $cotizacion->tipoOrden;
        TipoOrden::updateOrCreate(['codigo' => 'OS'], ['nombre' => 'Servicio', 'estado' => true]);
        $componente = $cotizacion->componentes()->create([
            'tipo_orden_id' => $tipo->id,
            'descripcion_componente' => $cotizacion->descripcion_trabajo,
            'tipo_cambio_comparacion' => 3.8,
            'orden_secuencia' => 1,
        ]);

        foreach ([['TUBERÍAS', 2, 10], ['SISTEMA NEUMÁTICO', 3, 20]] as [$area, $cantidad, $costo]) {
            $this->post(route('cotizaciones-cliente.presupuesto.materiales.store', $cotizacion), [
                'componente_id' => $componente->id, 'area_nombre' => $area,
                'moneda' => 'PEN', 'tipo_cambio' => 3.8, 'margen_porcentaje' => 20,
                'igv_modo' => 'NO_APLICA', 'igv_venta_porcentaje' => 18,
                'materiales' => [['producto_id' => $this->producto->id,
                    'cantidad' => $cantidad, 'costo_unitario' => $costo]],
            ])->assertSessionHasNoErrors();
        }
        foreach ([['TUBERÍAS', 'EXTERNO', 40], ['SISTEMA NEUMÁTICO', 'INTERNO_HIDROIL', 100]] as [$area, $ejecucion, $costo]) {
            $this->post(route('cotizaciones-cliente.presupuesto.store', $cotizacion), [
                'componente_id' => $componente->id, 'tipo_costo' => 'SERVICIO_TERCERO',
                'ejecucion_servicio' => $ejecucion, 'area_nombre' => $area,
                'descripcion' => 'Servicio '.$ejecucion, 'cantidad' => 1, 'unidad' => 'SERVICIO',
                'moneda' => 'PEN', 'tipo_cambio' => 3.8, 'costo_unitario' => $costo,
                'margen_porcentaje' => 20, 'igv_modo' => 'NO_APLICA',
            ])->assertSessionHasNoErrors();
        }
        $this->assertSame(2, $cotizacion->todasLasAreas()->count());
        $this->assertSame(4, $cotizacion->presupuestos()->count());
        $this->assertNull($cotizacion->fresh()->costeo_sincronizado_en);
        $admin = $this->usuario('ADMINISTRADOR', 'admin_manual_19075');
        $this->actingAs($admin);
        $this->post(route('cotizaciones-cliente.presupuesto.sincronizar', $cotizacion))->assertSessionHasNoErrors();
        $this->patch(route('cotizaciones-cliente.cerrar', $cotizacion))->assertSessionHasNoErrors();
        $this->post(route('cotizaciones-cliente.convertir-orden', $cotizacion), [
            'fecha_apertura' => today()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('ordenes_operacion', 2);
        $principal = OrdenOperacion::query()->whereNull('orden_padre_id')->sole();
        $hija = OrdenOperacion::query()->where('orden_padre_id', $principal->id)->sole();
        $interno = $cotizacion->presupuestos()->where('ejecucion_servicio', 'INTERNO_HIDROIL')->sole();
        $this->assertSame($interno->id, $hija->presupuesto_servicio_origen_id);
        $this->get(route('cotizaciones-cliente.show', $cotizacion))
            ->assertOk()
            ->assertSee('Orden principal:')
            ->assertSee('Servicio interno:')
            ->assertSee(route('ordenes-operacion.show', $principal), false)
            ->assertSee(route('ordenes-operacion.show', $hija), false)
            ->assertSee(route('ordenes-operacion.gasto-real', $principal), false)
            ->assertSee(route('ordenes-operacion.gasto-real.excel', $principal), false);
        $this->assertSame(2, $principal->materialesPlanificadosPorArea()->count());
        $this->assertSame(1, $principal->materialesRequeridos()->count());
        $tuberias = $principal->todasLasAreas()->where('nombre_normalizado', 'TUBERÍAS')->sole();
        $neumatico = $principal->todasLasAreas()->where('nombre_normalizado', 'SISTEMA NEUMÁTICO')->sole();

        $repisa = Repisa::create(['codigo' => 'R-MANUAL-19075', 'descripcion' => 'Repisa', 'estado' => true]);
        $inventario = Inventario::create(['producto_id' => $this->producto->id, 'repisa_id' => $repisa->id,
            'stock_actual' => 10, 'stock_minimo' => 1, 'stock_maximo' => 20, 'costo_promedio_soles' => 5]);
        $receptor = Empleado::create(['nombre_completo' => 'Operario manual', 'dni' => '73456076',
            'estado' => true, 'registrado_por' => $admin->id]);
        $this->patch(route('ordenes-operacion.iniciar', $principal))->assertSessionHasNoErrors();
        $this->patch(route('ordenes-operacion.iniciar', $hija))->assertSessionHasNoErrors();
        $this->assertSame('EN_PROCESO', $hija->fresh()->estado);
        foreach ([[$tuberias, 3, 'NECESIDAD_OPERATIVA'], [$neumatico, 1, null]] as [$area, $cantidad, $motivo]) {
            $this->post(route('notas-salida.store'), [
                'motivo_salida' => 'ORDEN_OPERACION', 'orden_operacion_id' => $principal->id,
                'orden_area_id' => $area->id, 'area_trabajo' => $area->nombre,
                'recibido_por_empleado_id' => $receptor->id, 'fecha_salida' => today()->toDateString(),
                'detalles' => [[
                    'inventario_id' => $inventario->id, 'producto_id' => $this->producto->id,
                    'repisa_id' => $repisa->id, 'tratamiento' => 'CONSUMO',
                    'cantidad' => $cantidad, 'motivo_excedente' => $motivo,
                ]],
            ])->assertSessionHasNoErrors();
        }
        $salida = NotaSalida::query()->where('orden_area_id', $tuberias->id)->sole();
        $detalle = $salida->detalles()->sole();
        $this->assertSame(1.0, (float) $detalle->cantidad_excedente);
        foreach (['RETORNO_MATERIAL', 'DEVOLUCION_MATERIAL_MALOGRADO'] as $motivo) {
            $this->post(route('notas-ingreso.store'), [
                'motivo_ingreso' => $motivo, 'nota_salida_id' => $salida->id,
                'devuelto_por_empleado_id' => $receptor->id, 'fecha_ingreso' => today()->toDateString(),
                'detalles' => [[
                    'nota_salida_detalle_id' => $detalle->id, 'producto_id' => $this->producto->id,
                    'repisa_id' => $repisa->id, 'cantidad' => 1,
                ]],
            ])->assertSessionHasNoErrors();
        }
        $this->assertSame(7.0, (float) $inventario->fresh()->stock_actual);
        foreach ([[$principal, $tuberias->id, 'SERVICIO_TERCERO', 50], [$hija, null, 'MANO_OBRA', 70]] as [$orden, $areaId, $tipoCosto, $costo]) {
            $this->post(route('ordenes-operacion.costos-directos.store', $orden), [
                'orden_area_id' => $areaId, 'tipo' => $tipoCosto,
                'fecha_costo' => today()->toDateString(), 'descripcion' => 'Costo ejecutado',
                'cantidad' => 1, 'unidad' => $tipoCosto === 'MANO_OBRA' ? 'HORA' : 'SERVICIO',
                'costo_unitario_soles' => $costo,
            ])->assertSessionHasNoErrors();
        }

        $reporte = app(GastoRealOrdenService::class)->construir($principal->fresh());
        $this->assertSame(220.0, $reporte['totales']['costo_estimado']);
        $this->assertSame(135.0, $reporte['totales']['costo_real']);
        $this->assertSame(220.0, array_sum(array_column($reporte['areas'], 'total_estimado')));
        $this->assertSame(135.0, array_sum(array_column($reporte['areas'], 'total_real')));
        $material = collect($reporte['materiales'])->firstWhere('area', 'TUBERÍAS');
        $this->assertSame(2.0, $material['estimado']);
        $this->assertSame(2.0, $material['real']);
        $this->assertSame(1.0, $material['malogrado']);
        $this->assertSame(0.0, $material['diferencia']);
        $materialNeumatico = collect($reporte['materiales'])->firstWhere('area', 'SISTEMA NEUMÁTICO');
        $this->assertSame(3.0, $materialNeumatico['estimado']);
        $this->assertSame(1.0, $materialNeumatico['real']);
        $this->assertSame(0.0, collect($reporte['areas'])->firstWhere('area', 'TUBERÍAS')['diferencia_total']);
        $this->assertSame(70.0, collect($reporte['otros_reales'])->firstWhere('orden', $hija->codigo_orden)['importe']);
        $desgloseOs = collect($reporte['servicios_internos'])->sole();
        $this->assertSame('SISTEMA NEUMÁTICO', $desgloseOs['area']);
        $this->assertSame(100.0, $desgloseOs['estimado']);
        $this->assertSame(70.0, $desgloseOs['total_real']);
        $this->assertSame(-30.0, $desgloseOs['diferencia']);

        $respuesta = $this->get(route('ordenes-operacion.gasto-real.excel', $principal));
        $respuesta->assertOk()->assertDownload('GASTO_REAL_ORDEN_'.$principal->id.'.xlsx');
        $ruta = tempnam(sys_get_temp_dir(), 'manual_19075_');
        try {
            file_put_contents($ruta, $respuesta->streamedContent());
            $libro = IOFactory::load($ruta);
            $this->assertEqualsWithDelta(135, $libro->getActiveSheet()->getCell('F7')->getCalculatedValue(), 0.00001);
            $hojaAreas = $libro->getSheetByName('Áreas');
            $filaTuberias = null;
            for ($fila = 5; $fila <= $hojaAreas->getHighestRow(); $fila++) {
                if ($hojaAreas->getCell('A'.$fila)->getValue() === $principal->codigo_orden
                    && $hojaAreas->getCell('B'.$fila)->getValue() === 'TUBERÍAS') {
                    $filaTuberias = $fila;
                    break;
                }
            }
            $this->assertNotNull($filaTuberias);
            $this->assertSame(60.0, $hojaAreas->getCell('H'.$filaTuberias)->getValue());
            $this->assertSame(60.0, $hojaAreas->getCell('I'.$filaTuberias)->getValue());
            $hojaOs = $libro->getSheetByName('OS internas por área');
            $this->assertSame($hija->codigo_orden, $hojaOs->getCell('A5')->getValue());
            $this->assertSame('SISTEMA NEUMÁTICO', $hojaOs->getCell('B5')->getValue());
            $this->assertSame(100.0, $hojaOs->getCell('D5')->getValue());
            $this->assertSame(70.0, $hojaOs->getCell('G5')->getValue());
            $this->assertSame(-30.0, $hojaOs->getCell('H5')->getValue());
            $libro->disconnectWorksheets();
        } finally {
            if (is_file($ruta)) { unlink($ruta); }
        }
    }

    private function usuario(string $rol, string $nombre): User
    {
        return User::create(['role_id' => Role::where('codigo', $rol)->firstOrFail()->id, 'username' => $nombre,
            'email' => $nombre.'@example.com', 'password' => 'password-seguro', 'estado' => true, 'fecha_creacion' => now()]);
    }

    private function cotizacion(string $codigo = 'OP'): CotizacionCliente
    {
        $tipo = TipoOrden::updateOrCreate(['codigo' => $codigo], ['nombre' => $codigo, 'estado' => true]);
        return CotizacionCliente::create(['origen' => 'DIRECTA_LOGISTICA', 'cliente_id' => $this->cliente->id,
            'tipo_orden_id' => $tipo->id, 'descripcion_trabajo' => 'Fabricación de prueba', 'codigo_base' => 'COT-19075',
            'version' => CotizacionCliente::count() + 1, 'codigo' => 'COT-19075-'.(CotizacionCliente::count() + 1),
            'cliente_documento' => $this->cliente->ruc, 'cliente_nombre' => $this->cliente->razon_social,
            'fecha_emision' => today(), 'moneda' => 'PEN', 'tipo_cambio' => 3.8, 'estado' => 'ABIERTA',
            'cotizado_por' => $this->usuario->id, 'costeo_sincronizado_en' => now()]);
    }

    private function subir(CotizacionCliente $cotizacion, string $ruta, bool $borrar = true): ImportacionPlantillaCosteo
    {
        try {
            $this->post(route('cotizaciones-cliente.excel.store', $cotizacion), [
                'documento' => new UploadedFile($ruta, 'costeo.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
                'cotizacion_cliente_id' => 999999, 'tipo_orden_id' => 999999,
            ])->assertSessionHasNoErrors()->assertRedirect();
            return ImportacionPlantillaCosteo::latest('id')->firstOrFail();
        } finally {
            if ($borrar && is_file($ruta)) { unlink($ruta); }
        }
    }

    private function archivo(bool $pendiente = false, bool $servicio = false): string
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setCellValue('B4', 'CANT.')->setCellValue('C4', 'DESCRIPCION DEL PRODUCTO');
        $hoja->setCellValue('C5', 'ESTRUCTURA')->setCellValue('C6', 'MANTA');
        $hoja->getStyle('C5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0070C0');
        $hoja->getStyle('C6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF85F0F3');
        $hoja->setCellValueExplicit('A7', '00017', DataType::TYPE_STRING);
        $hoja->setCellValue('B7', 2)->setCellValueExplicit('C7', $this->producto->descripcion, DataType::TYPE_STRING);
        $hoja->setCellValue('D7', 'UND')->setCellValue('F7', 118)->setCellValue('G7', 236)->setCellValue('H7', 0.1)->setCellValue('N7', 3.8);
        if ($pendiente) {
            $hoja->setCellValue('A8', 'NO-EXISTE')->setCellValue('B8', 1)->setCellValue('C8', 'Pendiente')->setCellValue('F8', 10);
        }
        if ($servicio) {
            $hoja->setCellValue('C8', 'SERVICIOS')->setCellValue('B9', 1)->setCellValue('C9', 'Prueba')->setCellValue('D9', 'SERVICIO')->setCellValue('F9', 50);
        }
        $ruta = tempnam(sys_get_temp_dir(), 'cotizacion_19075_');
        (new Xlsx($libro))->save($ruta);
        $libro->disconnectWorksheets();
        return $ruta;
    }
}
