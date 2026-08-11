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

    public function test_requerimiento_ofrece_un_solo_registro_y_ruta_anterior_redirige_al_metodo_asistido(): void
    {
        $this->actingAs($this->logistica)
            ->get(route('requerimientos-compra.show', $this->requerimiento))
            ->assertOk()
            ->assertSee('Registrar cotización')
            ->assertDontSee('Registro manual');

        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-proveedor.importacion.create', [
                'requisicion_id' => $this->requerimiento->id,
                'proveedor_id' => $this->proveedor->id,
            ]))
            ->assertRedirect(route('cotizaciones-proveedor.create', [
                'modo' => 'importar',
                'requisicion_id' => $this->requerimiento->id,
                'proveedor_id' => $this->proveedor->id,
            ]));

        $detalleId = $this->requerimiento->detalles()->firstOrFail()->id;

        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-proveedor.create', [
                'modo' => 'importar',
                'requisicion_id' => $this->requerimiento->id,
                'proveedor_id' => $this->proveedor->id,
                'detalle_ids' => [$detalleId],
            ]))
            ->assertOk()
            ->assertSee('Importar PDF / Excel')
            ->assertSee('La revisión humana es obligatoria')
            ->assertSee('name="detalle_ids[]" value="' . $detalleId . '"', false);
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
        $this->assertSame('INCLUIDO', $resultado['detalles'][0]['igv_modo']);
    }

    public function test_conciliacion_detecta_precio_con_igv_aunque_la_columna_sea_ambigua(): void
    {
        $resultado = app(InterpretarCotizacionImportada::class)->interpretar([
            'tipo' => 'EXCEL',
            'texto' => implode("\n", [
                "RUC: {$this->proveedor->ruc}",
                'COTIZACIÓN N° COT-CONCILIADA',
                'Valor Venta Neto: S/ 100.00',
                'I.G.V.: S/ 18.00',
                'Importe Total: S/ 118.00',
            ]),
            'filas' => [
                ['Código', 'Descripción', 'Cantidad', 'Precio unitario'],
                ['11053', 'RETEN HID. 85X98X9', 2, 59.00],
            ],
        ], $this->requerimiento->fresh(['detalles.producto.unidadMedida']));

        $this->assertSame('INCLUIDO', $resultado['cabecera']['igv_modo_sugerido']);
        $this->assertSame('COINCIDE', $resultado['cabecera']['conciliacion']['estado']);
        $this->assertSame(
            'Precios finales con IGV incluido',
            $resultado['cabecera']['conciliacion']['interpretacion']
        );
        $this->assertSame('INCLUIDO', $resultado['detalles'][0]['igv_modo']);
        $this->assertEquals(59.00, $resultado['detalles'][0]['precio_unitario']);
        $this->assertEquals(118.00, $resultado['cabecera']['conciliacion']['total_calculado']);
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

    public function test_pdf_de_implementos_precarga_lineas_precios_igv_y_respeta_moneda_soles(): void
    {
        $resultado = app(InterpretarCotizacionImportada::class)->interpretar([
            'tipo' => 'PDF',
            'texto' => implode("\n", [
                'IMPLEMENTOS PERU S.A.C. RUC: 20510673710 COTIZACION N° 687291',
                'miércoles, 22 de abril de 2026',
                'Señor (a): HIDROIL S.A.C. RUC: 20602691358',
                '1 LUDELE0003 FARO LATERAL 3 LED AMBAR OVALADO BI-VOLT 6 19.41 3.49 22.90 116.44',
                '2 LUDELE0004',
                'FARO LATERAL 3 LED ROJO OVALADO BI-VOLT',
                '20',
                '19.41',
                '3.49',
                '22.90',
                '388.14',
                'Valor expresado en: Soles',
                'Valor Venta Neto: S/ 504.58 I.G.V.: S/ 90.82 Importe Total: S/ 595.40',
                'CTA CTE DÓLARES 0011-0910-0100109414',
            ]),
        ], $this->requerimiento->fresh(['detalles.producto.unidadMedida']));

        $this->assertSame('687291', $resultado['cabecera']['numero_documento']);
        $this->assertSame('2026-04-22', $resultado['cabecera']['fecha_cotizacion']);
        $this->assertSame('PEN', $resultado['cabecera']['moneda']);
        $this->assertSame('INCLUIDO', $resultado['cabecera']['igv_modo_sugerido']);
        $this->assertEquals(504.58, $resultado['cabecera']['importes_documento']['subtotal']);
        $this->assertEquals(90.82, $resultado['cabecera']['importes_documento']['igv']);
        $this->assertEquals(595.40, $resultado['cabecera']['importes_documento']['total']);
        $this->assertSame('COINCIDE', $resultado['cabecera']['conciliacion']['estado']);
        $this->assertEquals(595.40, $resultado['cabecera']['conciliacion']['total_calculado']);
        $this->assertEquals(0, $resultado['cabecera']['conciliacion']['diferencia']);
        $this->assertSame(
            'Precios finales con IGV incluido',
            $resultado['cabecera']['conciliacion']['interpretacion']
        );
        $this->assertCount(2, $resultado['detalles']);
        $this->assertSame('LUDELE0003', $resultado['detalles'][0]['codigo_documento']);
        $this->assertEquals(6, $resultado['detalles'][0]['cantidad']);
        $this->assertEquals(22.90, $resultado['detalles'][0]['precio_unitario']);
        $this->assertEquals(19.41, $resultado['detalles'][0]['precio_unitario_neto_documento']);
        $this->assertEquals(3.49, $resultado['detalles'][0]['igv_unitario_documento']);
        $this->assertEquals(22.90, $resultado['detalles'][0]['precio_unitario_total_documento']);
        $this->assertSame('INCLUIDO', $resultado['detalles'][0]['igv_modo']);
        $this->assertNull($resultado['detalles'][0]['producto_id']);
        $this->assertSame('SIN_COINCIDENCIA', $resultado['detalles'][0]['coincidencia']);
        $this->assertEquals(20, $resultado['detalles'][1]['cantidad']);
        $this->assertEquals(22.90, $resultado['detalles'][1]['precio_unitario']);
    }

    public function test_contactos_diferencian_la_accion_y_etiquetan_el_contador_de_proveedores(): void
    {
        $vista = file_get_contents(resource_path('views/requerimientos_compra/show.blade.php'));

        $this->assertStringContainsString('Usar este proveedor', $vista);
        $this->assertStringContainsString("proveedor{{ \$contactos->count() === 1 ? '' : 'es' }}", $vista);
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
            ->assertSee('Documento precargado · revisión obligatoria')
            ->assertSee($importacion->nombre_original)
            ->assertSee('name="importacion_cotizacion_id"', false);
    }

    public function test_vista_previa_muestra_control_de_conciliacion_del_documento(): void
    {
        $importacion = $this->crearImportacionBorrador('PDF');
        $importacion->update([
            'datos_extraidos' => [
                'cabecera' => [
                    'numero_documento' => '687291',
                    'fecha_cotizacion' => '2026-04-22',
                    'moneda' => 'PEN',
                    'importes_documento' => [
                        'subtotal' => 504.58,
                        'igv' => 90.82,
                        'total' => 595.40,
                    ],
                    'conciliacion' => [
                        'estado' => 'COINCIDE',
                        'interpretacion' => 'Precios finales con IGV incluido',
                    ],
                ],
                'detalles' => [],
            ],
        ]);

        $this->actingAs($this->logistica)
            ->withSession(['_old_input' => $this->payloadPrefill($importacion)])
            ->get(route('cotizaciones-proveedor.create', [
                'requisicion_id' => $this->requerimiento->id,
                'proveedor_id' => $this->proveedor->id,
                'importacion_id' => $importacion->id,
            ]))
            ->assertOk()
            ->assertSee('Conciliación de importes')
            ->assertSee('Total del documento')
            ->assertSee('Precios finales con IGV incluido')
            ->assertSee('data-document-total="595.4000"', false)
            ->assertSee('El sistema no registrará la cotización mientras exista una diferencia.');
    }

    public function test_backend_bloquea_importacion_cuando_total_base_o_igv_no_coinciden(): void
    {
        $importacion = $this->crearImportacionBorrador('PDF');
        $this->asignarImportesDocumento($importacion, 504.58, 90.82, 595.40);
        $detalleReq = $this->requerimiento->detalles()->firstOrFail();

        $this->actingAs($this->logistica)
            ->from(route('cotizaciones-proveedor.create'))
            ->post(
                route('cotizaciones-proveedor.store'),
                $this->payloadCotizacion($importacion, $detalleReq->id)
            )
            ->assertRedirect(route('cotizaciones-proveedor.create'))
            ->assertSessionHasErrors('reconciliacion_documento');

        $this->assertDatabaseCount('cotizaciones', 0);
        $this->assertSame('BORRADOR', $importacion->fresh()->estado);
    }

    public function test_backend_confirma_importacion_cuando_total_base_e_igv_coinciden(): void
    {
        $importacion = $this->crearImportacionBorrador('PDF');
        $this->asignarImportesDocumento($importacion, 504.58, 90.82, 595.40);
        $detalleReq = $this->requerimiento->detalles()->firstOrFail();
        $payload = $this->payloadCotizacion($importacion, $detalleReq->id);
        $payload['detalles'][0]['cantidad'] = 26;
        $payload['detalles'][0]['precio_unitario'] = 22.90;
        $payload['detalles'][0]['igv_modo'] = 'INCLUIDO';

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-proveedor.store'), $payload)
            ->assertRedirect();

        $cotizacion = Cotizacion::query()->firstOrFail();

        $this->assertEquals(504.58, round((float) $cotizacion->subtotal, 2));
        $this->assertEquals(90.82, round((float) $cotizacion->impuesto, 2));
        $this->assertEquals(595.40, round((float) $cotizacion->total, 2));
        $this->assertSame('CONFIRMADA', $importacion->fresh()->estado);
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

    public function test_igv_incierto_y_pdf_sin_lineas_no_se_completan_mediante_supuestos(): void
    {
        $resultadoExcel = app(InterpretarCotizacionImportada::class)->interpretar([
            'tipo' => 'EXCEL',
            'texto' => "RUC: {$this->proveedor->ruc}\nCOTIZACIÓN N° SIN-IGV",
            'filas' => [
                ['Código', 'Descripción', 'Cantidad', 'Precio unitario'],
                ['11053', 'RETEN HID. 85X98X9', 3, 40],
            ],
        ], $this->requerimiento->fresh(['detalles.producto.unidadMedida']));

        $this->assertNull($resultadoExcel['cabecera']['igv_modo_sugerido']);
        $this->assertNull($resultadoExcel['detalles'][0]['igv_modo']);

        $resultadoPdf = app(InterpretarCotizacionImportada::class)->interpretar([
            'tipo' => 'PDF',
            'texto' => "COTIZACIÓN N° SIN-LINEAS\nRUC {$this->proveedor->ruc}\nDocumento sin tabla reconocible",
        ], $this->requerimiento->fresh(['detalles.producto.unidadMedida']));

        $this->assertSame([], $resultadoPdf['detalles']);
        $this->assertStringContainsString(
            'No se asumirá que el proveedor cotizó todos los productos',
            implode(' ', $resultadoPdf['advertencias'])
        );
    }

    public function test_borrador_importado_puede_existir_sin_requerimiento(): void
    {
        $importacion = ImportacionCotizacionProveedor::query()->create([
            'requisicion_id' => null,
            'proveedor_id' => $this->proveedor->id,
            'tipo_archivo' => 'EXCEL',
            'nombre_original' => 'cotizacion-libre.xlsx',
            'ruta_archivo' => 'cotizaciones-proveedor/importaciones/cotizacion-libre.xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'datos_extraidos' => ['cabecera' => [], 'detalles' => []],
            'advertencias' => [],
            'estado' => 'BORRADOR',
            'creado_por' => $this->logistica->id,
        ]);

        $this->assertNull($importacion->requisicion_id);

        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-proveedor.importacion.reanudar', $importacion))
            ->assertRedirect();

        $detalleReq = $this->requerimiento->detalles()->firstOrFail();
        $this->actingAs($this->logistica)
            ->post(
                route('cotizaciones-proveedor.store'),
                $this->payloadCotizacion($importacion, $detalleReq->id)
            )
            ->assertRedirect();

        $this->assertSame($this->requerimiento->id, Cotizacion::query()->firstOrFail()->requisicion_id);
        $this->assertSame($this->requerimiento->id, $importacion->fresh()->requisicion_id);
    }

    public function test_mismo_borrador_no_puede_confirmarse_dos_veces(): void
    {
        $importacion = $this->crearImportacionBorrador('EXCEL');
        $detalleReq = $this->requerimiento->detalles()->firstOrFail();
        $payload = $this->payloadCotizacion($importacion, $detalleReq->id);

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-proveedor.store'), $payload)
            ->assertRedirect();

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-proveedor.store'), $payload)
            ->assertSessionHasErrors('importacion_cotizacion_id');

        $this->assertDatabaseCount('cotizaciones', 1);
        $this->assertSame('CONFIRMADA', $importacion->fresh()->estado);
    }

    public function test_backend_rechaza_detalle_que_pertenece_a_otro_requerimiento(): void
    {
        $otroRequerimiento = Requisicion::query()->create([
            'codigo' => 'REQ-171221-OTRO',
            'fecha_solicitud' => now()->toDateString(),
            'origen' => 'REPOSICION',
            'descripcion' => 'Otro requerimiento.',
            'prioridad' => 'NORMAL',
            'estado' => 'COTIZANDO',
            'solicitado_por' => $this->logistica->id,
        ]);
        $detalleAjeno = $otroRequerimiento->detalles()->create([
            'producto_id' => $this->producto->id,
            'cantidad_solicitada' => 2,
            'cantidad_sugerida' => 2,
            'cantidad_atendida' => 0,
        ]);
        $importacion = $this->crearImportacionBorrador('EXCEL');

        $this->actingAs($this->logistica)
            ->post(
                route('cotizaciones-proveedor.store'),
                $this->payloadCotizacion($importacion, $detalleAjeno->id)
            )
            ->assertSessionHasErrors('detalles.0.producto_id');

        $this->assertDatabaseCount('cotizaciones', 0);
        $this->assertSame('BORRADOR', $importacion->fresh()->estado);
    }

    public function test_evidencia_importada_sobrevive_a_error_de_validacion(): void
    {
        $importacion = $this->crearImportacionBorrador('EXCEL');
        $detalleReq = $this->requerimiento->detalles()->firstOrFail();
        $payload = $this->payloadCotizacion($importacion, $detalleReq->id);
        $payload['detalles'][0]['precio_unitario'] = '';
        $payload['detalles'][0]['codigo_importado'] = 'COD-PDF-01';
        $payload['detalles'][0]['descripcion_importada'] = 'Descripción original del proveedor';
        $payload['detalles'][0]['coincidencia_importada'] = 'SUGERIDA';

        $this->actingAs($this->logistica)
            ->from(route('cotizaciones-proveedor.create'))
            ->post(route('cotizaciones-proveedor.store'), $payload)
            ->assertRedirect(route('cotizaciones-proveedor.create'))
            ->assertSessionHasErrors('detalles.0.precio_unitario')
            ->assertSessionHasInput('detalles.0.codigo_importado', 'COD-PDF-01')
            ->assertSessionHasInput('detalles.0.descripcion_importada', 'Descripción original del proveedor')
            ->assertSessionHasInput('detalles.0.coincidencia_importada', 'SUGERIDA');
    }

    private function payloadCotizacion(
        ImportacionCotizacionProveedor $importacion,
        int $requisicionDetalleId
    ): array {
        return [
            'importacion_cotizacion_id' => $importacion->id,
            'proveedor_id' => $this->proveedor->id,
            'requisicion_id' => $this->requerimiento->id,
            'numero_documento' => 'EXT-171221-' . $importacion->id,
            'fecha_cotizacion' => now()->toDateString(),
            'fecha_validez' => null,
            'moneda' => 'PEN',
            'tipo_cambio' => null,
            'descuento_global_modo' => 'SIN_DESCUENTO',
            'descuento_global_tipo' => null,
            'descuento_global_valor' => null,
            'condiciones_pago' => null,
            'condiciones_entrega' => null,
            'observacion' => null,
            'detalles' => [[
                'requisicion_detalle_id' => $requisicionDetalleId,
                'producto_id' => $this->producto->id,
                'cantidad' => 4,
                'precio_unitario' => 35.5,
                'descuento_modo' => 'SIN_DESCUENTO',
                'descuento_tipo' => null,
                'descuento_valor' => null,
                'igv_modo' => 'NO_APLICA',
                'marca_ofertada' => null,
                'observacion' => null,
            ]],
        ];
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

    private function asignarImportesDocumento(
        ImportacionCotizacionProveedor $importacion,
        float $subtotal,
        float $igv,
        float $total
    ): void {
        $datos = $importacion->datos_extraidos;
        $datos['cabecera']['moneda'] = 'PEN';
        $datos['cabecera']['importes_documento'] = [
            'subtotal' => $subtotal,
            'igv' => $igv,
            'total' => $total,
        ];

        $importacion->update(['datos_extraidos' => $datos]);
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
