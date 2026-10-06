<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\Producto;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\Ventas\ExportarCotizacionClienteExcelService;
use App\Services\Ventas\ExportarCotizacionCosteoExcelService;
use App\Services\Ventas\PresupuestoCotizacionService;
use App\Services\Ventas\SincronizarHojaCostosCotizacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase243PrecioPactadoYExcelClienteTest extends TestCase
{
    use RefreshDatabase;

    public function test_descuento_y_recargo_se_distribuyen_sin_cambiar_costos_ni_margenes_originales(): void
    {
        [$cotizacion, $usuario] = $this->prepararCotizacion();
        $this->actingAs($usuario);
        $sincronizador = app(SincronizarHojaCostosCotizacionService::class);
        $sincronizador->sincronizar($cotizacion);
        $original = $cotizacion->fresh();
        $partidas = $original->presupuestos()->orderBy('id')->get();
        $margenes = $partidas->pluck('margen_porcentaje')->all();
        $costos = $partidas->pluck('costo_neto_soles')->all();

        foreach ([round((float) $original->total - 100, 2), 7000.00] as $precio) {
            $this->patch(route('cotizaciones-cliente.precio-final', $cotizacion), [
                'precio_final_pactado' => $precio,
            ])->assertRedirect(route('cotizaciones-cliente.show', $cotizacion))
                ->assertSessionHasNoErrors();
            $actual = $cotizacion->fresh();
            $this->assertEqualsWithDelta($precio, (float) $actual->total, 0.0001);
            $this->assertEqualsWithDelta($precio, (float) $actual->detalles()->sum('total'), 0.0001);
            $this->assertEqualsWithDelta((float) $actual->total,
                (float) $actual->subtotal + (float) $actual->impuesto, 0.0001);
            $this->assertCount(3, $actual->detalles);
            $this->assertSame($margenes, $actual->presupuestos()->orderBy('id')->pluck('margen_porcentaje')->all());
            $this->assertSame($costos, $actual->presupuestos()->orderBy('id')->pluck('costo_neto_soles')->all());
            $sincronizador->sincronizar($actual);
            $this->assertEqualsWithDelta($precio, (float) $cotizacion->fresh()->total, 0.0001);
        }

        $this->patch(route('cotizaciones-cliente.precio-final', $cotizacion), [
            'precio_final_pactado' => '',
        ])->assertSessionHasNoErrors();
        $this->assertNull($cotizacion->fresh()->precio_final_pactado);
        $this->assertEqualsWithDelta((float) $original->total, (float) $cotizacion->fresh()->total, 0.0001);
    }

    public function test_excel_cliente_precio_unico_oculta_costos_y_detalle_y_el_interno_conserva_comparativo(): void
    {
        [$cotizacion, $usuario] = $this->prepararCotizacion();
        $this->actingAs($usuario);
        app(SincronizarHojaCostosCotizacionService::class)->sincronizar($cotizacion);
        $this->patch(route('cotizaciones-cliente.precio-final', $cotizacion), [
            'precio_final_pactado' => '4850.00',
        ])->assertSessionHasNoErrors();
        $actual = $cotizacion->fresh();

        $exportador = app(ExportarCotizacionClienteExcelService::class);
        $unico = $exportador->libro($actual, 'precio-unico');
        $this->assertSame('Fabricación hidráulica', $unico->getActiveSheet()->getCell('A7')->getValue());
        $this->assertSame(4850.0, $unico->getActiveSheet()->getCell('H7')->getValue());
        $this->assertStringNotContainsString('MATERIAL-1', $this->textoHoja($unico));
        $this->assertStringNotContainsString('COSTO UNITARIO', $this->textoHoja($unico));
        $unico->disconnectWorksheets();

        $detallado = $exportador->libro($actual, 'detallado');
        $this->assertStringContainsString('MATERIAL-1', $this->textoHoja($detallado));
        $this->assertStringNotContainsString('Costo neto estimado', $this->textoHoja($detallado));
        $detallado->disconnectWorksheets();

        $interno = app(ExportarCotizacionCosteoExcelService::class)->libro($actual);
        $this->assertStringContainsString('Utilidad estimada al precio pactado', $this->textoHoja($interno));
        $this->assertStringContainsString('MATERIAL-1', $this->textoHoja($interno));
        $interno->disconnectWorksheets();

        $this->get(route('cotizaciones-cliente.excel-cliente', [$actual, 'precio-unico']))
            ->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_no_permite_ajustar_documento_cerrado(): void
    {
        [$cotizacion, $usuario] = $this->prepararCotizacion();
        app(SincronizarHojaCostosCotizacionService::class)->sincronizar($cotizacion);
        $cotizacion->update(['estado' => 'CERRADA']);

        $this->actingAs($usuario)->patch(route('cotizaciones-cliente.precio-final', $cotizacion), [
            'precio_final_pactado' => '4850.00',
        ])->assertSessionHasErrors('precio_final_pactado');
        $this->assertNull($cotizacion->fresh()->precio_final_pactado);
    }

    public function test_cambia_moneda_y_precio_pactado_sin_alterar_el_costeo_y_los_documentos(): void
    {
        [$cotizacion, $usuario] = $this->prepararCotizacion();
        $this->actingAs($usuario);
        app(SincronizarHojaCostosCotizacionService::class)->sincronizar($cotizacion);
        $costos = $cotizacion->presupuestos()->orderBy('id')->pluck('costo_neto_soles')->all();
        $margenes = $cotizacion->presupuestos()->orderBy('id')->pluck('margen_porcentaje')->all();

        $this->patch(route('cotizaciones-cliente.moneda-comercial', $cotizacion), [
            'moneda' => 'USD', 'tipo_cambio' => '3.750000', 'precio_final_pactado' => '190.00',
        ])->assertRedirect(route('cotizaciones-cliente.show', $cotizacion).'#resumen-comercial')
            ->assertSessionHasNoErrors();

        $actual = $cotizacion->fresh();
        $this->assertSame('USD', $actual->moneda);
        $this->assertEqualsWithDelta(3.75, (float) $actual->tipo_cambio, 0.000001);
        $this->assertEqualsWithDelta(190, (float) $actual->total, 0.0001);
        $this->assertEqualsWithDelta(190, (float) $actual->detalles()->sum('total'), 0.0001);
        $this->assertSame($costos, $actual->presupuestos()->orderBy('id')->pluck('costo_neto_soles')->all());
        $this->assertSame($margenes, $actual->presupuestos()->orderBy('id')->pluck('margen_porcentaje')->all());
        $excel = app(ExportarCotizacionClienteExcelService::class)->libro($actual, 'precio-unico');
        $this->assertSame('PRECIO FINAL (USD)', $excel->getActiveSheet()->getCell('H6')->getValue());
        $this->assertSame(190.0, $excel->getActiveSheet()->getCell('H7')->getValue());
        $excel->disconnectWorksheets();
        $this->get(route('cotizaciones-cliente.documento', $actual))
            ->assertOk()->assertSee('USD 190.00');

        $this->patch(route('cotizaciones-cliente.moneda-comercial', $actual), [
            'moneda' => 'PEN', 'tipo_cambio' => '4.000000', 'precio_final_pactado' => '760.00',
        ])->assertSessionHasNoErrors();
        $actual = $cotizacion->fresh();
        $this->assertSame('PEN', $actual->moneda);
        $this->assertEqualsWithDelta(4, (float) $actual->tipo_cambio, 0.000001);
        $this->assertEqualsWithDelta(760, (float) $actual->total, 0.0001);
        $this->assertSame($costos, $actual->presupuestos()->orderBy('id')->pluck('costo_neto_soles')->all());
    }

    public function test_cambio_exige_tc_y_version_abierta_y_preserva_la_version_cerrada(): void
    {
        [$cotizacion, $usuario] = $this->prepararCotizacion();
        $this->actingAs($usuario);
        app(SincronizarHojaCostosCotizacionService::class)->sincronizar($cotizacion);
        $anterior = $cotizacion->fresh();

        $this->patch(route('cotizaciones-cliente.moneda-comercial', $cotizacion), [
            'moneda' => 'USD', 'tipo_cambio' => '', 'precio_final_pactado' => '190.00',
        ])->assertSessionHasErrors('tipo_cambio');
        $this->assertSame('PEN', $cotizacion->fresh()->moneda);
        $this->patch(route('cotizaciones-cliente.moneda-comercial', $cotizacion), [
            'moneda' => 'USD', 'tipo_cambio' => '3.75', 'precio_final_pactado' => '0.01',
        ])->assertSessionHasErrors('precio_final_pactado');
        $this->assertSame('PEN', $cotizacion->fresh()->moneda);
        $this->assertEqualsWithDelta((float) $anterior->total, (float) $cotizacion->fresh()->total, 0.0001);

        $cotizacion->update(['estado' => 'CERRADA']);
        $this->patch(route('cotizaciones-cliente.moneda-comercial', $cotizacion), [
            'moneda' => 'USD', 'tipo_cambio' => '3.75', 'precio_final_pactado' => '190.00',
        ])->assertSessionHasErrors('moneda');
        $respuestaVersion = $this->post(route('cotizaciones-cliente.version', $cotizacion));
        $nueva = CotizacionCliente::query()->where('codigo_base', $cotizacion->codigo_base)
            ->where('version', 2)->firstOrFail();
        $respuestaVersion->assertRedirect(route('cotizaciones-cliente.show', $nueva));
        $this->patch(route('cotizaciones-cliente.moneda-comercial', $nueva), [
            'moneda' => 'USD', 'tipo_cambio' => '3.75', 'precio_final_pactado' => '190.00',
        ])->assertSessionHasNoErrors();

        $this->assertSame('PEN', $anterior->fresh()->moneda);
        $this->assertEqualsWithDelta((float) $anterior->total, (float) $anterior->fresh()->total, 0.0001);
        $this->assertSame('USD', $nueva->fresh()->moneda);
        $this->assertEqualsWithDelta(190, (float) $nueva->fresh()->total, 0.0001);
    }

    private function prepararCotizacion(): array
    {
        $role = Role::query()->firstOrCreate(['codigo' => 'COMERCIAL_LOGISTICA'], [
            'nombre' => 'Comercial', 'estado' => true,
        ]);
        $usuario = User::query()->create([
            'role_id' => $role->id, 'username' => 'logistica-243', 'email' => 'logistica-243@example.com',
            'password' => 'clave', 'estado' => true, 'fecha_creacion' => now(),
        ]);
        $tipoCliente = TipoCliente::query()->firstOrCreate(['codigo' => 'FINAL'], [
            'nombre' => 'Final', 'porcentaje_ganancia' => 15, 'estado' => true,
        ]);
        $cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id, 'tipo_documento' => 'RUC',
            'numero_documento' => '20600000243', 'ruc' => '20600000243',
            'razon_social' => 'Cliente 243', 'estado' => true,
        ]);
        $tipo = TipoOrden::query()->firstOrCreate(['codigo' => 'OM'], ['nombre' => 'OM', 'estado' => true]);
        $unidad = UnidadMedida::query()->firstOrCreate(['codigo' => 'UND'], ['nombre' => 'Unidad', 'estado' => true]);
        $cotizacion = CotizacionCliente::query()->create([
            'origen' => 'DIRECTA_LOGISTICA', 'cliente_id' => $cliente->id,
            'tipo_orden_id' => $tipo->id, 'descripcion_trabajo' => 'Fabricación hidráulica',
            'codigo_base' => 'COT-243', 'version' => 1, 'codigo' => 'COT-243-VRS1',
            'cliente_nombre' => $cliente->razon_social, 'fecha_emision' => today(),
            'moneda' => 'PEN', 'tipo_cambio' => 3.8, 'estado' => 'ABIERTA',
            'cotizado_por' => $usuario->id,
        ]);
        $componente = $cotizacion->componentes()->create([
            'tipo_orden_id' => $tipo->id, 'descripcion_componente' => 'Fabricación hidráulica',
            'tipo_cambio_comparacion' => 3.8, 'orden_secuencia' => 1,
        ]);
        $presupuesto = app(PresupuestoCotizacionService::class);
        foreach ([1 => 1000, 2 => 500] as $indice => $costo) {
            $producto = Producto::query()->create([
                'unidad_medida_id' => $unidad->id, 'codigo' => 'MATERIAL-'.$indice,
                'descripcion' => 'Material '.$indice, 'estado' => true,
                'permite_fraccionamiento' => false,
            ]);
            $presupuesto->registrar($cotizacion, [
                'componente_id' => $componente->id, 'tipo_costo' => 'MATERIAL',
                'producto_id' => $producto->id, 'descripcion' => $producto->descripcion,
                'cantidad' => 1, 'unidad' => 'UND', 'moneda' => 'PEN',
                'tipo_cambio' => 3.8, 'costo_unitario' => $costo,
                'margen_porcentaje' => $indice === 1 ? 5 : 15,
                'igv_modo' => 'AGREGAR', 'igv_porcentaje' => 18,
                'igv_venta_porcentaje' => 18,
            ], $usuario);
        }
        $presupuesto->registrar($cotizacion, [
            'componente_id' => $componente->id, 'tipo_costo' => 'SERVICIO_TERCERO',
            'ejecucion_servicio' => 'EXTERNO', 'descripcion' => 'Servicio adicional',
            'cantidad' => 1, 'unidad' => 'SERVICIO', 'moneda' => 'PEN',
            'tipo_cambio' => 3.8, 'costo_unitario' => 120,
            'margen_porcentaje' => 15, 'igv_modo' => 'AGREGAR',
            'igv_porcentaje' => 18, 'igv_venta_porcentaje' => 18,
        ], $usuario);

        return [$cotizacion, $usuario];
    }

    private function textoHoja(\PhpOffice\PhpSpreadsheet\Spreadsheet $libro): string
    {
        $hoja = $libro->getActiveSheet();
        $texto = [];
        foreach ($hoja->getRowIterator() as $fila) {
            foreach ($fila->getCellIterator() as $celda) {
                if ($celda->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING) {
                    $texto[] = $celda->getValue();
                }
            }
        }
        return implode(' ', $texto);
    }
}
