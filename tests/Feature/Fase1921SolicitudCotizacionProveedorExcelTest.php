<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Cotizacion;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\RequisicionDetalle;
use App\Models\Role;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\Compras\ExportarSolicitudCotizacionProveedorExcelService;
use App\Services\Compras\Importacion\ImportarCotizacionProveedorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class Fase1921SolicitudCotizacionProveedorExcelTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private Proveedor $proveedor;
    private Requisicion $requerimiento;
    private RequisicionDetalle $detalle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = User::query()->create([
            'role_id' => Role::query()->where('codigo', 'ALMACEN')->firstOrFail()->id,
            'username' => 'almacen_1921',
            'email' => 'almacen_1921@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'MAT-1921-001',
            'descripcion' => 'Válvula de prueba para solicitud',
            'estado' => true,
        ]);
        $this->proveedor = Proveedor::query()->create([
            'ruc' => '20619210001',
            'razon_social' => 'Proveedor Solicitud 1921 S.A.C.',
            'nombre_comercial' => 'Proveedor Solicitud',
            'estado' => true,
        ]);
        $this->requerimiento = Requisicion::query()->create([
            'codigo' => 'REQ-1921-001',
            'fecha_solicitud' => now()->toDateString(),
            'origen' => 'REPOSICION',
            'prioridad' => 'NORMAL',
            'estado' => 'BORRADOR',
            'estado_abastecimiento' => 'PENDIENTE',
            'solicitado_por' => $this->almacen->id,
        ]);
        $this->detalle = $this->requerimiento->detalles()->create([
            'producto_id' => $producto->id,
            'cantidad_solicitada' => 4,
            'cantidad_sugerida' => 4,
            'cantidad_atendida' => 0,
        ]);
    }

    public function test_almacen_descarga_la_solicitud_con_precio_vacio(): void
    {
        $respuesta = $this->actingAs($this->almacen)->get(route(
            'requerimientos-compra.solicitud-cotizacion.excel',
            [
                'requerimientoCompra' => $this->requerimiento,
                'proveedor' => $this->proveedor,
                'detalle_ids' => [$this->detalle->id],
            ]
        ));

        $respuesta->assertOk()->assertDownload(
            'SOLICITUD_COTIZACION_REQ-1921-001_PROVEEDOR_SOLICITUD.xlsx'
        );

        $temporal = tempnam(sys_get_temp_dir(), 'solicitud-1921-');
        $ruta = $temporal . '.xlsx';
        @unlink($temporal);
        file_put_contents($ruta, $respuesta->streamedContent());
        $hoja = IOFactory::load($ruta)->getActiveSheet();

        $this->assertSame('PROVEEDOR SOLICITUD', $hoja->getCell('A1')->getValue());
        $this->assertSame('CODIGO', $hoja->getCell('A5')->getValue());
        $this->assertSame('PRECIO UNIT + IGV', $hoja->getCell('D5')->getValue());
        $this->assertSame('MAT-1921-001', $hoja->getCell('A6')->getValue());
        $this->assertSame(4.0, (float) $hoja->getCell('B6')->getValue());
        $this->assertSame('', (string) $hoja->getCell('D6')->getValue());

        @unlink($ruta);
    }

    public function test_el_archivo_completado_puede_volver_al_importador_existente(): void
    {
        $this->requerimiento->load('detalles.producto.unidadMedida');
        $libro = app(ExportarSolicitudCotizacionProveedorExcelService::class)->libro(
            $this->requerimiento,
            $this->proveedor,
            $this->requerimiento->detalles
        );
        $libro->getActiveSheet()->setCellValue('D2', 'USD');
        $libro->getActiveSheet()->setCellValue('D6', 12.28);

        $temporal = tempnam(sys_get_temp_dir(), 'respuesta-1921-');
        $ruta = $temporal . '.xlsx';
        @unlink($temporal);
        (new Xlsx($libro))->save($ruta);
        $resultado = app(ImportarCotizacionProveedorService::class)->procesar(
            $ruta,
            'xlsx',
            $this->requerimiento
        );

        $this->assertSame($this->proveedor->id, $resultado['cabecera']['proveedor_id_detectado']);
        $this->assertSame('USD', $resultado['cabecera']['moneda']);
        $this->assertSame('INCLUIDO', $resultado['cabecera']['igv_modo_sugerido']);
        $this->assertSame($this->detalle->id, $resultado['detalles'][0]['requisicion_detalle_id']);
        $this->assertSame(12.28, $resultado['detalles'][0]['precio_unitario']);

        @unlink($ruta);
    }

    public function test_siguiente_accion_es_compacta_y_no_reutiliza_el_banner_azul(): void
    {
        $vista = file_get_contents(resource_path('views/requerimientos_compra/partials/_show_encabezado.blade.php'))
            . file_get_contents(resource_path('views/requerimientos_compra/partials/_tarjeta_proveedor.blade.php'));

        $this->assertStringContainsString('purchase-requirement-next-action', $vista);
        $this->assertStringContainsString('<x-ui.icon name="arrow-right"', $vista);
        $this->assertStringNotContainsString(
            '<div class="notice notice--{{ $siguienteAccion[\'tono\'] }} notice--block"',
            $vista
        );
        $this->assertStringContainsString('Descargar solicitud Excel', $vista);
    }

    public function test_sugerencias_y_excel_solo_incluyen_productos_pendientes_de_cotizar(): void
    {
        $unidad = UnidadMedida::query()->where('codigo', 'UND')->firstOrFail();
        $productoPendiente = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'MAT-1921-PEND',
            'descripcion' => 'Producto que todavía necesita cotización',
            'estado' => true,
        ]);
        $detallePendiente = $this->requerimiento->detalles()->create([
            'producto_id' => $productoPendiente->id,
            'cantidad_solicitada' => 7,
            'cantidad_sugerida' => 7,
            'cantidad_atendida' => 0,
        ]);

        $cotizacionActual = $this->crearCotizacion('CP-1921-ACTUAL', $this->requerimiento->id);
        $this->crearDetalleCotizacion($cotizacionActual, $this->detalle);

        $referenciaHistorica = $this->crearCotizacion('CP-1921-HIST', null);
        $this->crearDetalleCotizacion($referenciaHistorica, $detallePendiente);

        $respuesta = $this->actingAs($this->almacen)
            ->get(route('requerimientos-compra.show', $this->requerimiento))
            ->assertOk();

        $idsSugeridos = $respuesta->viewData('gruposSugeridos')
            ->pluck('detalle_ids')
            ->flatten()
            ->map(fn($id): int => (int) $id);
        $idsContactos = $respuesta->viewData('contactos')
            ->pluck('detalle_ids')
            ->flatten()
            ->map(fn($id): int => (int) $id);

        $this->assertEqualsCanonicalizing([$detallePendiente->id], $idsSugeridos->all());
        $this->assertEqualsCanonicalizing([$detallePendiente->id], $idsContactos->all());

        $this->actingAs($this->almacen)
            ->from(route('requerimientos-compra.show', $this->requerimiento))
            ->get(route('requerimientos-compra.solicitud-cotizacion.excel', [
                'requerimientoCompra' => $this->requerimiento,
                'proveedor' => $this->proveedor,
                'detalle_ids' => [$this->detalle->id],
            ]))
            ->assertRedirect(route('requerimientos-compra.show', $this->requerimiento))
            ->assertSessionHasErrors('detalle_ids');
    }

    private function crearCotizacion(string $codigo, ?int $requisicionId): Cotizacion
    {
        return Cotizacion::query()->create([
            'requisicion_id' => $requisicionId,
            'proveedor_id' => $this->proveedor->id,
            'codigo' => $codigo,
            'numero_documento' => 'DOC-' . $codigo,
            'fecha_cotizacion' => now()->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => 1,
            'descuento_global_modo' => 'SIN_DESCUENTO',
            'subtotal' => 10,
            'descuento_global_monto' => 0,
            'impuesto' => 1.8,
            'total_calculado' => 11.8,
            'ajuste_redondeo' => 0,
            'total' => 11.8,
            'estado' => 'REGISTRADA',
            'registrado_por' => $this->almacen->id,
        ]);
    }

    private function crearDetalleCotizacion(Cotizacion $cotizacion, RequisicionDetalle $detalle): void
    {
        $cotizacion->detalles()->create([
            'requisicion_detalle_id' => $cotizacion->requisicion_id ? $detalle->id : null,
            'tipo_vinculacion' => $cotizacion->requisicion_id ? 'SOLICITADO' : 'ADICIONAL',
            'vinculacion_origen' => 'MANUAL',
            'producto_id' => $detalle->producto_id,
            'cantidad' => 1,
            'precio_unitario' => 11.8,
            'descuento_porcentaje' => 0,
            'descuento_modo' => 'SIN_DESCUENTO',
            'igv_modo' => 'INCLUIDO',
            'igv_porcentaje' => 18,
            'subtotal' => 10,
            'impuesto' => 1.8,
            'total' => 11.8,
        ]);
    }
}
