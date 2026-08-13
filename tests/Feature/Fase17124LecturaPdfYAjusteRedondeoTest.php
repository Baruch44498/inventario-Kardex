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
use App\Services\Compras\Importacion\ExtractorCotizacionPdf;
use App\Services\Compras\Importacion\InterpretarCotizacionImportada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class Fase17124LecturaPdfYAjusteRedondeoTest extends TestCase
{
    use RefreshDatabase;

    private User $logistica;
    private Producto $solicitado;
    private Producto $alternativa;
    private Requisicion $requisicion;
    private Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistica = User::query()->create([
            'role_id' => Role::query()->where('codigo', 'COMERCIAL_LOGISTICA')->firstOrFail()->id,
            'username' => 'logistica_17124',
            'email' => 'logistica_17124@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);

        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );

        $this->solicitado = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'LUDELE0003',
            'descripcion' => 'FARO LATERAL 3 LED AMBAR OVALADO BI-VOLT',
            'estado' => true,
        ]);
        $this->alternativa = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'ALT-LUDELE-03',
            'descripcion' => 'FARO LATERAL LED AMBAR MODELO REFORZADO',
            'estado' => true,
        ]);

        $this->requisicion = Requisicion::query()->create([
            'codigo' => 'REQ-17124-01',
            'fecha_solicitud' => now()->toDateString(),
            'origen' => 'REPOSICION',
            'descripcion' => 'Prueba de lectura y conciliación documental.',
            'prioridad' => 'ALTA',
            'estado' => 'COTIZANDO',
            'solicitado_por' => $this->logistica->id,
        ]);
        $this->requisicion->detalles()->create([
            'producto_id' => $this->solicitado->id,
            'cantidad_solicitada' => 6,
            'cantidad_sugerida' => 6,
            'cantidad_atendida' => 0,
        ]);

        $this->proveedor = Proveedor::query()->create([
            'ruc' => '20510673710',
            'razon_social' => 'Proveedor Fase 17.1.2.4 SAC',
            'nombre_comercial' => 'Proveedor 17.1.2.4',
            'estado' => true,
        ]);
    }

    public function test_pdf_crystal_reports_lee_cantidad_antes_del_codigo_y_descripcion_multilinea(): void
    {
        $texto = $this->textoReconstruidoDesdeCoordenadas([
            ['Cotización N°', 'CC*2025-0012279730'],
            ['Fecha : 21/10/2025'],
            ['Moneda : Dolares Americanos'],
            ['ITEM', 'CANT.', 'UDM', 'CODIGO', 'PRODUCTO', 'V. UNIT', 'V. TOTAL'],
            ['1', '2.00', 'Pza', '856215640', 'VÁLVULA MARIPOSA WAFER FE. FUNDIDO ASTM A126 P/BRIDAS B16.5', '75.06', '150.12'],
            ['CLASE 150 DISCO 316 (CF8M) A/BUNA-N/200 PSI (WOG) C/PALANCA'],
            ['TIPO INDUSTRIAL REX 8"'],
            ['Los precios unitarios no incluyen IGV (18%)'],
            ['VALOR DE VENTA', '$ 150.12'],
            ['I.G.V.', '$ 27.02'],
            ['IMPORTE TOTAL', '$ 177.14'],
        ]);

        $resultado = app(InterpretarCotizacionImportada::class)->interpretar([
            'tipo' => 'PDF',
            'texto' => $texto,
        ], null);

        $this->assertSame('CC*2025-0012279730', $resultado['cabecera']['numero_documento']);
        $this->assertSame('USD', $resultado['cabecera']['moneda']);
        $this->assertSame('2025-10-21', $resultado['cabecera']['fecha_cotizacion']);
        $this->assertCount(1, $resultado['detalles']);
        $this->assertSame('856215640', $resultado['detalles'][0]['codigo_documento']);
        $this->assertEquals(2, $resultado['detalles'][0]['cantidad']);
        $this->assertEquals(75.06, $resultado['detalles'][0]['precio_unitario']);
        $this->assertStringContainsString('TIPO INDUSTRIAL REX 8', $resultado['detalles'][0]['descripcion_documento']);
        $this->assertSame('AGREGAR', $resultado['detalles'][0]['igv_modo']);
        $this->assertSame('COINCIDE', $resultado['cabecera']['conciliacion']['estado']);
        $this->assertEquals(177.14, $resultado['cabecera']['conciliacion']['total_calculado']);
    }

    public function test_pdf_con_no_aplica_conserva_producto_solicitado_y_bloquea_cabecera_inconsistente(): void
    {
        $texto = $this->textoReconstruidoDesdeCoordenadas([
            ['PROVEEDOR DEMO S.A.C.', 'COTIZACION N°'],
            ['TEST-001'],
            ['Moneda: Soles'],
            ['N°', 'SKU', 'Producto solicitado', 'Alternativa ofrecida', 'Cant.', 'P. Unit. Neto', 'IGV', 'P. Unit. Total', 'Sub Total'],
            ['1', 'LUDELE0003', 'FARO LATERAL 3 LED AMBAR', 'NO APLICA - Se cotiza exactamente el', '6', '19.41', '3.49', '22.90', '116.46'],
            ['OVALADO BI-VOLT', 'producto solicitado'],
            ['2', 'LUDELE0004', 'FARO LATERAL 3 LED ROJO', 'NO APLICA - Se cotiza exactamente el', '20', '19.41', '3.49', '22.90', '388.20'],
            ['OVALADO BI-VOLT', 'producto solicitado'],
            ['Valor Venta Neto', 'S/', '504.66'],
            ['I.G.V. 18%', 'S/', '90.84'],
            ['Importe Total', 'S/', '595.50'],
        ]);

        $resultado = app(InterpretarCotizacionImportada::class)->interpretar([
            'tipo' => 'PDF',
            'texto' => $texto,
        ], $this->requisicion->fresh(['detalles.producto.unidadMedida']));

        $this->assertCount(2, $resultado['detalles']);
        $primera = $resultado['detalles'][0];
        $this->assertSame('TEST-001', $resultado['cabecera']['numero_documento']);
        $this->assertSame('SOLICITADO', $primera['tipo_vinculacion']);
        $this->assertSame($this->solicitado->id, $primera['producto_id']);
        $this->assertSame(
            $this->requisicion->detalles()->firstOrFail()->id,
            $primera['requisicion_detalle_id']
        );
        $this->assertSame($this->solicitado->descripcion, $primera['descripcion_solicitada_documento']);
        $this->assertStringStartsWith('NO APLICA', $primera['alternativa_ofrecida_documento']);
        $this->assertSame('DIFERENCIA', $resultado['cabecera']['conciliacion']['estado']);
        $this->assertEquals(0.10, $resultado['cabecera']['conciliacion']['diferencia']);
    }

    public function test_excel_distingue_una_alternativa_real_y_la_relaciona_con_lo_solicitado(): void
    {
        $resultado = app(InterpretarCotizacionImportada::class)->interpretar([
            'tipo' => 'EXCEL',
            'texto' => 'COTIZACIÓN N° ALT-001 Moneda PEN',
            'filas' => [
                ['Código', 'Producto solicitado', 'Alternativa ofrecida', 'Cantidad', 'Precio unitario', 'IGV'],
                ['ALT-LUDELE-03', $this->solicitado->descripcion, $this->alternativa->descripcion, 6, 21.50, 'Más IGV'],
            ],
        ], $this->requisicion->fresh(['detalles.producto.unidadMedida']));

        $linea = $resultado['detalles'][0];
        $this->assertSame('ALTERNATIVA', $linea['tipo_vinculacion']);
        $this->assertSame($this->alternativa->id, $linea['producto_id']);
        $this->assertSame(
            $this->requisicion->detalles()->firstOrFail()->id,
            $linea['requisicion_detalle_id']
        );
        $this->assertSame($this->alternativa->descripcion, $linea['descripcion_documento']);
        $this->assertSame('EXACTA_CATALOGO', $linea['coincidencia']);
    }

    public function test_pdf_vertical_separa_una_alternativa_real_usando_el_requerimiento(): void
    {
        $resultado = app(InterpretarCotizacionImportada::class)->interpretar([
            'tipo' => 'PDF',
            'texto' => implode("\n", [
                'COTIZACIÓN N° ALT-PDF-01',
                'Moneda: Soles',
                'Producto solicitado',
                'Alternativa ofrecida',
                '1',
                'ALT-LUDELE-03',
                'FARO LATERAL 3 LED AMBAR',
                'OVALADO BI-VOLT',
                'FARO LATERAL LED AMBAR',
                'MODELO REFORZADO',
                '6',
                '21.50',
                '3.87',
                '25.37',
                '129.00',
                'Importe Total S/ 152.22',
            ]),
        ], $this->requisicion->fresh(['detalles.producto.unidadMedida']));

        $linea = $resultado['detalles'][0];
        $this->assertSame('ALTERNATIVA', $linea['tipo_vinculacion']);
        $this->assertSame($this->alternativa->id, $linea['producto_id']);
        $this->assertSame($this->solicitado->descripcion, $linea['descripcion_solicitada_documento']);
        $this->assertSame($this->alternativa->descripcion, $linea['alternativa_ofrecida_documento']);
        $this->assertSame(
            $this->requisicion->detalles()->firstOrFail()->id,
            $linea['requisicion_detalle_id']
        );
    }

    public function test_cotizacion_285625_propone_menos_un_centimo_sin_alterar_sus_lineas(): void
    {
        $resultado = app(InterpretarCotizacionImportada::class)->interpretar([
            'tipo' => 'PDF',
            'texto' => implode("\n", [
                'IMPLEMENTOS PERU S.A.C. RUC: 20510673710 COTIZACION N° 285625',
                'Valor expresado en : Soles',
                '1 BALELE0003 CIRCULINA ESTROBOSCOPICA XENON IMANTADA 12-80 1 161.56 29.08 190.64 161.56',
                '2 STKACC0182 EXTINTOR DE 9KG - POLVO QUIMICO SECO AL 75% 1 113.47 20.43 133.90 113.47',
                '3 TOKELE0005 FARO 2 LATERAL 3LED AMBAR BI-VOLT - PACK X 2 1 16.86 3.04 19.90 16.86',
                '4 TOKELE0006 FARO 2 LATERAL 3 LED ROJO BI-VOLT - PACK X 2 1 17.20 3.10 20.30 17.20',
                '5 STKELE0006 FARO PIRATA CUADRADO 16 LED MV STARK 1 21.52 3.87 25.40 21.52',
                '6 HELELE0003 FARO HELLA COMET 550 AMBAR 1 126.12 22.70 148.82 126.12',
                '7 ECOACC0009 PERTIGA 10 PIES - LUZ LED 1 232.54 41.86 274.40 232.54',
                '8 HELACC0001 ALARMA RETROCESO 110 DECIBELES 9-48V HELLA 1 31.02 5.58 36.60 31.02',
                'Valor Venta Neto : S/ 720.30',
                'I.G.V. : S/ 129.65',
                'Importe Total : S/ 849.95',
            ]),
        ], null);

        $conciliacion = $resultado['cabecera']['conciliacion'];
        $this->assertCount(8, $resultado['detalles']);
        $this->assertSame('AJUSTE_REDONDEO', $conciliacion['estado']);
        $this->assertEquals(849.96, $conciliacion['total_calculado']);
        $this->assertEquals(-0.01, $conciliacion['ajuste_redondeo_propuesto']);
        $this->assertTrue($conciliacion['requiere_confirmacion_ajuste']);
        $this->assertEquals(190.64, $resultado['detalles'][0]['precio_unitario']);
        $this->assertSame('INCLUIDO', $resultado['detalles'][0]['igv_modo']);
    }

    public function test_backend_exige_confirmacion_para_un_ajuste_admisible(): void
    {
        $importacion = $this->crearImportacion(100.00, 17.99, 117.99);

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-proveedor.store'), $this->payload($importacion))
            ->assertSessionHasErrors('ajuste_redondeo_confirmado');

        $this->assertDatabaseCount('cotizaciones', 0);
        $this->assertSame('BORRADOR', $importacion->fresh()->estado);
    }

    public function test_backend_guarda_ajuste_total_documental_y_confirmador_sin_tocar_linea(): void
    {
        $importacion = $this->crearImportacion(100.00, 17.99, 117.99);
        $payload = $this->payload($importacion);
        $payload['ajuste_redondeo_confirmado'] = 1;

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-proveedor.store'), $payload)
            ->assertRedirect();

        $cotizacion = Cotizacion::query()->with('detalles')->firstOrFail();
        $this->assertEquals(118.00, $cotizacion->total_calculado);
        $this->assertEquals(-0.01, $cotizacion->ajuste_redondeo);
        $this->assertEquals(117.99, $cotizacion->total);
        $this->assertEquals(117.99, $cotizacion->total_documento);
        $this->assertSame($this->logistica->id, $cotizacion->ajuste_redondeo_confirmado_por);
        $this->assertNotNull($cotizacion->ajuste_redondeo_confirmado_en);
        $this->assertEquals(118.00, $cotizacion->detalles->first()->total);
        $this->assertSame('CONFIRMADA', $importacion->fresh()->estado);
    }

    public function test_backend_rechaza_como_redondeo_una_diferencia_mayor_a_cinco_centimos(): void
    {
        $importacion = $this->crearImportacion(100.00, 17.90, 117.90);
        $payload = $this->payload($importacion);
        $payload['ajuste_redondeo_confirmado'] = 1;

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-proveedor.store'), $payload)
            ->assertSessionHasErrors('reconciliacion_documento');

        $this->assertDatabaseCount('cotizaciones', 0);
    }

    public function test_registro_manual_no_puede_inyectar_un_ajuste_desde_el_formulario(): void
    {
        $payload = $this->payload(null);
        $payload['ajuste_redondeo_confirmado'] = 1;
        $payload['ajuste_redondeo'] = -0.05;
        $payload['total'] = 117.95;

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-proveedor.store'), $payload)
            ->assertRedirect();

        $cotizacion = Cotizacion::query()->firstOrFail();
        $this->assertEquals(118.00, $cotizacion->total_calculado);
        $this->assertEquals(0.00, $cotizacion->ajuste_redondeo);
        $this->assertEquals(118.00, $cotizacion->total);
        $this->assertNull($cotizacion->ajuste_redondeo_confirmado_por);
    }

    public function test_detalle_muestra_trazabilidad_del_ajuste_confirmado(): void
    {
        $importacion = $this->crearImportacion(100.00, 17.99, 117.99);
        $payload = $this->payload($importacion);
        $payload['ajuste_redondeo_confirmado'] = 1;

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-proveedor.store'), $payload);

        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-proveedor.show', Cotizacion::query()->firstOrFail()))
            ->assertOk()
            ->assertSee('Total calculado')
            ->assertSee('Ajuste por redondeo')
            ->assertSee('Total final pagable')
            ->assertSee('Confirmado por logistica_17124');
    }

    /**
     * Simula la salida X/Y de Smalot para comprobar la etapa que faltaba en
     * las pruebas originales: reconstruir visualmente las filas antes de
     * interpretar códigos, cantidades y precios.
     *
     * @param array<int, array<int, string>> $filas
     */
    private function textoReconstruidoDesdeCoordenadas(array $filas): string
    {
        $fragmentos = [];
        $y = 800.0;

        foreach ($filas as $fila) {
            $x = 30.0;

            foreach ($fila as $texto) {
                $fragmentos[] = [
                    [1, 0, 0, 1, $x, $y],
                    $texto,
                ];
                $x += 100.0;
            }

            $y -= 14.0;
        }

        // El orden interno se mezcla intencionalmente: la extracción real no
        // garantiza que los fragmentos lleguen por fila.
        $fragmentos = array_reverse($fragmentos);
        $pagina = new class($fragmentos) {
            public function __construct(private array $fragmentos) {}

            public function getDataTm(): array
            {
                return $this->fragmentos;
            }
        };
        $documento = new class($pagina) {
            public function __construct(private object $pagina) {}

            public function getPages(): array
            {
                return [$this->pagina];
            }
        };

        $metodo = new ReflectionMethod(ExtractorCotizacionPdf::class, 'textoOrdenadoPorPosicion');
        $metodo->setAccessible(true);

        return $metodo->invoke(new ExtractorCotizacionPdf(), $documento);
    }

    private function crearImportacion(
        float $subtotal,
        float $igv,
        float $total
    ): ImportacionCotizacionProveedor {
        return ImportacionCotizacionProveedor::query()->create([
            'requisicion_id' => $this->requisicion->id,
            'proveedor_id' => $this->proveedor->id,
            'tipo_archivo' => 'PDF',
            'nombre_original' => 'cotizacion-redondeo.pdf',
            'ruta_archivo' => 'cotizaciones-proveedor/importaciones/cotizacion-redondeo.pdf',
            'mime_type' => 'application/pdf',
            'datos_extraidos' => [
                'cabecera' => [
                    'numero_documento' => 'RED-001',
                    'fecha_cotizacion' => now()->toDateString(),
                    'moneda' => 'PEN',
                    'importes_documento' => compact('subtotal', 'igv', 'total'),
                ],
                'detalles' => [],
            ],
            'advertencias' => [],
            'estado' => 'BORRADOR',
            'creado_por' => $this->logistica->id,
        ]);
    }

    private function payload(?ImportacionCotizacionProveedor $importacion): array
    {
        $detalle = $this->requisicion->detalles()->firstOrFail();

        return [
            'importacion_cotizacion_id' => $importacion?->id,
            'proveedor_id' => $this->proveedor->id,
            'requisicion_id' => $this->requisicion->id,
            'numero_documento' => 'RED-001',
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
                'requisicion_detalle_id' => $detalle->id,
                'producto_id' => $this->solicitado->id,
                'tipo_vinculacion' => 'SOLICITADO',
                'vinculacion_origen' => 'MANUAL',
                'vinculacion_confirmada' => true,
                'cantidad' => 1,
                'precio_unitario' => 100,
                'descuento_modo' => 'SIN_DESCUENTO',
                'descuento_tipo' => null,
                'descuento_valor' => null,
                'igv_modo' => 'AGREGAR',
                'marca_ofertada' => null,
                'observacion' => null,
            ]],
        ];
    }
}
