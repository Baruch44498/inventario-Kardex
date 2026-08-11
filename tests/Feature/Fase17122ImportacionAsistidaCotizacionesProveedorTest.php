<?php

namespace Tests\Feature;

use App\Models\Cotizacion;
use App\Models\ImportacionCotizacionProveedor;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\Role;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\Compras\Importacion\InterpretarCotizacionImportada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Fase17122ImportacionAsistidaCotizacionesProveedorTest extends TestCase
{
    use RefreshDatabase;

    private User $logistica;
    private Requisicion $requerimiento;
    private Producto $producto;
    private Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistica = User::query()->create([
            'role_id' => Role::query()->where('codigo', 'COMERCIAL_LOGISTICA')->firstOrFail()->id,
            'username' => 'logistica_17122',
            'email' => 'logistica_17122@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);

        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );

        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => '11053',
            'descripcion' => 'RETEN HID. 85X98X9',
            'estado' => true,
        ]);

        $this->requerimiento = Requisicion::query()->create([
            'codigo' => 'REQ-17122-26',
            'fecha_solicitud' => now()->toDateString(),
            'origen' => 'REPOSICION',
            'descripcion' => 'Requerimiento para probar importación asistida.',
            'prioridad' => 'ALTA',
            'estado' => 'COTIZANDO',
            'solicitado_por' => $this->logistica->id,
            'enviado_por' => $this->logistica->id,
            'enviado_en' => now(),
            'recibido_por' => $this->logistica->id,
            'recibido_en' => now(),
        ]);

        $this->requerimiento->detalles()->create([
            'producto_id' => $this->producto->id,
            'cantidad_solicitada' => 10,
            'cantidad_sugerida' => 10,
            'cantidad_atendida' => 0,
        ]);

        $this->proveedor = Proveedor::query()->create([
            'ruc' => '20617122001',
            'razon_social' => 'Proveedor Importación 17.1.2.2 SAC',
            'nombre_comercial' => 'Proveedor Importador',
            'correo' => 'ventas@proveedor.test',
            'estado' => true,
        ]);
    }

    public function test_requerimiento_ofrece_importar_pdf_excel_y_revisión_obligatoria(): void
    {
        $this->actingAs($this->logistica)
            ->get(route('requerimientos-compra.show', $this->requerimiento))
            ->assertOk()
            ->assertSee('Importar PDF / Excel');

        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-proveedor.importacion.create', [
                'requisicion_id' => $this->requerimiento->id,
                'proveedor_id' => $this->proveedor->id,
            ]))
            ->assertOk()
            ->assertSee('Importación asistida')
            ->assertSee('La revisión humana es obligatoria');
    }

    public function test_interpretador_excel_detecta_producto_cantidad_precio_y_proveedor_por_ruc(): void
    {
        $resultado = app(InterpretarCotizacionImportada::class)->interpretar([
            'tipo' => 'EXCEL',
            'texto' => "RUC: {$this->proveedor->ruc}\nCOTIZACIÓN N° COT-778\nFECHA: 11/08/2026\nMoneda PEN",
            'filas' => [
                ['Código', 'Descripción', 'Cantidad', 'Precio unitario', 'IGV'],
                ['11053', 'RETEN HID. 85X98X9', 10, 35.50, 'Incluido'],
            ],
        ], $this->requerimiento->fresh(['detalles.producto.unidadMedida']));

        $this->assertSame($this->proveedor->id, $resultado['cabecera']['proveedor_id_detectado']);
        $this->assertSame('COT-778', $resultado['cabecera']['numero_documento']);
        $this->assertCount(1, $resultado['detalles']);
        $this->assertSame($this->producto->id, $resultado['detalles'][0]['producto_id']);
        $this->assertEquals(10, $resultado['detalles'][0]['cantidad']);
        $this->assertEquals(35.5, $resultado['detalles'][0]['precio_unitario']);
        $this->assertSame('EXACTA', $resultado['detalles'][0]['coincidencia']);
    }

    public function test_interpretador_pdf_digital_usa_productos_del_requerimiento_para_relacionar_lineas(): void
    {
        $resultado = app(InterpretarCotizacionImportada::class)->interpretar([
            'tipo' => 'PDF',
            'texto' => "COTIZACIÓN N° PDF-22\nRUC {$this->proveedor->ruc}\n11053 RETEN HID. 85X98X9 10 UND 35.00 350.00\nIGV 18%",
        ], $this->requerimiento->fresh(['detalles.producto.unidadMedida']));

        $this->assertCount(1, $resultado['detalles']);
        $this->assertSame($this->producto->id, $resultado['detalles'][0]['producto_id']);
        $this->assertSame(
            $this->requerimiento->detalles()->firstOrFail()->id,
            $resultado['detalles'][0]['requisicion_detalle_id']
        );
        $this->assertEquals(35.0, $resultado['detalles'][0]['precio_unitario']);
    }

    public function test_importacion_borrador_no_crea_cotizacion_hasta_confirmar(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('cotizaciones-proveedor/importaciones/prueba.xlsx', 'documento');

        $importacion = $this->crearImportacionBorrador('EXCEL');

        $this->assertDatabaseCount('cotizaciones', 0);
        $this->assertSame('BORRADOR', $importacion->estado);
    }

    public function test_vista_previa_muestra_documento_y_hidden_de_importacion(): void
    {
        $importacion = $this->crearImportacionBorrador('PDF');

        $this->actingAs($this->logistica)
            ->withSession(['_old_input' => $this->payloadPrefill($importacion)])
            ->get(route('cotizaciones-proveedor.create', [
                'requisicion_id' => $this->requerimiento->id,
                'proveedor_id' => $this->proveedor->id,
                'importacion_id' => $importacion->id,
            ]))
            ->assertOk()
            ->assertSee('Vista previa obligatoria')
            ->assertSee($importacion->nombre_original)
            ->assertSee('name="importacion_cotizacion_id"', false);
    }

    public function test_confirmar_importacion_crea_cotizacion_con_origen_y_conserva_documento(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('cotizaciones-proveedor/importaciones/prueba.xlsx', 'documento');
        $importacion = $this->crearImportacionBorrador('EXCEL');
        $detalleReq = $this->requerimiento->detalles()->firstOrFail();

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-proveedor.store'), [
                'importacion_cotizacion_id' => $importacion->id,
                'proveedor_id' => $this->proveedor->id,
                'requisicion_id' => $this->requerimiento->id,
                'numero_documento' => 'EXT-17122',
                'fecha_cotizacion' => now()->toDateString(),
                'fecha_validez' => null,
                'moneda' => 'PEN',
                'tipo_cambio' => null,
                'descuento_global_modo' => 'SIN_DESCUENTO',
                'descuento_global_tipo' => null,
                'descuento_global_valor' => null,
                'condiciones_pago' => 'Crédito 30 días',
                'condiciones_entrega' => '48 horas',
                'observacion' => 'Revisada antes de confirmar.',
                'detalles' => [[
                    'requisicion_detalle_id' => $detalleReq->id,
                    'producto_id' => $this->producto->id,
                    'cantidad' => 10,
                    'precio_unitario' => 35.50,
                    'descuento_modo' => 'SIN_DESCUENTO',
                    'descuento_tipo' => null,
                    'descuento_valor' => null,
                    'igv_modo' => 'AGREGAR',
                    'marca_ofertada' => null,
                    'observacion' => null,
                ]],
            ])
            ->assertRedirect();

        $cotizacion = Cotizacion::query()->firstOrFail();
        $importacion->refresh();

        $this->assertSame('IMPORTADO_EXCEL', $cotizacion->origen_registro);
        $this->assertSame($importacion->nombre_original, $cotizacion->archivo_original_nombre);
        $this->assertSame($importacion->ruta_archivo, $cotizacion->archivo_original_path);
        $this->assertSame('CONFIRMADA', $importacion->estado);
        $this->assertSame($cotizacion->id, $importacion->cotizacion_id);
    }

    public function test_cotizacion_importada_expone_descarga_autenticada_del_documento_original(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('cotizaciones-proveedor/importaciones/original.pdf', 'PDF digital');

        $cotizacion = Cotizacion::query()->create([
            'requisicion_id' => $this->requerimiento->id,
            'proveedor_id' => $this->proveedor->id,
            'codigo' => 'CP-17122-01',
            'numero_documento' => 'PDF-01',
            'fecha_cotizacion' => now()->toDateString(),
            'moneda' => 'PEN',
            'descuento_global_modo' => 'SIN_DESCUENTO',
            'subtotal' => 0,
            'descuento_global_monto' => 0,
            'impuesto' => 0,
            'total' => 0,
            'estado' => 'REGISTRADA',
            'origen_registro' => 'IMPORTADO_PDF',
            'archivo_original_nombre' => 'cotizacion-proveedor.pdf',
            'archivo_original_path' => 'cotizaciones-proveedor/importaciones/original.pdf',
            'registrado_por' => $this->logistica->id,
        ]);

        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-proveedor.documento-original', $cotizacion))
            ->assertOk()
            ->assertDownload('cotizacion-proveedor.pdf');
    }

    public function test_descarga_original_recupera_archivo_desde_importacion_confirmada_si_cotizacion_no_tiene_ruta(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('cotizaciones-proveedor/importaciones/legado.pdf', 'PDF legado');

        $cotizacion = Cotizacion::query()->create([
            'requisicion_id' => $this->requerimiento->id,
            'proveedor_id' => $this->proveedor->id,
            'codigo' => 'CP-17122-LEG',
            'numero_documento' => 'LEG-01',
            'fecha_cotizacion' => now()->toDateString(),
            'moneda' => 'PEN',
            'descuento_global_modo' => 'SIN_DESCUENTO',
            'subtotal' => 0,
            'descuento_global_monto' => 0,
            'impuesto' => 0,
            'total' => 0,
            'estado' => 'REGISTRADA',
            'origen_registro' => 'IMPORTADO_PDF',
            'archivo_original_nombre' => null,
            'archivo_original_path' => null,
            'registrado_por' => $this->logistica->id,
        ]);

        ImportacionCotizacionProveedor::query()->create([
            'requisicion_id' => $this->requerimiento->id,
            'proveedor_id' => $this->proveedor->id,
            'cotizacion_id' => $cotizacion->id,
            'tipo_archivo' => 'PDF',
            'nombre_original' => 'cotizacion-legado.pdf',
            'ruta_archivo' => 'cotizaciones-proveedor/importaciones/legado.pdf',
            'mime_type' => 'application/pdf',
            'datos_extraidos' => ['cabecera' => [], 'detalles' => []],
            'advertencias' => [],
            'estado' => 'CONFIRMADA',
            'creado_por' => $this->logistica->id,
            'confirmado_por' => $this->logistica->id,
            'confirmado_en' => now(),
        ]);

        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-proveedor.documento-original', $cotizacion))
            ->assertOk()
            ->assertDownload('cotizacion-legado.pdf');
    }


    public function test_descartar_importacion_borrador_elimina_archivo_y_marca_descartada(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('cotizaciones-proveedor/importaciones/prueba.xlsx', 'documento');
        $importacion = $this->crearImportacionBorrador('EXCEL');

        $this->actingAs($this->logistica)
            ->delete(route('cotizaciones-proveedor.importacion.destroy', $importacion))
            ->assertRedirect(route('requerimientos-compra.show', $this->requerimiento->id))
            ->assertSessionHas('success', 'Importación descartada. No se creó ninguna cotización.');

        $importacion->refresh();

        $this->assertSame('DESCARTADA', $importacion->estado);
        Storage::disk('local')->assertMissing($importacion->ruta_archivo);
        $this->assertDatabaseCount('cotizaciones', 0);
    }

    public function test_importacion_confirmada_no_puede_descartarse_ni_elimina_documento(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('cotizaciones-proveedor/importaciones/confirmada.pdf', 'PDF confirmado');

        $cotizacion = Cotizacion::query()->create([
            'requisicion_id' => $this->requerimiento->id,
            'proveedor_id' => $this->proveedor->id,
            'codigo' => 'CP-17122-CONF',
            'numero_documento' => 'CONF-01',
            'fecha_cotizacion' => now()->toDateString(),
            'moneda' => 'PEN',
            'descuento_global_modo' => 'SIN_DESCUENTO',
            'subtotal' => 0,
            'descuento_global_monto' => 0,
            'impuesto' => 0,
            'total' => 0,
            'estado' => 'REGISTRADA',
            'origen_registro' => 'IMPORTADO_PDF',
            'archivo_original_nombre' => 'confirmada.pdf',
            'archivo_original_path' => 'cotizaciones-proveedor/importaciones/confirmada.pdf',
            'registrado_por' => $this->logistica->id,
        ]);

        $importacion = ImportacionCotizacionProveedor::query()->create([
            'requisicion_id' => $this->requerimiento->id,
            'proveedor_id' => $this->proveedor->id,
            'cotizacion_id' => $cotizacion->id,
            'tipo_archivo' => 'PDF',
            'nombre_original' => 'confirmada.pdf',
            'ruta_archivo' => 'cotizaciones-proveedor/importaciones/confirmada.pdf',
            'mime_type' => 'application/pdf',
            'datos_extraidos' => ['cabecera' => [], 'detalles' => []],
            'advertencias' => [],
            'estado' => 'CONFIRMADA',
            'creado_por' => $this->logistica->id,
            'confirmado_por' => $this->logistica->id,
            'confirmado_en' => now(),
        ]);

        $this->actingAs($this->logistica)
            ->delete(route('cotizaciones-proveedor.importacion.destroy', $importacion))
            ->assertRedirect(route('requerimientos-compra.show', $this->requerimiento->id))
            ->assertSessionHas('error', 'La importación ya fue confirmada y está vinculada a una cotización. No puede descartarse.');

        $importacion->refresh();

        $this->assertSame('CONFIRMADA', $importacion->estado);
        $this->assertSame($cotizacion->id, $importacion->cotizacion_id);
        Storage::disk('local')->assertExists($importacion->ruta_archivo);
        $this->assertDatabaseHas('cotizaciones', ['id' => $cotizacion->id]);
    }

    public function test_importacion_ya_descartada_no_devuelve_falso_mensaje_de_exito(): void
    {
        Storage::fake('local');
        $importacion = $this->crearImportacionBorrador('EXCEL');
        $importacion->update(['estado' => 'DESCARTADA']);

        $this->actingAs($this->logistica)
            ->delete(route('cotizaciones-proveedor.importacion.destroy', $importacion))
            ->assertRedirect(route('requerimientos-compra.show', $this->requerimiento->id))
            ->assertSessionHas('error', 'La importación ya fue descartada anteriormente.')
            ->assertSessionMissing('success');

        $this->assertSame('DESCARTADA', $importacion->fresh()->estado);
    }

    public function test_otro_usuario_de_logistica_recibe_mensaje_de_estado_si_importacion_ya_fue_descartada(): void
    {
        $otroLogistica = User::query()->create([
            'role_id' => Role::query()->where('codigo', 'COMERCIAL_LOGISTICA')->firstOrFail()->id,
            'username' => 'logistica_17122_otro',
            'email' => 'logistica_17122_otro@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);

        $importacion = $this->crearImportacionBorrador('EXCEL');
        $importacion->update(['estado' => 'DESCARTADA']);

        $this->actingAs($otroLogistica)
            ->delete(route('cotizaciones-proveedor.importacion.destroy', $importacion))
            ->assertRedirect(route('requerimientos-compra.show', $this->requerimiento->id))
            ->assertSessionHas('error', 'La importación ya fue descartada anteriormente.')
            ->assertSessionMissing('success');

        $this->assertSame('DESCARTADA', $importacion->fresh()->estado);
    }

    public function test_otro_usuario_de_logistica_no_puede_descartar_borrador_ajeno(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('cotizaciones-proveedor/importaciones/prueba.xlsx', 'documento');

        $otroLogistica = User::query()->create([
            'role_id' => Role::query()->where('codigo', 'COMERCIAL_LOGISTICA')->firstOrFail()->id,
            'username' => 'logistica_17122_borrador_ajeno',
            'email' => 'logistica_17122_borrador_ajeno@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);

        $importacion = $this->crearImportacionBorrador('EXCEL');

        $this->actingAs($otroLogistica)
            ->delete(route('cotizaciones-proveedor.importacion.destroy', $importacion))
            ->assertForbidden();

        $this->assertSame('BORRADOR', $importacion->fresh()->estado);
        Storage::disk('local')->assertExists($importacion->ruta_archivo);
    }

    private function crearImportacionBorrador(string $tipo): ImportacionCotizacionProveedor
    {
        return ImportacionCotizacionProveedor::query()->create([
            'requisicion_id' => $this->requerimiento->id,
            'proveedor_id' => $this->proveedor->id,
            'tipo_archivo' => $tipo,
            'nombre_original' => $tipo === 'PDF' ? 'cotizacion.pdf' : 'cotizacion.xlsx',
            'ruta_archivo' => 'cotizaciones-proveedor/importaciones/prueba.' . ($tipo === 'PDF' ? 'pdf' : 'xlsx'),
            'mime_type' => $tipo === 'PDF' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'datos_extraidos' => [
                'cabecera' => [
                    'numero_documento' => 'DOC-17122',
                    'fecha_cotizacion' => now()->toDateString(),
                    'moneda' => 'PEN',
                ],
                'detalles' => [],
            ],
            'advertencias' => ['Revisar datos antes de confirmar.'],
            'estado' => 'BORRADOR',
            'creado_por' => $this->logistica->id,
        ]);
    }

    private function payloadPrefill(ImportacionCotizacionProveedor $importacion): array
    {
        $detalle = $this->requerimiento->detalles()->firstOrFail();

        return [
            'importacion_cotizacion_id' => $importacion->id,
            'requisicion_id' => $this->requerimiento->id,
            'proveedor_id' => $this->proveedor->id,
            'fecha_cotizacion' => now()->toDateString(),
            'moneda' => 'PEN',
            'descuento_global_modo' => 'SIN_DESCUENTO',
            'detalles' => [[
                'requisicion_detalle_id' => $detalle->id,
                'producto_id' => $this->producto->id,
                'cantidad' => 10,
                'precio_unitario' => 35.5,
                'descuento_modo' => 'SIN_DESCUENTO',
                'igv_modo' => 'AGREGAR',
            ]],
        ];
    }
}
