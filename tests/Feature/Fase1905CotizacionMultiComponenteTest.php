<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\CotizacionComponente;
use App\Models\OrdenOperacion;
use App\Models\Producto;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Ordenes\ResumenEjecucionOrdenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1905CotizacionMultiComponenteTest extends TestCase
{
    use RefreshDatabase;

    private User $logistica;
    private User $almacen;
    private Cliente $cliente;
    private Vehiculo $vehiculo;
    private Producto $productoUno;
    private Producto $productoDos;
    private TipoOrden $tipoOm;
    private TipoOrden $tipoOs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistica = $this->usuario('COMERCIAL_LOGISTICA', 'logistica_1905');
        $this->almacen = $this->usuario('ALMACEN', 'almacen_1905');
        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );
        $this->cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20619050001',
            'ruc' => '20619050001',
            'razon_social' => 'Cliente Multi Componente SAC',
            'estado' => true,
        ]);
        $this->vehiculo = Vehiculo::query()->create([
            'cliente_id' => $this->cliente->id,
            'placa' => 'MUL-195',
            'marca' => 'Volvo',
            'modelo' => 'Prueba',
            'estado' => true,
        ]);
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $this->productoUno = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'MC-1905-1',
            'descripcion' => 'Repuesto de mantenimiento',
            'estado' => true,
        ]);
        $this->productoDos = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'MC-1905-2',
            'descripcion' => 'Material de servicio',
            'estado' => true,
        ]);
        $this->tipoOm = TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OM'],
            ['nombre' => 'Mantenimiento', 'estado' => true]
        );
        $this->tipoOs = TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OS'],
            ['nombre' => 'Servicio', 'estado' => true]
        );
    }

    public function test_una_cotizacion_genera_una_orden_por_componente(): void
    {
        $cotizacion = $this->crearCotizacion();
        $principal = $cotizacion->componentes()->firstOrFail();
        $servicio = $this->agregarServicio($cotizacion);
        $this->assertNotSame($principal->id, $servicio->id);
        $this->assertSame(2, $servicio->orden_secuencia);

        $this->actingAs($this->logistica)
            ->put(route('cotizaciones-cliente.update', $cotizacion), $this->datosCotizacion([
                'detalles' => [
                    $this->linea($this->productoUno, 1, 100),
                    $this->linea($this->productoDos, 2, 200),
                ],
            ]))
            ->assertRedirect();

        $detalles = $cotizacion->detalles()->get()->keyBy('producto_id');
        $this->actingAs($this->logistica)
            ->put(route('cotizaciones-cliente.componentes.asignar', $cotizacion), [
                'detalles' => [
                    [
                        'detalle_id' => $detalles[$this->productoUno->id]->id,
                        'componente_id' => $principal->id,
                    ],
                    [
                        'detalle_id' => $detalles[$this->productoDos->id]->id,
                        'componente_id' => $servicio->id,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cotizacion_cliente_detalles', [
            'id' => $detalles[$this->productoUno->id]->id,
            'componente_id' => $principal->id,
        ]);
        $this->assertDatabaseHas('cotizacion_cliente_detalles', [
            'id' => $detalles[$this->productoDos->id]->id,
            'componente_id' => $servicio->id,
        ]);

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.convertir-orden', $cotizacion), [
                'fecha_apertura' => now()->toDateString(),
            ])
            ->assertRedirect(route('cotizaciones-cliente.show', $cotizacion));

        $cotizacion->refresh();
        $this->assertSame('CONVERTIDA_EN_ORDEN', $cotizacion->estado);
        $this->assertSame(2, $cotizacion->ordenesOperacion()->count());
        $this->assertDatabaseHas('ordenes_operacion', [
            'cotizacion_cliente_id' => $cotizacion->id,
            'tipo_orden_id' => $this->tipoOm->id,
            'vehiculo_id' => $this->vehiculo->id,
        ]);
        $this->assertDatabaseHas('ordenes_operacion', [
            'cotizacion_cliente_id' => $cotizacion->id,
            'tipo_orden_id' => $this->tipoOs->id,
            'vehiculo_id' => null,
        ]);
        $this->assertSame(2, CotizacionComponente::query()
            ->where('cotizacion_cliente_id', $cotizacion->id)
            ->whereNotNull('orden_operacion_id')
            ->count());

        $ordenOm = OrdenOperacion::query()->where('tipo_orden_id', $this->tipoOm->id)->firstOrFail();
        $ordenOs = OrdenOperacion::query()->where('tipo_orden_id', $this->tipoOs->id)->firstOrFail();
        $this->assertDatabaseHas('materiales_requeridos_orden', [
            'orden_operacion_id' => $ordenOm->id,
            'producto_id' => $this->productoUno->id,
        ]);
        $this->assertDatabaseHas('materiales_requeridos_orden', [
            'orden_operacion_id' => $ordenOs->id,
            'producto_id' => $this->productoDos->id,
        ]);

        $resumenOm = app(ResumenEjecucionOrdenService::class)->construir($ordenOm, true);
        $resumenOs = app(ResumenEjecucionOrdenService::class)->construir($ordenOs, true);
        $this->assertSame(100.0, (float) $resumenOm['costos']['rentabilidad']['ingreso_neto_soles']);
        $this->assertSame(400.0, (float) $resumenOs['costos']['rentabilidad']['ingreso_neto_soles']);

        $this->actingAs($this->logistica)
            ->get(route('ordenes-operacion.index'))
            ->assertOk()
            ->assertSee('1 producto planificado')
            ->assertDontSee('2 productos planificados');
    }

    public function test_no_cierra_si_un_componente_no_tiene_producto_cotizado(): void
    {
        $cotizacion = $this->crearCotizacion();
        $this->agregarServicio($cotizacion);

        $this->actingAs($this->logistica)
            ->patch(route('cotizaciones-cliente.cerrar', $cotizacion))
            ->assertRedirect()
            ->assertSessionHas('error', function (string $mensaje): bool {
                return str_contains($mensaje, 'Cada componente debe tener al menos una línea comercial');
            });

        $this->assertSame('ABIERTA', $cotizacion->fresh()->estado);
    }

    public function test_no_convierte_una_cotizacion_cerrada_con_componente_vacio(): void
    {
        $cotizacion = $this->crearCotizacion();
        $this->agregarServicio($cotizacion);
        $cotizacion->update(['estado' => 'CERRADA']);

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.convertir-orden', $cotizacion), [
                'fecha_apertura' => now()->toDateString(),
            ])
            ->assertUnprocessable();

        $this->assertSame(0, OrdenOperacion::query()->count());
    }

    public function test_presupuesto_se_registra_en_el_componente_elegido(): void
    {
        $cotizacion = $this->crearCotizacion();
        $servicio = $this->agregarServicio($cotizacion);

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.presupuesto.store', $cotizacion), [
                'componente_id' => $servicio->id,
                'tipo_costo' => 'SERVICIO_TERCERO',
                'descripcion' => 'Ensayo del componente de servicio',
                'cantidad' => 1,
                'unidad' => 'SERVICIO',
                'moneda' => 'PEN',
                'tipo_cambio' => 3.8,
                'costo_unitario' => 250,
                'carga_social_porcentaje' => 0,
                'igv_modo' => 'AGREGAR',
                'igv_porcentaje' => 18,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cotizacion_presupuestos', [
            'cotizacion_cliente_id' => $cotizacion->id,
            'componente_id' => $servicio->id,
            'costo_neto_soles' => 250,
        ]);
    }

    public function test_no_elimina_componente_con_lineas_asignadas(): void
    {
        $cotizacion = $this->crearCotizacion();
        $principal = $cotizacion->componentes()->firstOrFail();
        $this->agregarServicio($cotizacion);

        $this->actingAs($this->logistica)
            ->delete(route('cotizacion-componentes.destroy', $principal))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('cotizacion_componentes', ['id' => $principal->id]);
    }

    public function test_nueva_version_clona_componentes_y_reasigna_sus_lineas(): void
    {
        $cotizacion = $this->crearCotizacion();
        $principal = $cotizacion->componentes()->firstOrFail();
        $servicio = $this->agregarServicio($cotizacion);
        $this->assertNotSame($principal->id, $servicio->id);
        $this->assertSame(2, $servicio->orden_secuencia);

        $this->actingAs($this->logistica)
            ->put(route('cotizaciones-cliente.update', $cotizacion), $this->datosCotizacion([
                'detalles' => [
                    $this->linea($this->productoUno, 1, 100),
                    $this->linea($this->productoDos, 2, 200),
                ],
            ]))
            ->assertRedirect();

        $detalles = $cotizacion->detalles()->get()->keyBy('producto_id');
        $this->actingAs($this->logistica)
            ->put(route('cotizaciones-cliente.componentes.asignar', $cotizacion), [
                'detalles' => [
                    [
                        'detalle_id' => $detalles[$this->productoUno->id]->id,
                        'componente_id' => $principal->id,
                    ],
                    [
                        'detalle_id' => $detalles[$this->productoDos->id]->id,
                        'componente_id' => $servicio->id,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cotizacion_cliente_detalles', [
            'id' => $detalles[$this->productoUno->id]->id,
            'componente_id' => $principal->id,
        ]);
        $this->assertDatabaseHas('cotizacion_cliente_detalles', [
            'id' => $detalles[$this->productoDos->id]->id,
            'componente_id' => $servicio->id,
        ]);

        $cotizacion->update(['estado' => 'CERRADA']);
        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.version', $cotizacion))
            ->assertRedirect();

        $nueva = CotizacionCliente::query()->where('version', 2)->firstOrFail();
        $this->assertSame(2, $nueva->componentes()->count());
        $this->assertSame(2, $nueva->detalles()->whereNotNull('componente_id')->count());
        $this->assertSame(2, $nueva->detalles()->distinct()->count('componente_id'));
        $this->assertFalse(
            $nueva->detalles()->pluck('componente_id')->contains($principal->id)
        );
        $this->assertFalse(
            $nueva->detalles()->pluck('componente_id')->contains($servicio->id)
        );
    }

    public function test_almacen_no_accede_a_la_estructura_interna(): void
    {
        $cotizacion = $this->crearCotizacion();

        $this->actingAs($this->almacen)
            ->get(route('cotizaciones-cliente.componentes.show', $cotizacion))
            ->assertForbidden();
    }

    private function crearCotizacion(): CotizacionCliente
    {
        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.store'), $this->datosCotizacion())
            ->assertRedirect();

        return CotizacionCliente::query()->with('componentes')->firstOrFail();
    }

    private function agregarServicio(CotizacionCliente $cotizacion): CotizacionComponente
    {
        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.componentes.store', $cotizacion), [
                'tipo_orden_id' => $this->tipoOs->id,
                'descripcion_componente' => 'Servicio de diagnóstico hidráulico',
                'cliente_direccion_id' => null,
                'vehiculo_id' => null,
                'tipo_cambio_comparacion' => 3.8,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        return $cotizacion->componentes()
            ->reorder()
            ->orderByDesc('orden_secuencia')
            ->firstOrFail();
    }

    private function datosCotizacion(array $cambios = []): array
    {
        return array_replace([
            'cliente_id' => $this->cliente->id,
            'tipo_orden_id' => $this->tipoOm->id,
            'cliente_direccion_id' => null,
            'vehiculo_id' => $this->vehiculo->id,
            'descripcion_trabajo' => 'Mantenimiento preventivo principal',
            'fecha_emision' => now()->toDateString(),
            'fecha_validez' => now()->addDays(15)->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => null,
            'condiciones_pago' => 'Contado',
            'condiciones_entrega' => 'Entrega en HIDROIL',
            'observacion' => null,
            'detalles' => [$this->linea($this->productoUno, 1, 100)],
        ], $cambios);
    }

    private function linea(Producto $producto, float $cantidad, float $precio): array
    {
        return [
            'producto_id' => $producto->id,
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'igv_modo' => 'NO_APLICA',
        ];
    }

    private function usuario(string $rol, string $username): User
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
