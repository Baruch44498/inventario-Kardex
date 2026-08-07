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

class Fase1705ProformasPrestamosSinOvTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private User $logistica;
    private Cliente $cliente;
    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = $this->usuarioConRol('ALMACEN', 'almacen_1705');
        $this->logistica = $this->usuarioConRol('COMERCIAL_LOGISTICA', 'logistica_1705');

        foreach (
            [
                'OM' => 'Orden de mantenimiento',
                'OV' => 'Orden de venta',
                'OS' => 'Orden de servicio',
                'OP' => 'Orden de producción',
            ] as $codigo => $nombre
        ) {
            TipoOrden::query()->updateOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'estado' => true]
            );
        }

        $tipo = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            [
                'nombre' => 'Final',
                'porcentaje_ganancia' => 20,
                'estado' => true,
            ]
        );

        $this->cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipo->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20444555666',
            'ruc' => '20444555666',
            'razon_social' => 'Cliente Mixto 1705 SAC',
            'estado' => true,
        ]);

        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $repisa = Repisa::query()->create([
            'codigo' => 'R-1705-01',
            'descripcion' => 'Pruebas 17.0.5',
            'estado' => true,
        ]);
        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'VAL-1705',
            'descripcion' => 'Válvula para venta o préstamo',
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

    public function test_formulario_de_almacen_no_pide_vigencia_ni_muestra_precios_sugeridos(): void
    {
        $this->actingAs($this->almacen)
            ->get(route('proformas.create'))
            ->assertOk()
            ->assertSee('Fecha de emisión')
            ->assertDontSee('Válida hasta')
            ->assertDontSee('precio mostrado es una sugerencia')
            ->assertDontSee('precios sugeridos');

        $this->actingAs($this->almacen)
            ->post(route('proformas.store'), $this->datosProforma([
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 1,
                    'tratamiento' => 'VENTA',
                ],
            ]))
            ->assertRedirect();

        $this->assertNull(Proforma::query()->firstOrFail()->fecha_validez);
    }

    public function test_proforma_mixta_cotiza_solo_las_lineas_de_venta_y_no_crea_ov(): void
    {
        $this->actingAs($this->almacen)
            ->post(route('proformas.store'), $this->datosProforma([
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 2,
                    'tratamiento' => 'VENTA',
                    'observacion' => 'Cobrar estas dos unidades',
                ],
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 1,
                    'tratamiento' => 'PRESTAMO',
                    'observacion' => 'Reponer con una nueva de la misma marca',
                ],
            ]))
            ->assertRedirect();

        $proforma = Proforma::query()->with('detalles')->firstOrFail();
        $this->assertCount(2, $proforma->detalles);
        $this->assertSame(
            ['PRESTAMO', 'VENTA'],
            $proforma->detalles->pluck('tratamiento')->sort()->values()->all()
        );

        $this->actingAs($this->almacen)
            ->patch(route('proformas.enviar', $proforma))
            ->assertRedirect(route('proformas.show', $proforma));

        $this->actingAs($this->logistica)
            ->post(route('proformas.cotizar', $proforma))
            ->assertRedirect();

        $cotizacion = CotizacionCliente::query()->with('detalles')->firstOrFail();
        $this->assertNull($cotizacion->tipo_orden_id);
        $this->assertCount(1, $cotizacion->detalles);
        $this->assertEquals(2, (float) $cotizacion->detalles->first()->cantidad);
        $this->assertSame('COTIZADA', $proforma->fresh()->estado);

        $tipoVenta = TipoOrden::query()->where('codigo', 'OV')->firstOrFail();
        $this->actingAs($this->logistica)
            ->from(route('cotizaciones-cliente.show', $cotizacion))
            ->post(route('cotizaciones-cliente.convertir-orden', $cotizacion), [
                'tipo_orden_id' => $tipoVenta->id,
                'fecha_apertura' => now()->toDateString(),
                'descripcion' => 'No debe existir una OV',
            ])
            ->assertRedirect(route('cotizaciones-cliente.show', $cotizacion))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('ordenes_operacion', 0);
    }

    public function test_reposicion_de_prestamo_se_deriva_a_nota_de_ingreso(): void
    {
        $proforma = $this->crearProformaPrestamo(2);
        $detalle = $proforma->detalles()->firstOrFail();
        $inventario = Inventario::query()
            ->where('producto_id', $this->producto->id)
            ->firstOrFail();

        $this->actingAs($this->almacen)
            ->post(route('notas-salida.store'), [
                'motivo_salida' => 'PROFORMA',
                'proforma_id' => $proforma->id,
                'fecha_salida' => now()->toDateString(),
                'entregado_a' => $this->cliente->razon_social,
                'detalles' => [[
                    'inventario_id' => $inventario->id,
                    'proforma_detalle_id' => $detalle->id,
                    'producto_id' => $this->producto->id,
                    'repisa_id' => $inventario->repisa_id,
                    'tratamiento' => 'PRESTAMO_EXTERNO',
                    'cantidad' => 2,
                ]],
            ])
            ->assertRedirect();

        $this->actingAs($this->almacen)
            ->post(route('proformas.prestamos.reponer', [$proforma, $detalle]), [
                'cantidad' => 1,
            ])
            ->assertRedirect(route('notas-ingreso.create', [
                'motivo_ingreso' => 'REPOSICION_PRESTAMO',
                'proforma_id' => $proforma->id,
            ]));

        $this->assertDatabaseCount('proforma_prestamo_reposiciones', 0);
        $this->assertEquals(8, (float) $inventario->fresh()->stock_actual);
    }

    public function test_reposicion_no_se_habilita_sin_salida_fisica(): void
    {
        $proforma = $this->crearProformaPrestamo(1);
        $detalle = $proforma->detalles()->firstOrFail();

        $this->actingAs($this->almacen)
            ->from(route('proformas.show', $proforma))
            ->post(route('proformas.prestamos.reponer', [$proforma, $detalle]), [
                'cantidad' => 1,
            ])
            ->assertRedirect(route('proformas.show', $proforma))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('proforma_prestamo_reposiciones', 0);
    }

    public function test_proforma_solo_prestamo_se_confirma_sin_cobro_y_sin_cotizacion(): void
    {
        $proforma = $this->crearProformaPrestamo(1);

        $this->actingAs($this->logistica)
            ->post(route('proformas.cotizar', $proforma))
            ->assertStatus(422);

        $this->actingAs($this->logistica)
            ->patch(route('proformas.sin-cobro', $proforma))
            ->assertRedirect(route('proformas.show', $proforma));

        $this->assertSame('SIN_COBRO', $proforma->fresh()->estado);
        $this->assertDatabaseCount('cotizaciones_cliente', 0);
        $this->assertDatabaseCount('ordenes_operacion', 0);
    }

    public function test_proforma_no_permite_cantidades_mayores_al_stock_fisico(): void
    {
        $this->actingAs($this->almacen)
            ->from(route('proformas.create'))
            ->post(route('proformas.store'), $this->datosProforma([
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => 11,
                    'tratamiento' => 'VENTA',
                ],
            ]))
            ->assertRedirect(route('proformas.create'))
            ->assertSessionHasErrors('detalles');

        $this->assertDatabaseCount('proformas', 0);
    }

    public function test_vista_explica_venta_prestamo_y_elimina_ov_del_flujo(): void
    {
        $proforma = $this->crearProformaPrestamo(1);

        $this->actingAs($this->logistica)
            ->get(route('proformas.show', $proforma))
            ->assertOk()
            ->assertSee('PRÉSTAMO')
            ->assertSee('Reposiciones físicas')
            ->assertSee('Confirmar operación sin cobro')
            ->assertDontSee('Orden de venta');
    }

    private function crearProformaPrestamo(
        float $cantidad,
        bool $enviar = true
    ): Proforma {
        $this->actingAs($this->almacen)
            ->post(route('proformas.store'), $this->datosProforma([
                [
                    'producto_id' => $this->producto->id,
                    'cantidad' => $cantidad,
                    'tratamiento' => 'PRESTAMO',
                    'observacion' => 'Reposición con producto nuevo equivalente',
                ],
            ]));

        $proforma = Proforma::query()->firstOrFail();

        if ($enviar) {
            $this->actingAs($this->almacen)
                ->patch(route('proformas.enviar', $proforma));
        }

        return $proforma->fresh();
    }

    private function datosProforma(array $detalles): array
    {
        return [
            'tipo_origen' => 'VENTA_DIRECTA',
            'cliente_id' => $this->cliente->id,
            'fecha_emision' => now()->toDateString(),
            'observacion' => 'Prueba fase 17.0.5',
            'detalles' => $detalles,
        ];
    }

    private function usuarioConRol(string $codigoRol, string $username): User
    {
        return User::query()->create([
            'role_id' => Role::query()->where('codigo', $codigoRol)->firstOrFail()->id,
            'username' => $username,
            'email' => $username . '@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
    }
}
