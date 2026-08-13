<?php

namespace Tests\Feature;

use App\Models\Cotizacion;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\Role;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\Compras\Importacion\InterpretarCotizacionImportada;
use App\Services\Compras\ResolverVinculacionProductoCotizado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase17123VinculacionInteligenteProductosTest extends TestCase
{
    use RefreshDatabase;

    private User $logistica;
    private UnidadMedida $unidad;
    private Producto $solicitado;
    private Requisicion $requerimiento;
    private Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistica = User::query()->create([
            'role_id' => Role::query()->where('codigo', 'COMERCIAL_LOGISTICA')->firstOrFail()->id,
            'username' => 'logistica_17123',
            'email' => 'logistica_17123@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);

        $this->unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );

        $this->solicitado = $this->crearProducto(
            '10011',
            'RETEN HIDRAULICO 85 X 98 X 9'
        );

        $this->requerimiento = Requisicion::query()->create([
            'codigo' => 'REQ-17123-01',
            'fecha_solicitud' => now()->toDateString(),
            'origen' => 'REPOSICION',
            'descripcion' => 'Prueba de vinculación inteligente.',
            'prioridad' => 'ALTA',
            'estado' => 'COTIZANDO',
            'solicitado_por' => $this->logistica->id,
            'enviado_por' => $this->logistica->id,
            'enviado_en' => now(),
        ]);
        $this->requerimiento->detalles()->create([
            'producto_id' => $this->solicitado->id,
            'cantidad_solicitada' => 10,
            'cantidad_sugerida' => 10,
            'cantidad_atendida' => 0,
        ]);

        $this->proveedor = Proveedor::query()->create([
            'ruc' => '20617123001',
            'razon_social' => 'Proveedor Vinculación Inteligente SAC',
            'nombre_comercial' => 'Proveedor 17.1.2.3',
            'estado' => true,
        ]);
    }

    public function test_coincidencia_exacta_y_unica_del_requerimiento_se_vincula_automaticamente(): void
    {
        $resultado = app(ResolverVinculacionProductoCotizado::class)->resolver(
            '10011',
            'Retén hidráulico 85 x 98 x 9',
            $this->requerimiento->fresh(['detalles.producto.unidadMedida'])
        );

        $this->assertSame($this->solicitado->id, $resultado['producto_id']);
        $this->assertSame(
            $this->requerimiento->detalles()->firstOrFail()->id,
            $resultado['requisicion_detalle_id']
        );
        $this->assertSame('SOLICITADO', $resultado['tipo_vinculacion']);
        $this->assertSame('AUTOMATICA', $resultado['vinculacion_origen']);
        $this->assertSame('EXACTA', $resultado['coincidencia']);
    }

    public function test_similitud_aproximada_propone_candidatos_pero_no_selecciona_producto(): void
    {
        $resultado = app(ResolverVinculacionProductoCotizado::class)->resolver(
            'COD-PROVEEDOR-77',
            'Reten hidraulico 85x98x9 para equipo',
            $this->requerimiento->fresh(['detalles.producto.unidadMedida'])
        );

        $this->assertNull($resultado['producto_id']);
        $this->assertNull($resultado['requisicion_detalle_id']);
        $this->assertSame('SUGERIDA', $resultado['coincidencia']);
        $this->assertNotEmpty($resultado['candidatos']);
        $this->assertSame('REQUERIMIENTO', $resultado['candidatos'][0]['ambito']);
        $this->assertSame($this->solicitado->id, $resultado['candidatos'][0]['producto_id']);
    }

    public function test_interpretador_no_confirma_una_sugerencia_aproximada(): void
    {
        $resultado = app(InterpretarCotizacionImportada::class)->interpretar([
            'tipo' => 'EXCEL',
            'texto' => 'COTIZACIÓN N° SUG-01 Moneda PEN',
            'filas' => [
                ['Código', 'Descripción', 'Cantidad', 'Precio unitario'],
                ['EXT-10011', 'Reten hidraulico 85x98x9 para equipo', 4, 20],
            ],
        ], $this->requerimiento->fresh(['detalles.producto.unidadMedida']));

        $this->assertNull($resultado['detalles'][0]['producto_id']);
        $this->assertSame('SUGERIDA', $resultado['detalles'][0]['coincidencia']);
        $this->assertNotEmpty($resultado['detalles'][0]['candidatos_vinculacion']);
    }

    public function test_producto_exacto_del_catalogo_sin_inventario_se_detecta_como_adicional(): void
    {
        $catalogo = $this->crearProducto('CAT-900', 'SENSOR DE PRESION 400 BAR');

        $resultado = app(ResolverVinculacionProductoCotizado::class)->resolver(
            'CAT-900',
            'Sensor de presión 400 bar'
        );

        $this->assertSame($catalogo->id, $resultado['producto_id']);
        $this->assertNull($resultado['requisicion_detalle_id']);
        $this->assertSame('ADICIONAL', $resultado['tipo_vinculacion']);
        $this->assertSame('EXACTA_CATALOGO', $resultado['coincidencia']);
        $this->assertDatabaseMissing('inventarios', ['producto_id' => $catalogo->id]);
    }

    public function test_endpoint_devuelve_lineas_del_requerimiento_y_candidatos_sin_exigir_stock(): void
    {
        $this->actingAs($this->logistica)
            ->getJson(route('cotizaciones-proveedor.productos.vinculacion', [
                'codigo' => 'EXT-10011',
                'descripcion' => 'Reten hidraulico 85x98x9 para equipo',
                'requisicion_id' => $this->requerimiento->id,
            ]))
            ->assertOk()
            ->assertJsonPath('producto_id', null)
            ->assertJsonPath('coincidencia', 'SUGERIDA')
            ->assertJsonPath('candidatos.0.ambito', 'REQUERIMIENTO')
            ->assertJsonPath('lineas_requerimiento.0.producto_id', $this->solicitado->id);
    }

    public function test_guarda_alternativa_sin_modificar_producto_del_requerimiento(): void
    {
        $alternativa = $this->crearProducto('ALT-10011', 'RETEN HIDRAULICO 85 X 98 X 10');
        $detalleRequerido = $this->requerimiento->detalles()->firstOrFail();

        $respuesta = $this->actingAs($this->logistica)
            ->post(route('cotizaciones-proveedor.store'), $this->payload([
                'requisicion_detalle_id' => $detalleRequerido->id,
                'producto_id' => $alternativa->id,
                'tipo_vinculacion' => 'ALTERNATIVA',
                'vinculacion_origen' => 'CONFIRMADA',
                'vinculacion_confirmada' => true,
                'codigo_importado' => 'ALT-PROV-01',
                'descripcion_importada' => 'Retén equivalente ofertado',
                'coincidencia_importada' => 'SUGERIDA',
            ]));

        $cotizacion = Cotizacion::query()->latest('id')->firstOrFail();
        $respuesta->assertRedirect(route('cotizaciones-proveedor.show', $cotizacion));
        $this->assertDatabaseHas('cotizacion_detalles', [
            'cotizacion_id' => $cotizacion->id,
            'requisicion_detalle_id' => $detalleRequerido->id,
            'producto_id' => $alternativa->id,
            'tipo_vinculacion' => 'ALTERNATIVA',
            'vinculacion_origen' => 'CONFIRMADA',
            'codigo_documento' => 'ALT-PROV-01',
        ]);
        $this->assertSame($this->solicitado->id, $detalleRequerido->fresh()->producto_id);

        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-proveedor.show', $cotizacion))
            ->assertOk()
            ->assertSee('Alternativa ofrecida')
            ->assertSee('Solicitado: ' . $this->solicitado->codigo);
    }

    public function test_rechaza_marcar_el_mismo_producto_solicitado_como_alternativa(): void
    {
        $detalleRequerido = $this->requerimiento->detalles()->firstOrFail();

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-proveedor.store'), $this->payload([
                'requisicion_detalle_id' => $detalleRequerido->id,
                'producto_id' => $this->solicitado->id,
                'tipo_vinculacion' => 'ALTERNATIVA',
                'vinculacion_confirmada' => true,
            ]))
            ->assertSessionHasErrors('detalles.0.tipo_vinculacion');

        $this->assertDatabaseCount('cotizaciones', 0);
    }

    public function test_importacion_dudosa_exige_confirmacion_humana_del_producto(): void
    {
        $detalleRequerido = $this->requerimiento->detalles()->firstOrFail();

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-proveedor.store'), $this->payload([
                'requisicion_detalle_id' => $detalleRequerido->id,
                'producto_id' => $this->solicitado->id,
                'tipo_vinculacion' => 'SOLICITADO',
                'vinculacion_confirmada' => false,
                'codigo_importado' => 'EXT-DUDOSO',
                'descripcion_importada' => 'Descripción aproximada',
                'coincidencia_importada' => 'SUGERIDA',
            ]))
            ->assertSessionHasErrors('detalles.0.producto_id');

        $this->assertDatabaseCount('cotizaciones', 0);
    }

    public function test_alta_rapida_reutiliza_producto_con_descripcion_exacta(): void
    {
        $respuesta = $this->actingAs($this->logistica)->postJson(
            route('cotizaciones-proveedor.productos.registro-rapido'),
            [
                'codigo' => 'NUEVO-01',
                'descripcion' => $this->solicitado->descripcion,
                'unidad_medida_id' => $this->unidad->id,
            ]
        );

        $respuesta
            ->assertUnprocessable()
            ->assertJsonPath('producto_existente.id', $this->solicitado->id);
        $this->assertDatabaseCount('productos', 1);
    }

    public function test_alta_rapida_pide_segunda_confirmacion_ante_similitud_alta(): void
    {
        $this->crearProducto('BOM-01', 'BOMBA HIDRAULICA DE ENGRANAJES PARKER');
        $payload = [
            'codigo' => 'BOM-02',
            'descripcion' => 'BOMBA HIDRAULICA ENGRANAJES PARKER',
            'unidad_medida_id' => $this->unidad->id,
        ];

        $this->actingAs($this->logistica)
            ->postJson(route('cotizaciones-proveedor.productos.registro-rapido'), $payload)
            ->assertUnprocessable()
            ->assertJsonPath('requiere_confirmacion_similitud', true);

        $this->actingAs($this->logistica)
            ->postJson(route('cotizaciones-proveedor.productos.registro-rapido'), [
                ...$payload,
                'confirmar_similitud' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('producto.codigo', 'BOM-02');

        $this->assertDatabaseHas('productos', ['codigo' => 'BOM-02']);
        $this->assertDatabaseCount('inventarios', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_interfaz_explica_solicitado_alternativa_y_adicional(): void
    {
        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-proveedor.create', [
                'requisicion_id' => $this->requerimiento->id,
            ]))
            ->assertOk()
            ->assertSee('Relación con el requerimiento')
            ->assertSee('Producto solicitado')
            ->assertSee('Alternativa ofrecida')
            ->assertSee('Producto adicional')
            ->assertSee('El requerimiento original no se modifica.');
    }

    private function crearProducto(string $codigo, string $descripcion): Producto
    {
        return Producto::query()->create([
            'unidad_medida_id' => $this->unidad->id,
            'codigo' => $codigo,
            'descripcion' => $descripcion,
            'estado' => true,
        ]);
    }

    /** @param array<string, mixed> $detalle */
    private function payload(array $detalle): array
    {
        return [
            'proveedor_id' => $this->proveedor->id,
            'requisicion_id' => $this->requerimiento->id,
            'numero_documento' => 'DOC-17123-' . uniqid(),
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
                ...$detalle,
                'cantidad' => 4,
                'precio_unitario' => 25,
                'descuento_modo' => 'SIN_DESCUENTO',
                'descuento_tipo' => null,
                'descuento_valor' => null,
                'igv_modo' => 'NO_APLICA',
                'marca_ofertada' => null,
                'observacion' => null,
            ]],
        ];
    }
}
