<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\NotaIngreso;
use App\Models\NotaSalida;
use App\Models\OrdenOperacion;
use App\Models\Producto;
use App\Models\Proforma;
use App\Models\ProformaDetalle;
use App\Models\Repisa;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1706NotasFisicasRetornosTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private Cliente $cliente;
    private Producto $producto;
    private Repisa $repisa;
    private Inventario $inventario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = $this->usuarioConRol('ALMACEN', 'almacen_1706');

        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );

        $this->cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20617060001',
            'ruc' => '20617060001',
            'razon_social' => 'Cliente Fase 1706 SAC',
            'estado' => true,
        ]);

        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );

        $this->repisa = Repisa::query()->create([
            'codigo' => 'R-1706-01',
            'descripcion' => 'Pruebas notas físicas 17.0.6',
            'estado' => true,
        ]);

        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'PRD-1706',
            'descripcion' => 'Producto control físico 17.0.6',
            'estado' => true,
        ]);

        $this->inventario = Inventario::query()->create([
            'producto_id' => $this->producto->id,
            'repisa_id' => $this->repisa->id,
            'stock_actual' => 10,
            'stock_minimo' => 2,
            'stock_maximo' => 20,
            'costo_promedio_soles' => 100,
        ]);
    }

    public function test_prestamo_de_proforma_sale_fisicamente_mediante_nota_de_salida(): void
    {
        $proforma = $this->crearProforma([
            ['producto_id' => $this->producto->id, 'cantidad' => 2, 'tratamiento' => 'PRESTAMO'],
        ]);
        $linea = $proforma->detalles()->firstOrFail();

        $this->actingAs($this->almacen)
            ->post(route('notas-salida.store'), $this->datosSalidaProforma($proforma, [
                [
                    'inventario_id' => $this->inventario->id,
                    'proforma_detalle_id' => $linea->id,
                    'producto_id' => $this->producto->id,
                    'repisa_id' => $this->repisa->id,
                    'tratamiento' => 'PRESTAMO_EXTERNO',
                    'cantidad' => 2,
                ],
            ]))
            ->assertRedirect();

        $this->assertEquals(8, (float) $this->inventario->fresh()->stock_actual);
        $this->assertDatabaseHas('notas_salida', [
            'proforma_id' => $proforma->id,
            'motivo_salida' => 'PROFORMA',
            'estado' => 'CONFIRMADA',
        ]);
        $this->assertDatabaseHas('movimientos_inventario', [
            'producto_id' => $this->producto->id,
            'tipo_movimiento' => 'SALIDA',
            'motivo' => 'PRESTAMO_EXTERNO',
        ]);
        $this->assertEquals(2, $linea->fresh()->cantidadPrestadaFisicamente());
    }

    public function test_reposicion_de_prestamo_ingresa_stock_y_conserva_trazabilidad(): void
    {
        $proforma = $this->crearProforma([
            ['producto_id' => $this->producto->id, 'cantidad' => 2, 'tratamiento' => 'PRESTAMO'],
        ]);
        $linea = $proforma->detalles()->firstOrFail();
        $this->crearSalidaProforma($proforma, $linea, 2, 'PRESTAMO_EXTERNO');

        $this->actingAs($this->almacen)
            ->post(route('notas-ingreso.store'), [
                'motivo_ingreso' => 'REPOSICION_PRESTAMO',
                'proforma_id' => $proforma->id,
                'fecha_ingreso' => now()->toDateString(),
                'observacion' => 'Reposición equivalente nueva',
                'detalles' => [[
                    'proforma_detalle_id' => $linea->id,
                    'producto_id' => $this->producto->id,
                    'repisa_id' => $this->repisa->id,
                    'cantidad' => 1,
                    'observacion' => 'Primera unidad repuesta',
                ]],
            ])
            ->assertRedirect();

        $this->assertEquals(9, (float) $this->inventario->fresh()->stock_actual);
        $this->assertDatabaseHas('notas_ingreso', [
            'proforma_id' => $proforma->id,
            'motivo_ingreso' => 'REPOSICION_PRESTAMO',
            'estado' => 'CONFIRMADA',
        ]);
        $this->assertDatabaseHas('proforma_prestamo_reposiciones', [
            'proforma_detalle_id' => $linea->id,
            'cantidad' => 1,
        ]);
        $this->assertDatabaseHas('movimientos_inventario', [
            'producto_id' => $this->producto->id,
            'tipo_movimiento' => 'ENTRADA',
            'motivo' => 'REPOSICION_PRESTAMO',
        ]);
        $this->assertEquals(1, $linea->fresh()->cantidadPendienteReposicion());
    }

    public function test_herramienta_sale_para_orden_y_vuelve_por_nota_de_ingreso(): void
    {
        $orden = $this->crearOrden('OP');

        $this->actingAs($this->almacen)
            ->post(route('notas-salida.store'), [
                'motivo_salida' => 'ORDEN_OPERACION',
                'orden_operacion_id' => $orden->id,
                'fecha_salida' => now()->toDateString(),
                'entregado_a' => 'Responsable de producción',
                'detalles' => [[
                    'inventario_id' => $this->inventario->id,
                    'producto_id' => $this->producto->id,
                    'repisa_id' => $this->repisa->id,
                    'tratamiento' => 'USO_TEMPORAL',
                    'cantidad' => 1,
                ]],
            ])
            ->assertRedirect();

        $salida = NotaSalida::query()->with('detalles')->firstOrFail();
        $salidaDetalle = $salida->detalles->first();
        $this->assertEquals(9, (float) $this->inventario->fresh()->stock_actual);
        $this->assertSame('EN_PROCESO', $orden->fresh()->estado);

        $this->actingAs($this->almacen)
            ->post(route('notas-ingreso.store'), [
                'motivo_ingreso' => 'DEVOLUCION_HERRAMIENTA',
                'nota_salida_id' => $salida->id,
                'fecha_ingreso' => now()->toDateString(),
                'detalles' => [[
                    'nota_salida_detalle_id' => $salidaDetalle->id,
                    'producto_id' => $this->producto->id,
                    'repisa_id' => $this->repisa->id,
                    'cantidad' => 1,
                ]],
            ])
            ->assertRedirect();

        $this->assertEquals(10, (float) $this->inventario->fresh()->stock_actual);
        $this->assertDatabaseHas('nota_ingreso_detalles', [
            'nota_salida_detalle_id' => $salidaDetalle->id,
            'cantidad' => 1,
        ]);
        $this->assertDatabaseHas('movimientos_inventario', [
            'tipo_movimiento' => 'SALIDA',
            'motivo' => 'HERRAMIENTA_EN_USO',
        ]);
        $this->assertDatabaseHas('movimientos_inventario', [
            'tipo_movimiento' => 'ENTRADA',
            'motivo' => 'DEVOLUCION_HERRAMIENTA',
        ]);
    }

    public function test_material_no_utilizado_puede_retornar_parcialmente_a_la_orden(): void
    {
        $orden = $this->crearOrden('OM');

        $this->actingAs($this->almacen)
            ->post(route('notas-salida.store'), [
                'motivo_salida' => 'ORDEN_OPERACION',
                'orden_operacion_id' => $orden->id,
                'fecha_salida' => now()->toDateString(),
                'entregado_a' => 'Técnico de mantenimiento',
                'detalles' => [[
                    'inventario_id' => $this->inventario->id,
                    'producto_id' => $this->producto->id,
                    'repisa_id' => $this->repisa->id,
                    'tratamiento' => 'CONSUMO',
                    'cantidad' => 3,
                ]],
            ]);

        $salida = NotaSalida::query()->with('detalles')->firstOrFail();
        $salidaDetalle = $salida->detalles->first();

        $this->actingAs($this->almacen)
            ->post(route('notas-ingreso.store'), [
                'motivo_ingreso' => 'RETORNO_MATERIAL',
                'nota_salida_id' => $salida->id,
                'fecha_ingreso' => now()->toDateString(),
                'detalles' => [[
                    'nota_salida_detalle_id' => $salidaDetalle->id,
                    'producto_id' => $this->producto->id,
                    'repisa_id' => $this->repisa->id,
                    'cantidad' => 1,
                ]],
            ])
            ->assertRedirect();

        $this->assertEquals(8, (float) $this->inventario->fresh()->stock_actual);
        $this->assertDatabaseHas('movimientos_inventario', [
            'tipo_movimiento' => 'ENTRADA',
            'motivo' => 'RETORNO_MATERIAL',
            'cantidad' => 1,
        ]);
    }

    public function test_no_permite_devolver_mas_de_lo_que_queda_pendiente(): void
    {
        $orden = $this->crearOrden('OS');

        $this->actingAs($this->almacen)->post(route('notas-salida.store'), [
            'motivo_salida' => 'ORDEN_OPERACION',
            'orden_operacion_id' => $orden->id,
            'fecha_salida' => now()->toDateString(),
            'entregado_a' => 'Técnico de servicio',
            'detalles' => [[
                'inventario_id' => $this->inventario->id,
                'producto_id' => $this->producto->id,
                'repisa_id' => $this->repisa->id,
                'tratamiento' => 'USO_TEMPORAL',
                'cantidad' => 1,
            ]],
        ]);

        $salida = NotaSalida::query()->with('detalles')->firstOrFail();
        $salidaDetalle = $salida->detalles->first();

        $this->actingAs($this->almacen)
            ->from(route('notas-ingreso.create', [
                'motivo_ingreso' => 'DEVOLUCION_HERRAMIENTA',
                'nota_salida_id' => $salida->id,
            ]))
            ->post(route('notas-ingreso.store'), [
                'motivo_ingreso' => 'DEVOLUCION_HERRAMIENTA',
                'nota_salida_id' => $salida->id,
                'fecha_ingreso' => now()->toDateString(),
                'detalles' => [[
                    'nota_salida_detalle_id' => $salidaDetalle->id,
                    'producto_id' => $this->producto->id,
                    'repisa_id' => $this->repisa->id,
                    'cantidad' => 2,
                ]],
            ])
            ->assertSessionHasErrors('detalles');

        $this->assertDatabaseCount('notas_ingreso', 0);
        $this->assertEquals(9, (float) $this->inventario->fresh()->stock_actual);
    }

    public function test_proforma_mixta_puede_despachar_venta_y_prestamo_sin_crear_ov(): void
    {
        $proforma = $this->crearProforma([
            ['producto_id' => $this->producto->id, 'cantidad' => 2, 'tratamiento' => 'VENTA'],
            ['producto_id' => $this->producto->id, 'cantidad' => 1, 'tratamiento' => 'PRESTAMO'],
        ]);
        $venta = $proforma->detalles()->where('tratamiento', 'VENTA')->firstOrFail();
        $prestamo = $proforma->detalles()->where('tratamiento', 'PRESTAMO')->firstOrFail();

        $this->actingAs($this->almacen)
            ->post(route('notas-salida.store'), $this->datosSalidaProforma($proforma, [
                [
                    'inventario_id' => $this->inventario->id,
                    'proforma_detalle_id' => $venta->id,
                    'producto_id' => $this->producto->id,
                    'repisa_id' => $this->repisa->id,
                    'tratamiento' => 'VENTA_DIRECTA',
                    'cantidad' => 2,
                ],
                [
                    'inventario_id' => $this->inventario->id,
                    'proforma_detalle_id' => $prestamo->id,
                    'producto_id' => $this->producto->id,
                    'repisa_id' => $this->repisa->id,
                    'tratamiento' => 'PRESTAMO_EXTERNO',
                    'cantidad' => 1,
                ],
            ]))
            ->assertRedirect();

        $this->assertEquals(7, (float) $this->inventario->fresh()->stock_actual);
        $this->assertSame(
            ['PRESTAMO_EXTERNO', 'VENTA_DIRECTA'],
            MovimientoInventario::query()->pluck('motivo')->sort()->values()->all()
        );
        $this->assertDatabaseCount('ordenes_operacion', 0);
    }

    public function test_vistas_explican_entrada_salida_y_mensaje_de_producto_sin_stock(): void
    {
        $this->actingAs($this->almacen)
            ->get(route('notas-salida.create', ['motivo_salida' => 'USO_INTERNO']))
            ->assertOk()
            ->assertSee('Uso temporal')
            ->assertSee('realmente deja Almacén')
            ->assertSee('no quedarán unidades disponibles de esta herramienta en Almacén');

        $this->actingAs($this->almacen)
            ->get(route('notas-ingreso.create'))
            ->assertOk()
            ->assertSee('Devolución de herramienta')
            ->assertSee('Reposición de préstamo');

        $this->actingAs($this->almacen)
            ->get(route('proformas.create'))
            ->assertOk()
            ->assertSee('Producto no encontrado o sin stock físico disponible en Almacén.');
    }

    private function crearProforma(array $detalles): Proforma
    {
        $this->actingAs($this->almacen)
            ->post(route('proformas.store'), [
                'tipo_origen' => 'VENTA_DIRECTA',
                'cliente_id' => $this->cliente->id,
                'fecha_emision' => now()->toDateString(),
                'observacion' => 'Prueba de movimiento físico 17.0.6',
                'detalles' => $detalles,
            ])
            ->assertRedirect();

        return Proforma::query()->latest('id')->with('detalles')->firstOrFail();
    }

    private function crearSalidaProforma(
        Proforma $proforma,
        ProformaDetalle $linea,
        float $cantidad,
        string $tratamiento
    ): NotaSalida {
        $this->actingAs($this->almacen)
            ->post(route('notas-salida.store'), $this->datosSalidaProforma($proforma, [[
                'inventario_id' => $this->inventario->id,
                'proforma_detalle_id' => $linea->id,
                'producto_id' => $this->producto->id,
                'repisa_id' => $this->repisa->id,
                'tratamiento' => $tratamiento,
                'cantidad' => $cantidad,
            ]]))
            ->assertRedirect();

        return NotaSalida::query()->latest('id')->firstOrFail();
    }

    private function datosSalidaProforma(Proforma $proforma, array $detalles): array
    {
        return [
            'motivo_salida' => 'PROFORMA',
            'proforma_id' => $proforma->id,
            'fecha_salida' => now()->toDateString(),
            'entregado_a' => $this->cliente->razon_social,
            'detalles' => $detalles,
        ];
    }

    private function crearOrden(string $tipoCodigo): OrdenOperacion
    {
        $tipo = TipoOrden::query()->updateOrCreate(
            ['codigo' => $tipoCodigo],
            ['nombre' => "Orden {$tipoCodigo}", 'estado' => true]
        );

        return OrdenOperacion::query()->create([
            'tipo_orden_id' => $tipo->id,
            'cliente_id' => $this->cliente->id,
            'codigo_orden' => $tipoCodigo . '-1706-' . str_pad((string) (OrdenOperacion::query()->count() + 1), 5, '0', STR_PAD_LEFT),
            'numero_correlativo' => OrdenOperacion::query()->count() + 1,
            'anio' => (int) now()->format('Y'),
            'fecha_apertura' => now()->toDateString(),
            'descripcion' => 'Orden para prueba de herramientas y retornos',
            'estado' => 'EN_PROCESO',
            'creado_por' => $this->almacen->id,
        ]);
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
