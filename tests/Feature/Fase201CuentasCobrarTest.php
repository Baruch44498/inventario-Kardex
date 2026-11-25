<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\OrdenOperacion;
use App\Models\Proforma;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase201CuentasCobrarTest extends TestCase
{
    use RefreshDatabase;

    public function test_venta_directa_cobros_parciales_sobrecobro_anulacion_y_version(): void
    {
        $contabilidad = $this->usuario('CONTABILIDAD');
        $logistica = $this->usuario('COMERCIAL_LOGISTICA');
        $cotizacion = $this->cotizacion($logistica, true, 'USD', 118);

        $this->actingAs($contabilidad)->get(route('cuentas-cobrar.index'))
            ->assertOk()->assertSee($cotizacion->codigo);
        $this->actingAs($contabilidad)->get(route('cuentas-cobrar.show', $cotizacion))
            ->assertOk()->assertSee('Saldo por cobrar')->assertSee('US$');

        $this->actingAs($contabilidad)
            ->post(route('cuentas-cobrar.cobros.store', $cotizacion), $this->cobro('50'))
            ->assertRedirect();
        $this->assertSame(68.0, $cotizacion->fresh()->saldoPorCobrar());
        $cotizacion->update(['cliente_nombre' => '=SUM(1+1)']);
        $csv = $this->actingAs($contabilidad)->get(route('cuentas-cobrar.csv', ['origen' => 'VENTA_DIRECTA', 'moneda' => 'USD']))
            ->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $filas = array_map(fn ($fila) => str_getcsv($fila, ';', '"', ''), array_filter(explode("\n", trim($csv))));
        $this->assertCount(2, $filas);
        $this->assertSame($cotizacion->codigo, $filas[1][1]);
        $this->assertSame("'=SUM(1+1)", $filas[1][2]);
        $this->assertSame(['118,0000', '50,0000', '68,0000', 'PARCIAL'], array_slice($filas[1], 8, 4));

        $this->actingAs($logistica)
            ->post(route('cotizaciones-cliente.version', $cotizacion))
            ->assertSessionHasErrors('version');
        $this->actingAs($logistica)
            ->patch(route('cotizaciones-cliente.anular', $cotizacion), ['motivo_anulacion' => 'Anular venta valorizada'])
            ->assertSessionHasErrors('motivo_anulacion');

        $this->actingAs($contabilidad)
            ->post(route('cuentas-cobrar.cobros.store', $cotizacion), $this->cobro('68.0001'))
            ->assertSessionHasErrors('monto');
        $this->actingAs($contabilidad)
            ->post(route('cuentas-cobrar.cobros.store', $cotizacion), $this->cobro('68'))
            ->assertRedirect();
        $this->assertSame(0.0, $cotizacion->fresh()->saldoPorCobrar());

        $pago = $cotizacion->cobros()->where('monto', 68)->firstOrFail();
        $this->actingAs($contabilidad)
            ->patch(route('cuentas-cobrar.cobros.anular', [$cotizacion, $pago]), ['motivo_anulacion' => 'Ingreso duplicado'])
            ->assertRedirect();
        $this->assertSame(68.0, $cotizacion->fresh()->saldoPorCobrar());
        $this->assertSame($contabilidad->id, $pago->fresh()->anulado_por);
        $this->assertDatabaseCount('cobros_cotizacion_cliente', 2);
    }

    public function test_trabajo_solo_se_cobra_al_cerrar_orden_principal_y_no_duplica_versiones(): void
    {
        $contabilidad = $this->usuario('CONTABILIDAD');
        $logistica = $this->usuario('COMERCIAL_LOGISTICA');
        $cotizacion = $this->cotizacion($logistica, false, 'PEN', 200);

        $this->actingAs($contabilidad)->get(route('cuentas-cobrar.index'))
            ->assertOk()->assertDontSee($cotizacion->codigo);
        $this->actingAs($contabilidad)
            ->post(route('cuentas-cobrar.cobros.store', $cotizacion), $this->cobro('10'))
            ->assertSessionHasErrors('monto');

        $cotizacion->ordenOperacion->update(['estado' => 'CERRADA', 'cerrado_en' => now()]);
        $this->actingAs($contabilidad)->get(route('cuentas-cobrar.index', ['origen' => 'ORDEN']))
            ->assertOk()->assertSee($cotizacion->codigo);
        $this->actingAs($contabilidad)
            ->post(route('cuentas-cobrar.cobros.store', $cotizacion), $this->cobro('20'))
            ->assertRedirect();
        $this->assertSame(180.0, $cotizacion->fresh()->saldoPorCobrar());
        $csvOrdenes = $this->get(route('cuentas-cobrar.csv', ['origen' => 'ORDEN']))
            ->assertOk()->streamedContent();
        $this->assertStringContainsString($cotizacion->codigo, $csvOrdenes);
        $this->assertStringContainsString('180,0000', $csvOrdenes);

        CotizacionCliente::query()->create([
            'origen' => 'DIRECTA_LOGISTICA', 'cliente_id' => $cotizacion->cliente_id,
            'tipo_orden_id' => $cotizacion->tipo_orden_id,
            'codigo_base' => $cotizacion->codigo_base, 'version' => 2,
            'codigo' => 'COB-2011-VRS2', 'cliente_nombre' => $cotizacion->cliente_nombre,
            'fecha_emision' => today(), 'moneda' => 'PEN', 'total' => 300,
            'estado' => 'ABIERTA', 'cotizado_por' => $logistica->id,
        ]);
        $this->actingAs($contabilidad)->get(route('cuentas-cobrar.index'))
            ->assertOk()->assertSee($cotizacion->codigo)->assertDontSee('COB-2011-VRS2');

        $otro = $this->cotizacion($logistica, true, 'PEN', 30, '2012');
        $cobroAjeno = $otro->cobros()->create($this->cobro('5') + ['registrado_por' => $contabilidad->id]);
        $this->actingAs($contabilidad)
            ->patch(route('cuentas-cobrar.cobros.anular', [$cotizacion, $cobroAjeno]), ['motivo_anulacion' => 'No corresponde'])
            ->assertNotFound();
    }

    public function test_logistica_no_registra_cobros_y_version_anterior_no_aparece(): void
    {
        $logistica = $this->usuario('COMERCIAL_LOGISTICA');
        $contabilidad = $this->usuario('CONTABILIDAD');
        $anterior = $this->cotizacion($logistica, true, 'PEN', 50);

        CotizacionCliente::query()->create([
            'proforma_id' => $anterior->proforma_id, 'origen' => 'PROFORMA_ALMACEN',
            'cliente_id' => $anterior->cliente_id, 'codigo_base' => $anterior->codigo_base,
            'version' => 2, 'codigo' => 'COB-2011-VRS2',
            'cliente_nombre' => $anterior->cliente_nombre, 'fecha_emision' => today(),
            'moneda' => 'PEN', 'total' => 60, 'estado' => 'ABIERTA',
            'cotizado_por' => $logistica->id,
        ]);

        $this->actingAs($contabilidad)->get(route('cuentas-cobrar.index'))
            ->assertOk()->assertDontSee($anterior->codigo);
        $this->actingAs($logistica)->get(route('cuentas-cobrar.index'))->assertForbidden();
        $this->get(route('cuentas-cobrar.csv'))->assertForbidden();
        $this->actingAs($logistica)
            ->post(route('cuentas-cobrar.cobros.store', $anterior), $this->cobro('10'))
            ->assertForbidden();
        $this->actingAs($contabilidad)
            ->post(route('cuentas-cobrar.cobros.store', $anterior), $this->cobro('10'))
            ->assertSessionHasErrors('monto');
    }

    private function usuario(string $rol): User
    {
        $role = Role::query()->firstOrCreate(['codigo' => $rol], ['nombre' => $rol, 'estado' => true]);
        $nombre = strtolower($rol).'-'.(User::query()->count() + 1);

        return User::query()->create([
            'role_id' => $role->id, 'username' => $nombre,
            'email' => $nombre.'@example.com', 'password' => 'password-seguro',
            'estado' => true, 'fecha_creacion' => now(),
        ]);
    }

    private function cotizacion(User $usuario, bool $ventaDirecta, string $moneda, float $total, string $codigo = '2011'): CotizacionCliente
    {
        $tipoCliente = TipoCliente::query()->firstOrCreate(['codigo' => 'FINAL'], ['nombre' => 'Final', 'porcentaje_ganancia' => 0, 'estado' => true]);
        $cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id, 'tipo_documento' => 'RUC',
            'numero_documento' => '2060020'.$codigo, 'ruc' => '2060020'.$codigo,
            'razon_social' => 'Cliente Cobros '.$codigo, 'estado' => true,
        ]);
        $tipo = TipoOrden::query()->firstOrCreate(['codigo' => 'OS'], ['nombre' => 'Servicio', 'estado' => true]);
        $proforma = $ventaDirecta ? Proforma::query()->create([
            'cliente_id' => $cliente->id, 'codigo' => 'PRO-'.$codigo,
            'fecha_emision' => today(), 'moneda' => $moneda,
            'estado' => 'COTIZADA', 'registrado_por' => $usuario->id,
        ]) : null;
        $orden = ! $ventaDirecta ? OrdenOperacion::query()->create([
            'tipo_orden_id' => $tipo->id, 'cliente_id' => $cliente->id,
            'codigo_orden' => 'OS-'.$codigo, 'fecha_apertura' => today(),
            'estado' => 'EN_PROCESO', 'creado_por' => $usuario->id,
        ]) : null;

        return CotizacionCliente::query()->create([
            'proforma_id' => $proforma?->id,
            'origen' => $ventaDirecta ? 'PROFORMA_ALMACEN' : 'DIRECTA_LOGISTICA',
            'cliente_id' => $cliente->id, 'tipo_orden_id' => $tipo->id,
            'codigo_base' => 'COB-'.$codigo, 'version' => 1,
            'codigo' => 'COB-'.$codigo.'-VRS1',
            'cliente_nombre' => $cliente->razon_social,
            'fecha_emision' => today(), 'moneda' => $moneda,
            'tipo_cambio' => $moneda === 'USD' ? 3.8 : null,
            'subtotal' => $total, 'total' => $total,
            'estado' => $ventaDirecta ? 'CERRADA' : 'CONVERTIDA_EN_ORDEN',
            'cotizado_por' => $usuario->id, 'orden_operacion_id' => $orden?->id,
        ]);
    }

    /** @return array<string, string> */
    private function cobro(string $monto): array
    {
        return [
            'fecha_cobro' => today()->toDateString(), 'monto' => $monto,
            'medio_cobro' => 'TRANSFERENCIA', 'referencia' => 'COB-TEST',
        ];
    }
}
