<?php

namespace Tests\Feature;

use App\Models\{Cliente, CotizacionCliente, ImportacionPlantillaCosteo, PlantillaCosteo, Producto, Role, TipoCliente, TipoOrden, UnidadMedida, User};
use App\Services\Ordenes\PlanificacionPorAreaService;
use App\Services\Ventas\{ExportarCotizacionCosteoExcelService, ExtractorPlantillaCosteoExcel, PresupuestoCotizacionService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
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
