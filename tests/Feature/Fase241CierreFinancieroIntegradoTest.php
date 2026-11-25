<?php

namespace Tests\Feature;

use App\Models\AuditoriaEvento;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionCliente;
use App\Models\FacturaProveedor;
use App\Models\OrdenCompra;
use App\Models\Proforma;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\Role;
use App\Models\SolicitudCompra;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase241CierreFinancieroIntegradoTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagos_y_cobros_recorridos_hasta_tesoreria_y_auditoria_incluida_anulacion(): void
    {
        $contabilidad = $this->usuario('CONTABILIDAD', 'contabilidad-241');
        $factura = $this->factura($contabilidad);
        $cotizacion = $this->ventaDirecta($contabilidad);

        $this->actingAs($contabilidad)
            ->post(route('facturas-proveedor.pagos.store', $factura), $this->pago('30.0000'))
            ->assertRedirect(route('facturas-proveedor.show', $factura));
        $this->post(route('cuentas-cobrar.cobros.store', $cotizacion), $this->cobro('80.0000'))
            ->assertRedirect(route('cuentas-cobrar.show', $cotizacion));

        $pago = $factura->pagos()->firstOrFail();
        $cobro = $cotizacion->cobros()->firstOrFail();
        $this->assertSame('PARCIAL', $factura->fresh()->estado);
        $this->assertSame(88.0, $factura->fresh()->saldoPendiente());
        $this->assertSame(120.0, $cotizacion->fresh()->saldoPorCobrar());

        $porPagar = $this->get(route('cuentas-pagar.csv', ['estado' => 'PARCIAL']))->assertOk()->streamedContent();
        $this->assertStringContainsString('88,0000', $porPagar);
        $porCobrar = $this->get(route('cuentas-cobrar.csv', ['origen' => 'VENTA_DIRECTA']))->assertOk()->streamedContent();
        $this->assertStringContainsString('120,0000', $porCobrar);

        $this->get(route('tesoreria.movimientos.index'))->assertOk()
            ->assertSee('PAGO-241')->assertSee('COBRO-241')->assertSee('50.00');
        $movimientos = $this->get(route('tesoreria.movimientos.csv'))->assertOk()->streamedContent();
        $this->assertStringContainsString('PAGO-241', $movimientos);
        $this->assertStringContainsString('COBRO-241', $movimientos);

        foreach ([['PagoFacturaProveedor', $pago->id], ['CobroCotizacionCliente', $cobro->id]] as [$entidad, $id]) {
            $this->assertDatabaseHas('auditoria_eventos', [
                'entidad' => $entidad, 'entidad_id' => $id,
                'accion' => 'CREADO', 'usuario_id' => $contabilidad->id,
            ]);
        }

        $this->patch(route('facturas-proveedor.pagos.anular', [$factura, $pago]), [
            'motivo_anulacion' => 'Transferencia duplicada',
        ])->assertRedirect();
        $this->patch(route('cuentas-cobrar.cobros.anular', [$cotizacion, $cobro]), [
            'motivo_anulacion' => 'Cobro duplicado',
        ])->assertRedirect();

        $this->assertSame('REGISTRADA', $factura->fresh()->estado);
        $this->assertSame(118.0, $factura->fresh()->saldoPendiente());
        $this->assertSame(200.0, $cotizacion->fresh()->saldoPorCobrar());
        $this->assertNotNull($pago->fresh()->anulado_en);
        $this->assertNotNull($cobro->fresh()->anulado_en);
        $this->assertStringNotContainsString('PAGO-241', $this->get(route('tesoreria.movimientos.csv'))->assertOk()->streamedContent());
        $this->assertStringNotContainsString('COBRO-241', $this->get(route('tesoreria.movimientos.csv'))->assertOk()->streamedContent());
        foreach ([['PagoFacturaProveedor', $pago->id], ['CobroCotizacionCliente', $cobro->id]] as [$entidad, $id]) {
            $evento = AuditoriaEvento::query()->where('entidad', $entidad)
                ->where('entidad_id', $id)->where('accion', 'ACTUALIZADO')->firstOrFail();
            $this->assertSame($contabilidad->id, $evento->usuario_id);
            $this->assertContains('anulado_en', $evento->campos);
        }
    }

    public function test_rechazo_de_excesos_no_crea_movimientos_ni_eventos_y_roles_limitan_descargas(): void
    {
        $contabilidad = $this->usuario('CONTABILIDAD', 'contabilidad-limites-241');
        $almacen = $this->usuario('ALMACEN', 'almacen-limites-241');
        $factura = $this->factura($contabilidad);
        $cotizacion = $this->ventaDirecta($contabilidad);

        $this->actingAs($almacen)->get(route('cuentas-pagar.csv'))->assertForbidden();
        $this->get(route('cuentas-cobrar.csv'))->assertForbidden();
        $this->get(route('tesoreria.movimientos.csv'))->assertForbidden();

        $this->actingAs($contabilidad)
            ->post(route('facturas-proveedor.pagos.store', $factura), $this->pago('119'))
            ->assertSessionHasErrors('monto');
        $this->post(route('cuentas-cobrar.cobros.store', $cotizacion), $this->cobro('201'))
            ->assertSessionHasErrors('monto');

        $this->assertDatabaseCount('pagos_factura_proveedor', 0);
        $this->assertDatabaseCount('cobros_cotizacion_cliente', 0);
        $this->assertSame(0, AuditoriaEvento::query()->whereIn('entidad', [
            'PagoFacturaProveedor', 'CobroCotizacionCliente',
        ])->count());
        $this->assertStringNotContainsString('PAGO-241', $this->get(route('tesoreria.movimientos.csv'))->assertOk()->streamedContent());
    }

    private function usuario(string $rol, string $nombre): User
    {
        $role = Role::query()->firstOrCreate(['codigo' => $rol], ['nombre' => $rol, 'estado' => true]);

        return User::query()->create([
            'role_id' => $role->id, 'username' => $nombre,
            'email' => $nombre.'@example.com', 'password' => 'clave-de-prueba',
            'estado' => true, 'fecha_creacion' => now(),
        ]);
    }

    private function factura(User $usuario): FacturaProveedor
    {
        $proveedor = Proveedor::query()->create([
            'razon_social' => 'Proveedor integrado', 'ruc' => '20600002410', 'estado' => true,
        ]);
        $requisicion = Requisicion::query()->create([
            'codigo' => 'REQ-241', 'fecha_solicitud' => today(), 'solicitado_por' => $usuario->id,
        ]);
        $cotizacion = Cotizacion::query()->create([
            'requisicion_id' => $requisicion->id, 'proveedor_id' => $proveedor->id,
            'codigo' => 'CP-241', 'fecha_cotizacion' => today(), 'registrado_por' => $usuario->id,
        ]);
        $solicitud = SolicitudCompra::query()->create([
            'cotizacion_id' => $cotizacion->id, 'codigo' => 'SC-241',
            'fecha_solicitud' => today(), 'solicitado_por' => $usuario->id,
        ]);
        $orden = OrdenCompra::query()->create([
            'solicitud_compra_id' => $solicitud->id, 'proveedor_id' => $proveedor->id,
            'codigo' => 'OC-241', 'fecha_emision' => today(), 'emitido_por' => $usuario->id,
            'moneda' => 'PEN', 'total' => 118, 'estado' => 'APROBADA',
        ]);

        return FacturaProveedor::query()->create([
            'orden_compra_id' => $orden->id, 'proveedor_id' => $proveedor->id,
            'tipo_documento' => 'FACTURA', 'serie' => 'F001', 'numero' => '0241',
            'fecha_emision' => today(), 'fecha_vencimiento' => today()->addDays(10),
            'moneda' => 'PEN', 'total' => 118, 'estado' => 'REGISTRADA',
            'registrado_por' => $usuario->id,
        ]);
    }

    private function ventaDirecta(User $usuario): CotizacionCliente
    {
        $tipoCliente = TipoCliente::query()->firstOrCreate(['codigo' => 'FINAL'], [
            'nombre' => 'Final', 'porcentaje_ganancia' => 0, 'estado' => true,
        ]);
        $cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id, 'tipo_documento' => 'RUC',
            'numero_documento' => '20600202410', 'ruc' => '20600202410',
            'razon_social' => 'Cliente integrado', 'estado' => true,
        ]);
        $tipo = TipoOrden::query()->firstOrCreate(['codigo' => 'OS'], [
            'nombre' => 'Servicio', 'estado' => true,
        ]);
        $proforma = Proforma::query()->create([
            'cliente_id' => $cliente->id, 'codigo' => 'PRO-241',
            'fecha_emision' => today(), 'moneda' => 'PEN',
            'estado' => 'COTIZADA', 'registrado_por' => $usuario->id,
        ]);

        return CotizacionCliente::query()->create([
            'proforma_id' => $proforma->id, 'origen' => 'PROFORMA_ALMACEN',
            'cliente_id' => $cliente->id, 'tipo_orden_id' => $tipo->id,
            'codigo_base' => 'COB-241', 'codigo' => 'COB-241-VRS1', 'version' => 1,
            'cliente_nombre' => $cliente->razon_social, 'fecha_emision' => today(),
            'moneda' => 'PEN', 'total' => 200, 'estado' => 'CERRADA',
            'cotizado_por' => $usuario->id,
        ]);
    }

    /** @return array<string, string> */
    private function pago(string $monto): array
    {
        return [
            'fecha_pago' => today()->toDateString(), 'monto' => $monto,
            'medio_pago' => 'TRANSFERENCIA', 'referencia' => 'PAGO-241',
        ];
    }

    /** @return array<string, string> */
    private function cobro(string $monto): array
    {
        return [
            'fecha_cobro' => today()->toDateString(), 'monto' => $monto,
            'medio_cobro' => 'TRANSFERENCIA', 'referencia' => 'COBRO-241',
        ];
    }
}
