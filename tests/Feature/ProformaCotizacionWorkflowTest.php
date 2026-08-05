<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Proforma;
use App\Models\Repisa;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProformaCotizacionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;

    private User $logistica;

    private Cliente $cliente;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = $this->usuarioConRol('ALMACEN', 'almacen_prueba');
        $this->logistica = $this->usuarioConRol('COMERCIAL_LOGISTICA', 'logistica_prueba');

        TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OV'],
            [
                'nombre' => 'Orden de venta',
                'descripcion' => null,
                'estado' => true,
            ]
        );

        $tipo = TipoCliente::query()->where('codigo', 'FINAL')->firstOrFail();
        $tipo->update(['porcentaje_ganancia' => 20]);

        $this->cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipo->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20123456789',
            'ruc' => '20123456789',
            'razon_social' => 'Cliente Industrial de Prueba SAC',
            'estado' => true,
        ]);

        $unidad = UnidadMedida::query()->create([
            'codigo' => 'UND',
            'nombre' => 'Unidad',
            'estado' => true,
        ]);
        $repisa = Repisa::query()->create([
            'codigo' => 'R-PRF-01',
            'descripcion' => 'Pruebas de proforma',
            'estado' => true,
        ]);
        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'PRD-VENTA-01',
            'descripcion' => 'Producto para cotización',
            'estado' => true,
        ]);
        Inventario::query()->create([
            'producto_id' => $this->producto->id,
            'repisa_id' => $repisa->id,
            'stock_actual' => 10,
            'stock_minimo' => 2,
            'stock_maximo' => 20,
            'costo_promedio_soles' => 100,
        ]);
    }

    public function test_flujo_completo_conserva_versiones_y_no_mueve_inventario(): void
    {
        $this->actingAs($this->almacen)
            ->post(route('proformas.store'), $this->datosProforma())
            ->assertRedirect();

        $proforma = Proforma::query()->with('detalles')->firstOrFail();

        $this->assertSame('PRF-000001', $proforma->codigo);
        $this->assertSame('BORRADOR', $proforma->estado);
        $this->assertEquals(120, (float) $proforma->detalles->first()->precio_sugerido);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertEquals(
            10,
            (float) Inventario::query()->where('producto_id', $this->producto->id)->value('stock_actual')
        );

        $this->actingAs($this->almacen)
            ->patch(route('proformas.enviar', $proforma))
            ->assertRedirect(route('proformas.show', $proforma));

        $this->actingAs($this->logistica)
            ->post(route('proformas.cotizar', $proforma), [
                'cliente_id' => $this->cliente->id,
            ])
            ->assertRedirect();

        $vrs1 = CotizacionCliente::query()->with('detalles')->firstOrFail();
        $this->assertSame('COT-000001-VRS1', $vrs1->codigo);
        $this->assertSame('ABIERTA', $vrs1->estado);

        $this->actingAs($this->logistica)
            ->put(route('cotizaciones-cliente.update', $vrs1), [
                'cliente_id' => $this->cliente->id,
                'fecha_emision' => now()->toDateString(),
                'fecha_validez' => now()->addDays(10)->toDateString(),
                'moneda' => 'PEN',
                'tipo_cambio' => null,
                'condiciones_pago' => 'Contado',
                'condiciones_entrega' => 'Entrega en almacén',
                'observacion' => 'Precio negociado',
                'detalles' => [[
                    'producto_id' => $this->producto->id,
                    'cantidad' => 2,
                    'precio_unitario' => 125,
                    'igv_modo' => 'AGREGAR',
                    'observacion' => null,
                ]],
            ])
            ->assertRedirect(route('cotizaciones-cliente.show', $vrs1));

        $this->actingAs($this->logistica)
            ->patch(route('cotizaciones-cliente.cerrar', $vrs1))
            ->assertRedirect(route('cotizaciones-cliente.show', $vrs1));

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.version', $vrs1))
            ->assertRedirect();

        $vrs2 = CotizacionCliente::query()->where('version', 2)->firstOrFail();
        $this->assertSame('COT-000001-VRS2', $vrs2->codigo);
        $this->assertSame('CERRADA', $vrs1->fresh()->estado);
        $this->assertSame('ABIERTA', $vrs2->estado);
        $this->assertDatabaseCount('cotizaciones_cliente', 2);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_almacen_no_puede_cotizar_ni_anular_la_proforma(): void
    {
        $proforma = $this->crearYEnviarProforma();

        $this->actingAs($this->almacen)
            ->post(route('proformas.cotizar', $proforma), [
                'cliente_id' => $this->cliente->id,
            ])
            ->assertForbidden();

        $this->actingAs($this->almacen)
            ->patch(route('proformas.anular', $proforma), [
                'motivo_anulacion' => 'Intento sin permiso',
            ])
            ->assertForbidden();
    }

    public function test_logistica_anula_sin_borrar_el_historial(): void
    {
        $proforma = $this->crearYEnviarProforma();

        $this->actingAs($this->logistica)
            ->post(route('proformas.cotizar', $proforma), [
                'cliente_id' => $this->cliente->id,
            ]);

        $cotizacion = CotizacionCliente::query()->firstOrFail();

        $this->actingAs($this->logistica)
            ->patch(route('proformas.anular', $proforma), [
                'motivo_anulacion' => 'El cliente canceló la solicitud',
            ])
            ->assertRedirect(route('proformas.show', $proforma));

        $this->assertDatabaseHas('proformas', [
            'id' => $proforma->id,
            'estado' => 'ANULADA',
            'motivo_anulacion' => 'El cliente canceló la solicitud',
            'anulado_por' => $this->logistica->id,
        ]);
        $this->assertDatabaseHas('cotizaciones_cliente', [
            'id' => $cotizacion->id,
            'estado' => 'ANULADA',
        ]);
        $this->assertDatabaseCount('proformas', 1);
        $this->assertDatabaseCount('cotizaciones_cliente', 1);
    }

    public function test_almacen_solo_captura_datos_operativos_de_la_proforma(): void
    {
        $formulario = $this->actingAs($this->almacen)
            ->get(route('proformas.create'));

        $formulario
            ->assertOk()
            ->assertDontSee('name="condiciones_pago"', false)
            ->assertDontSee('name="condiciones_entrega"', false)
            ->assertDontSee('name="detalles[0][igv_modo]"', false)
            ->assertDontSee('data-line-cost', false)
            ->assertDontSee('data-line-suggested', false)
            ->assertSee('Indicaciones para Logística');

        $datos = $this->datosProforma();
        $datos['condiciones_pago'] = 'Contado enviado por Almacén';
        $datos['condiciones_entrega'] = 'Entrega inmediata enviada por Almacén';
        $datos['detalles'][0]['igv_modo'] = 'AGREGAR';

        $this->actingAs($this->almacen)
            ->post(route('proformas.store'), $datos)
            ->assertRedirect();

        $proforma = Proforma::query()->with('detalles')->firstOrFail();

        $this->assertNull($proforma->condiciones_pago);
        $this->assertNull($proforma->condiciones_entrega);
        $this->assertSame('NO_APLICA', $proforma->detalles->first()->igv_modo);

        $vistaAlmacen = $this->actingAs($this->almacen)
            ->get(route('proformas.show', $proforma));

        $vistaAlmacen
            ->assertOk()
            ->assertDontSee('Costo ref.')
            ->assertDontSee('>Sugerido</th>', false)
            ->assertDontSee('>IGV</th>', false)
            ->assertDontSee('Valor sugerido')
            ->assertDontSee('Margen sugerido');

        $this->actingAs($this->logistica)
            ->get(route('proformas.show', $proforma))
            ->assertOk()
            ->assertSee('Costo ref.')
            ->assertSee('>Sugerido</th>', false)
            ->assertSee('>IGV</th>', false)
            ->assertSee('Valor sugerido')
            ->assertSee('Margen sugerido');
    }

    public function test_catalogos_de_proforma_almacen_no_exponen_costos_ni_margen(): void
    {
        $this->actingAs($this->almacen)
            ->getJson(route('catalogos.productos.buscar', [
                'contexto' => 'proforma_almacen',
                'q' => $this->producto->codigo,
            ]))
            ->assertOk()
            ->assertJsonMissingPath('items.0.costo_referencia');

        $this->actingAs($this->almacen)
            ->getJson(route('catalogos.clientes.buscar', [
                'contexto' => 'proforma_almacen',
                'q' => $this->cliente->numero_documento,
            ]))
            ->assertOk()
            ->assertJsonMissingPath('items.0.margen_porcentaje');
    }

    public function test_logistica_ve_crear_y_anular_en_un_solo_bloque(): void
    {
        $proforma = $this->crearYEnviarProforma();

        $respuesta = $this->actingAs($this->logistica)
            ->get(route('proformas.show', $proforma));

        $respuesta
            ->assertOk()
            ->assertSee('Acciones de la proforma')
            ->assertSee('Crear cotización abierta')
            ->assertSee('Anular proforma')
            ->assertSee('commercial-quote-actions__divider', false)
            ->assertDontSee('class="commercial-danger-zone"', false);

        $this->assertSame(
            1,
            substr_count($respuesta->getContent(), 'Acciones de la proforma')
        );
    }

    public function test_selector_de_ordenes_puede_desplegarse_fuera_del_panel(): void
    {
        $css = file_get_contents(public_path('css/hidroil-admin.css'));

        $this->assertMatchesRegularExpression(
            '/\.order-selector-panel\s*\{[^}]*z-index\s*:\s*5\s*;[^}]*overflow\s*:\s*visible\s*;/s',
            $css
        );
    }

    private function crearYEnviarProforma(): Proforma
    {
        $this->actingAs($this->almacen)
            ->post(route('proformas.store'), $this->datosProforma());
        $proforma = Proforma::query()->firstOrFail();
        $this->actingAs($this->almacen)
            ->patch(route('proformas.enviar', $proforma));

        return $proforma->fresh();
    }

    private function datosProforma(): array
    {
        return [
            'tipo_origen' => 'VENTA_DIRECTA',
            'cliente_id' => $this->cliente->id,
            'fecha_emision' => now()->toDateString(),
            'fecha_validez' => now()->addDays(10)->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => null,
            'condiciones_pago' => null,
            'condiciones_entrega' => null,
            'observacion' => null,
            'detalles' => [[
                'producto_id' => $this->producto->id,
                'cantidad' => 2,
                'igv_modo' => 'AGREGAR',
                'observacion' => null,
            ]],
        ];
    }

    private function usuarioConRol(string $rol, string $username): User
    {
        return User::query()->create([
            'role_id' => Role::query()->where('codigo', $rol)->firstOrFail()->id,
            'username' => $username,
            'email' => $username . '@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
    }
}
