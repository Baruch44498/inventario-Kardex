<?php

namespace Tests\Feature;

use App\Models\Cotizacion;
use App\Models\Inventario;
use App\Models\NotaIngreso;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Repisa;
use App\Models\Requisicion;
use App\Models\Role;
use App\Models\SolicitudCompra;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1924UnidadesYRecepcionPresentacionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogo_activo_usa_las_unidades_reales_del_kardex(): void
    {
        $this->assertSame(
            ['BAL', 'GLN', 'KIT', 'LT', 'MTS', 'UND'],
            UnidadMedida::query()
                ->where('estado', true)
                ->orderBy('codigo')
                ->pluck('codigo')
                ->all()
        );
    }

    public function test_almacen_recibe_una_bolsa_y_kardex_registra_treinta_unidades(): void
    {
        [$almacen, $orden, $detalle, $repisa] = $this->escenarioBolsa();

        $this->actingAs($almacen)
            ->get(route('notas-ingreso.create', [
                'motivo_ingreso' => 'COMPRA',
                'orden_compra_id' => $orden->id,
            ]))
            ->assertOk()
            ->assertSee('Bolsa x 30')
            ->assertSee('UND sueltas');

        $this->actingAs($almacen)
            ->post(route('notas-ingreso.store'), [
                'motivo_ingreso' => 'COMPRA',
                'orden_compra_id' => $orden->id,
                'fecha_ingreso' => now()->toDateString(),
                'detalles' => [[
                    'orden_compra_detalle_id' => $detalle->id,
                    'producto_id' => $detalle->producto_id,
                    'repisa_id' => $repisa->id,
                    'unidad_recepcion' => 'PRESENTACION',
                    'cantidad_recepcion' => 1,
                ]],
            ])
            ->assertRedirect();

        $notaDetalle = NotaIngreso::query()->with('detalles')->firstOrFail()->detalles->firstOrFail();

        $this->assertSame(30.0, (float) $notaDetalle->cantidad);
        $this->assertSame('Bolsa x 30', $notaDetalle->presentacion_nombre);
        $this->assertSame(1.0, (float) $notaDetalle->cantidad_presentacion);
        $this->assertSame(30.0, (float) $notaDetalle->factor_conversion);
        $this->assertSame(30.0, (float) Inventario::query()->firstOrFail()->stock_actual);
        $this->assertSame(30.0, (float) $detalle->fresh()->cantidad_recibida);
        $this->assertSame('RECIBIDA', $orden->fresh()->estado);
    }

    public function test_servidor_no_confia_en_un_factor_enviado_por_el_navegador(): void
    {
        [$almacen, $orden, $detalle, $repisa] = $this->escenarioBolsa();

        $this->actingAs($almacen)
            ->post(route('notas-ingreso.store'), [
                'motivo_ingreso' => 'COMPRA',
                'orden_compra_id' => $orden->id,
                'fecha_ingreso' => now()->toDateString(),
                'detalles' => [[
                    'orden_compra_detalle_id' => $detalle->id,
                    'producto_id' => $detalle->producto_id,
                    'repisa_id' => $repisa->id,
                    'unidad_recepcion' => 'PRESENTACION',
                    'cantidad_recepcion' => 2,
                    'factor_conversion' => 1,
                ]],
            ])
            ->assertSessionHasErrors('detalles.0.cantidad');

        $this->assertDatabaseCount('notas_ingreso', 0);
        $this->assertDatabaseCount('inventarios', 0);
    }

    /** @return array{User, OrdenCompra, mixed, Repisa} */
    private function escenarioBolsa(): array
    {
        $almacen = $this->usuario('ALMACEN', 'almacen_1924');
        $logistica = $this->usuario('COMERCIAL_LOGISTICA', 'logistica_1924');
        $unidad = UnidadMedida::query()->where('codigo', 'UND')->firstOrFail();
        $producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'BOL-1924',
            'descripcion' => 'Producto recibido en bolsas',
            'permite_fraccionamiento' => false,
            'estado' => true,
        ]);
        $proveedor = Proveedor::query()->create([
            'ruc' => '20619240001',
            'razon_social' => 'Proveedor Bolsa 1924 SAC',
            'estado' => true,
        ]);
        $repisa = Repisa::query()->create([
            'codigo' => 'BOL-24',
            'descripcion' => 'Recepción de bolsas',
            'estado' => true,
        ]);
        $requisicion = Requisicion::query()->create([
            'codigo' => 'REQ-1924',
            'fecha_solicitud' => now()->toDateString(),
            'origen' => 'REPOSICION',
            'prioridad' => 'MEDIA',
            'estado' => 'COTIZANDO',
            'solicitado_por' => $almacen->id,
        ]);
        $requisicionDetalle = $requisicion->detalles()->create([
            'producto_id' => $producto->id,
            'cantidad_solicitada' => 30,
            'cantidad_sugerida' => 30,
            'cantidad_atendida' => 0,
        ]);
        $cotizacion = Cotizacion::query()->create([
            'requisicion_id' => $requisicion->id,
            'proveedor_id' => $proveedor->id,
            'codigo' => 'CP-1924',
            'fecha_cotizacion' => now()->toDateString(),
            'moneda' => 'PEN',
            'descuento_global_modo' => 'SIN_DESCUENTO',
            'descuento_global_monto' => 0,
            'subtotal' => 30,
            'impuesto' => 5.4,
            'total' => 35.4,
            'total_calculado' => 35.4,
            'ajuste_redondeo' => 0,
            'estado' => 'SELECCIONADA',
            'registrado_por' => $logistica->id,
        ]);
        $cotizacionDetalle = $cotizacion->detalles()->create([
            'requisicion_detalle_id' => $requisicionDetalle->id,
            'tipo_vinculacion' => 'SOLICITADO',
            'vinculacion_origen' => 'MANUAL',
            'producto_id' => $producto->id,
            'presentacion_nombre' => 'Bolsa x 30',
            'cantidad_presentacion' => 1,
            'factor_conversion' => 30,
            'precio_presentacion' => 35.4,
            'cantidad' => 30,
            'precio_unitario' => 1.18,
            'descuento_porcentaje' => 0,
            'descuento_modo' => 'SIN_DESCUENTO',
            'igv_modo' => 'INCLUIDO',
            'igv_porcentaje' => 18,
            'subtotal' => 30,
            'impuesto' => 5.4,
            'total' => 35.4,
        ]);
        $solicitud = SolicitudCompra::query()->create([
            'cotizacion_id' => $cotizacion->id,
            'codigo' => 'SC-1924',
            'fecha_solicitud' => now()->toDateString(),
            'total_lineas' => 35.4,
            'ajuste_redondeo' => 0,
            'total_seleccionado' => 35.4,
            'estado' => 'CONVERTIDA',
            'solicitado_por' => $logistica->id,
            'aprobado_por' => $logistica->id,
            'aprobado_en' => now(),
        ]);
        $solicitudDetalle = $solicitud->detalles()->create([
            'cotizacion_detalle_id' => $cotizacionDetalle->id,
            'producto_id' => $producto->id,
            'cantidad' => 30,
            'precio_unitario' => 1.18,
            'descuento_porcentaje' => 0,
            'subtotal' => 35.4,
        ]);
        $orden = OrdenCompra::query()->create([
            'solicitud_compra_id' => $solicitud->id,
            'proveedor_id' => $proveedor->id,
            'codigo' => 'OC-1924',
            'fecha_emision' => now()->toDateString(),
            'moneda' => 'PEN',
            'subtotal' => 30,
            'impuesto' => 5.4,
            'ajuste_redondeo' => 0,
            'total' => 35.4,
            'estado' => 'APROBADA',
            'emitido_por' => $logistica->id,
            'aprobado_por' => $logistica->id,
            'aprobado_en' => now(),
        ]);
        $detalle = $orden->detalles()->create([
            'solicitud_compra_detalle_id' => $solicitudDetalle->id,
            'producto_id' => $producto->id,
            'cantidad_ordenada' => 30,
            'cantidad_recibida' => 0,
            'precio_unitario' => 1.18,
            'descuento_porcentaje' => 0,
            'subtotal' => 35.4,
        ]);

        return [$almacen, $orden, $detalle, $repisa];
    }

    private function usuario(string $rol, string $username): User
    {
        return User::query()->create([
            'role_id' => Role::query()->where('codigo', $rol)->firstOrFail()->id,
            'username' => $username,
            'email' => "{$username}@example.com",
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
    }
}
