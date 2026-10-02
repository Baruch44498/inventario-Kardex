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

class Fase205VencimientosCuentasPagarTest extends TestCase
{
    use RefreshDatabase;

    public function test_resumen_separa_monedas_y_descuenta_solo_pagos_vigentes(): void
    {
        $usuario = $this->usuario('CONTABILIDAD');
        $vencida = $this->factura($usuario, '2001', 'PEN', today()->subDay()->toDateString(), 100);
        $vencida->pagos()->create([
            'fecha_pago' => today(), 'monto' => 25,
            'medio_pago' => 'TRANSFERENCIA', 'registrado_por' => $usuario->id,
        ]);
        $vencida->pagos()->create([
            'fecha_pago' => today(), 'monto' => 20,
            'medio_pago' => 'TRANSFERENCIA', 'registrado_por' => $usuario->id,
            'anulado_en' => now(),
        ]);
        $this->factura($usuario, '2002', 'PEN', today()->addDays(7)->toDateString(), 40);
        $this->factura($usuario, '2003', 'PEN', today()->addDays(8)->toDateString(), 30);
        $this->factura($usuario, '2004', 'USD', null, 20);
        $pagada = $this->factura($usuario, '2005', 'PEN', today()->subDays(2)->toDateString(), 10);
        $pagada->pagos()->create([
            'fecha_pago' => today(), 'monto' => 10,
            'medio_pago' => 'TRANSFERENCIA', 'registrado_por' => $usuario->id,
        ]);
        $pagada->update(['estado' => 'PAGADA']);

        $this->actingAs($usuario)->get(route('cuentas-pagar.index'))
            ->assertOk()->assertSee('Saldos pendientes por vencimiento')
            ->assertSee('75.00')->assertSee('40.00')->assertSee('30.00')
            ->assertSee('145.00')->assertSee('20.00');

        $this->get(route('cuentas-pagar.index', ['estado' => 'POR_VENCER']))
            ->assertOk()->assertSee('F001-2002')->assertDontSee('F001-2001')
            ->assertDontSee('F001-2003')->assertDontSee('F001-2005');
        $this->get(route('cuentas-pagar.index', ['estado' => 'SIN_VENCIMIENTO']))
            ->assertOk()->assertSee('F001-2004')->assertDontSee('F001-2002');

        $csv = $this->get(route('cuentas-pagar.csv', ['estado' => 'POR_VENCER']))
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('F001-2002', $csv);
        $this->assertStringNotContainsString('F001-2001', $csv);
        $this->assertStringNotContainsString('F001-2004', $csv);
    }

    public function test_filtros_nuevos_conservan_permisos_y_validacion(): void
    {
        $this->get(route('cuentas-pagar.index'))->assertRedirect(route('login'));
        $almacen = $this->usuario('ALMACEN');
        $this->actingAs($almacen)->get(route('cuentas-pagar.index', ['estado' => 'POR_VENCER']))
            ->assertForbidden();
        $this->get(route('cuentas-pagar.csv', ['estado' => 'SIN_VENCIMIENTO']))
            ->assertForbidden();

        $contabilidad = $this->usuario('CONTABILIDAD');
        $this->actingAs($contabilidad)->get(route('cuentas-pagar.index', ['estado' => 'DESCONOCIDO']))
            ->assertSessionHasErrors('estado');
    }

    private function factura(User $usuario, string $numero, string $moneda, ?string $vencimiento, float $total): FacturaProveedor
    {
        $proveedor = Proveedor::query()->create([
            'razon_social' => 'Proveedor '.$numero, 'ruc' => '2060000'.$numero, 'estado' => true,
        ]);
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
            'fecha_emision' => today(), 'fecha_vencimiento' => $vencimiento,
            'moneda' => $moneda, 'total' => $total,
            'registrado_por' => $usuario->id, 'estado' => 'REGISTRADA',
        ]);
    }

    private function usuario(string $codigo): User
    {
        $rol = Role::query()->firstOrCreate(['codigo' => $codigo], ['nombre' => $codigo, 'estado' => true]);
        $nombre = strtolower($codigo).'-'.(User::query()->count() + 1);

        return User::query()->create([
            'role_id' => $rol->id, 'username' => $nombre,
            'email' => $nombre.'@example.com', 'password' => 'clave-de-prueba',
            'estado' => true, 'fecha_creacion' => now(),
        ]);
    }
}
