<?php

namespace Tests\Feature;

use App\Models\Producto;
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
        $ruta = $temporal.'.xlsx';
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
        $ruta = $temporal.'.xlsx';
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
        $vista = file_get_contents(resource_path('views/requerimientos_compra/show.blade.php'));

        $this->assertStringContainsString('purchase-requirement-next-action', $vista);
        $this->assertStringContainsString('<x-ui.icon name="arrow-right"', $vista);
        $this->assertStringNotContainsString(
            '<div class="notice notice--{{ $siguienteAccion[\'tono\'] }} notice--block"',
            $vista
        );
        $this->assertStringContainsString('Descargar solicitud Excel', $vista);
    }
}
