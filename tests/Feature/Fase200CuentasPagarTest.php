<?php

namespace Tests\Feature;

use App\Models\Cotizacion;
use App\Models\FacturaProveedor;
use App\Models\OrdenCompra;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\Role;
use App\Models\SolicitudCompra;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase200CuentasPagarTest extends TestCase
{
    use RefreshDatabase;

    public function test_pago_parcial_total_y_anulacion_recalculan_saldo_sin_borrar_historial(): void
    {
        $contabilidad = $this->usuario('CONTABILIDAD');
        $factura = $this->factura($contabilidad, 'PEN', 118);

        $this->actingAs($contabilidad)->get(route('cuentas-pagar.index'))
            ->assertOk()->assertSee('F001-2001')->assertSee('S/');

        $this->actingAs($contabilidad)
            ->post(route('facturas-proveedor.pagos.store', $factura), $this->pago('50.0000'))
            ->assertRedirect(route('facturas-proveedor.show', $factura));
        $this->assertSame('PARCIAL', $factura->fresh()->estado);
        $this->assertSame(68.0, $factura->fresh()->saldoPendiente());
        $factura->proveedor->update(['razon_social' => '=SUM(1+1)']);
        $csv = $this->actingAs($contabilidad)->get(route('cuentas-pagar.csv', ['estado' => 'PARCIAL', 'moneda' => 'PEN']))
            ->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $filas = array_map(fn ($fila) => str_getcsv($fila, ';', '"', ''), array_filter(explode("\n", trim($csv))));
        $this->assertCount(2, $filas);
        $this->assertSame('F001-2001', $filas[1][2]);
        $this->assertSame("'=SUM(1+1)", $filas[1][3]);
        $this->assertSame(['118,0000', '50,0000', '68,0000', 'PARCIAL'], array_slice($filas[1], 9, 4));
        $vencidas = $this->get(route('cuentas-pagar.csv', ['estado' => 'VENCIDA']))
            ->assertOk()->streamedContent();
        $this->assertStringNotContainsString('F001-2001', $vencidas);
        $this->actingAs($contabilidad)->get(route('facturas-proveedor.show', $factura))
            ->assertOk()->assertSee('Pagos de la factura')->assertSee('Total facturado');

        $almacen = $this->usuario('ALMACEN');
        $this->actingAs($almacen)
            ->patch(route('facturas-proveedor.anular', $factura), ['motivo_anulacion' => 'Registro incorrecto'])
            ->assertSessionHas('error');

        $this->actingAs($contabilidad)
            ->post(route('facturas-proveedor.pagos.store', $factura), $this->pago('68.0000'))
            ->assertRedirect();
        $this->assertSame('PAGADA', $factura->fresh()->estado);
        $this->assertSame(0.0, $factura->fresh()->saldoPendiente());

        $this->actingAs($contabilidad)
            ->post(route('facturas-proveedor.pagos.store', $factura), $this->pago('1'))
            ->assertSessionHasErrors('monto');
        $this->assertDatabaseCount('pagos_factura_proveedor', 2);

        $pago = $factura->pagos()->where('monto', 68)->firstOrFail();
        $this->actingAs($contabilidad)
            ->patch(route('facturas-proveedor.pagos.anular', [$factura, $pago]), ['motivo_anulacion' => 'Pago duplicado'])
            ->assertRedirect();
        $this->assertSame('PARCIAL', $factura->fresh()->estado);
        $this->assertSame(68.0, $factura->fresh()->saldoPendiente());
        $this->assertNotNull($pago->fresh()->anulado_en);
        $this->assertSame($contabilidad->id, $pago->fresh()->anulado_por);

        $this->actingAs($contabilidad)
            ->patch(route('facturas-proveedor.pagos.anular', [$factura, $pago]), ['motivo_anulacion' => 'Segundo intento'])
            ->assertSessionHasErrors('motivo_anulacion');
        $this->assertDatabaseCount('pagos_factura_proveedor', 2);
    }

    public function test_solo_contabilidad_registra_pagos_y_no_excede_saldo_en_dolares(): void
    {
        $contabilidad = $this->usuario('CONTABILIDAD');
        $almacen = $this->usuario('ALMACEN');
        $factura = $this->factura($contabilidad, 'USD', 20);

        $this->actingAs($almacen)->get(route('cuentas-pagar.index'))->assertForbidden();
        $this->get(route('cuentas-pagar.csv'))->assertForbidden();
        $this->actingAs($almacen)
            ->post(route('facturas-proveedor.pagos.store', $factura), $this->pago('5'))
            ->assertForbidden();

        $this->actingAs($contabilidad)
            ->post(route('facturas-proveedor.pagos.store', $factura), $this->pago('20.0001'))
            ->assertSessionHasErrors('monto');
        $this->actingAs($contabilidad)
            ->post(route('facturas-proveedor.pagos.store', $factura), $this->pago('20'))
            ->assertRedirect();
        $this->assertSame('PAGADA', $factura->fresh()->estado);
        $this->assertSame(20.0, $factura->fresh()->montoPagado());
        $this->assertDatabaseHas('pagos_factura_proveedor', ['factura_proveedor_id' => $factura->id, 'monto' => 20]);

        $this->actingAs($contabilidad)->get(route('cuentas-pagar.index', ['estado' => 'PAGADA']))
            ->assertOk()->assertSee('F001-2001');
    }

    public function test_validacion_de_fecha_factura_anulada_y_pago_ajeno(): void
    {
        $contabilidad = $this->usuario('CONTABILIDAD');
        $factura = $this->factura($contabilidad, 'PEN', 30);
        $otra = $this->factura($contabilidad, 'PEN', 40, '2002');

        $fechaAnterior = $this->pago('5');
        $fechaAnterior['fecha_pago'] = now()->subYear()->toDateString();
        $this->actingAs($contabilidad)
            ->post(route('facturas-proveedor.pagos.store', $factura), $fechaAnterior)
            ->assertSessionHasErrors('fecha_pago');

        $this->actingAs($contabilidad)
            ->post(route('facturas-proveedor.pagos.store', $otra), $this->pago('5'))
            ->assertRedirect();
        $pagoAjeno = $otra->pagos()->firstOrFail();
        $this->actingAs($contabilidad)
            ->patch(route('facturas-proveedor.pagos.anular', [$factura, $pagoAjeno]), ['motivo_anulacion' => 'No corresponde'])
            ->assertNotFound();

        $factura->update(['estado' => 'ANULADA']);
        $this->actingAs($contabilidad)
            ->post(route('facturas-proveedor.pagos.store', $factura), $this->pago('5'))
            ->assertSessionHasErrors('monto');
        $this->assertDatabaseCount('pagos_factura_proveedor', 1);
    }

    private function usuario(string $codigo): User
    {
        $rol = Role::query()->firstOrCreate(['codigo' => $codigo], ['nombre' => $codigo, 'estado' => true]);
        $nombre = strtolower($codigo).'-'.(User::query()->count() + 1);

        return User::query()->create([
            'role_id' => $rol->id,
            'username' => $nombre,
            'email' => $nombre.'@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
    }

    private function factura(User $usuario, string $moneda, float $total, string $numero = '2001'): FacturaProveedor
    {
        $proveedor = Proveedor::query()->create(['razon_social' => 'Proveedor de prueba', 'ruc' => '2060000'.$numero, 'estado' => true]);
        $requisicion = Requisicion::query()->create([
            'codigo' => 'REQ-'.$numero, 'fecha_solicitud' => today(), 'solicitado_por' => $usuario->id,
        ]);
        $cotizacion = Cotizacion::query()->create([
            'requisicion_id' => $requisicion->id, 'proveedor_id' => $proveedor->id,
            'codigo' => 'CP-'.$numero, 'fecha_cotizacion' => today(), 'registrado_por' => $usuario->id,
        ]);
        $solicitud = SolicitudCompra::query()->create([
            'cotizacion_id' => $cotizacion->id, 'codigo' => 'SC-'.$numero,
            'fecha_solicitud' => today(), 'solicitado_por' => $usuario->id,
        ]);
        $orden = OrdenCompra::query()->create([
            'solicitud_compra_id' => $solicitud->id, 'proveedor_id' => $proveedor->id,
            'codigo' => 'OC-'.$numero, 'fecha_emision' => today(), 'emitido_por' => $usuario->id,
            'moneda' => $moneda, 'total' => $total, 'estado' => 'APROBADA',
        ]);

        return FacturaProveedor::query()->create([
            'orden_compra_id' => $orden->id, 'proveedor_id' => $proveedor->id,
            'tipo_documento' => 'FACTURA', 'serie' => 'F001', 'numero' => $numero,
            'fecha_emision' => today(), 'fecha_vencimiento' => today()->addDays(15),
            'moneda' => $moneda, 'tipo_cambio' => $moneda === 'USD' ? 3.8 : null,
            'total' => $total, 'registrado_por' => $usuario->id, 'estado' => 'REGISTRADA',
        ]);
    }

    /** @return array<string, string> */
    private function pago(string $monto): array
    {
        return [
            'fecha_pago' => today()->toDateString(),
            'monto' => $monto,
            'medio_pago' => 'TRANSFERENCIA',
            'referencia' => 'OP-1234',
        ];
    }
}
