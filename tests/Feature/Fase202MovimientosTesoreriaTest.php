<?php

namespace Tests\Feature;

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

class Fase202MovimientosTesoreriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_reune_movimientos_vigentes_por_moneda_fecha_y_tipo_sin_sumar_anulados(): void
    {
        $contabilidad = $this->usuario('CONTABILIDAD');
        $factura = $this->factura($contabilidad, 'PEN', '2201');
        $cotizacion = $this->cotizacion($contabilidad, 'PEN', '2201');
        $otra = $this->cotizacion($contabilidad, 'USD', '2202');

        $factura->pagos()->create([
            'fecha_pago' => today()->toDateString(), 'monto' => 30,
            'medio_pago' => 'TRANSFERENCIA', 'referencia' => 'PAGO-2201',
            'registrado_por' => $contabilidad->id,
        ]);
        $cotizacion->cobros()->create([
            'fecha_cobro' => today()->toDateString(), 'monto' => 80,
            'medio_cobro' => 'TRANSFERENCIA', 'referencia' => 'COBRO-2201',
            'registrado_por' => $contabilidad->id,
        ]);
        $cotizacion->cobros()->create([
            'fecha_cobro' => today()->toDateString(), 'monto' => 10,
            'medio_cobro' => 'EFECTIVO', 'referencia' => 'ANULADO-2201',
            'registrado_por' => $contabilidad->id, 'anulado_en' => now(),
            'anulado_por' => $contabilidad->id, 'motivo_anulacion' => 'Duplicado',
        ]);
        $otra->cobros()->create([
            'fecha_cobro' => today()->toDateString(), 'monto' => 20,
            'medio_cobro' => 'TRANSFERENCIA', 'referencia' => 'USD-2202',
            'registrado_por' => $contabilidad->id,
        ]);

        $this->actingAs($contabilidad)->get(route('tesoreria.movimientos.index'))
            ->assertOk()->assertSee('PAGO-2201')->assertSee('COBRO-2201')
            ->assertSee('USD-2202')->assertDontSee('ANULADO-2201')
            ->assertSee('50.00');

        $this->actingAs($contabilidad)->get(route('tesoreria.movimientos.index', ['tipo' => 'PAGO', 'moneda' => 'PEN']))
            ->assertOk()->assertSee('PAGO-2201')->assertDontSee('COBRO-2201')->assertDontSee('USD-2202');

        $this->actingAs($contabilidad)->get(route('tesoreria.movimientos.index', [
            'desde' => today()->addDay()->toDateString(),
        ]))->assertOk()->assertDontSee('PAGO-2201')->assertDontSee('COBRO-2201');

        $this->actingAs($contabilidad)->get(route('tesoreria.movimientos.index', [
            'desde' => today()->toDateString(), 'hasta' => today()->subDay()->toDateString(),
        ]))->assertSessionHasErrors('hasta');

        // El CSV comparte el filtro y entrega todas las filas, incluidas las que no caben en la primera página.
        for ($i = 1; $i <= 21; $i++) {
            $cotizacion->cobros()->create([
                'fecha_cobro' => today()->toDateString(), 'monto' => '1.2345',
                'medio_cobro' => 'TRANSFERENCIA',
                'referencia' => $i === 21 ? '=SUM(1+1)' : 'EXTRA-'.$i,
                'registrado_por' => $contabilidad->id,
            ]);
        }

        $csv = $this->actingAs($contabilidad)->get(route('tesoreria.movimientos.csv'))
            ->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertSame(25, substr_count($csv, "\n")); // Cabecera + 24 movimientos vigentes.
        $this->assertStringContainsString("'=SUM(1+1)", $csv);
        $this->assertStringContainsString('1,2345', $csv);
        $this->assertStringNotContainsString('ANULADO-2201', $csv);

        $filtrado = $this->get(route('tesoreria.movimientos.csv', ['tipo' => 'PAGO', 'moneda' => 'PEN']))
            ->assertOk()->streamedContent();
        $filas = array_map(fn ($fila) => str_getcsv($fila, ';', '"', ''), array_filter(explode("\n", trim($filtrado))));
        $this->assertCount(2, $filas);
        $this->assertSame('F001-2201', $filas[1][2]);
        $this->assertSame('30,0000', $filas[1][7]);
        $this->get(route('tesoreria.movimientos.csv', [
            'desde' => today()->toDateString(), 'hasta' => today()->subDay()->toDateString(),
        ]))->assertSessionHasErrors('hasta');
    }

    public function test_acceso_limitado_a_contabilidad_y_administrador(): void
    {
        $this->actingAs($this->usuario('ALMACEN'))
            ->get(route('tesoreria.movimientos.index'))->assertForbidden();
        $this->get(route('tesoreria.movimientos.csv'))->assertForbidden();
        $this->actingAs($this->usuario('ADMINISTRADOR'))
            ->get(route('tesoreria.movimientos.index'))->assertOk();
    }

    private function usuario(string $codigo): User
    {
        $rol = Role::query()->firstOrCreate(['codigo' => $codigo], ['nombre' => $codigo, 'estado' => true]);
        $nombre = strtolower($codigo).'-'.(User::query()->count() + 1);

        return User::query()->create([
            'role_id' => $rol->id, 'username' => $nombre, 'email' => $nombre.'@example.com',
            'password' => 'password-seguro', 'estado' => true, 'fecha_creacion' => now(),
        ]);
    }

    private function factura(User $usuario, string $moneda, string $numero): FacturaProveedor
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
            'codigo' => 'OC-'.$numero, 'fecha_emision' => today(),
            'emitido_por' => $usuario->id, 'moneda' => $moneda,
            'total' => 100, 'estado' => 'APROBADA',
        ]);

        return FacturaProveedor::query()->create([
            'orden_compra_id' => $orden->id, 'proveedor_id' => $proveedor->id, 'tipo_documento' => 'FACTURA',
            'serie' => 'F001', 'numero' => $numero, 'fecha_emision' => today(),
            'moneda' => $moneda, 'total' => 100, 'registrado_por' => $usuario->id,
            'estado' => 'PARCIAL',
        ]);
    }

    private function cotizacion(User $usuario, string $moneda, string $numero): CotizacionCliente
    {
        $tipoCliente = TipoCliente::query()->firstOrCreate(['codigo' => 'FINAL'], [
            'nombre' => 'Final', 'porcentaje_ganancia' => 0, 'estado' => true,
        ]);
        $cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id, 'tipo_documento' => 'RUC',
            'numero_documento' => '2060020'.$numero, 'ruc' => '2060020'.$numero,
            'razon_social' => 'Cliente '.$numero, 'estado' => true,
        ]);
        $tipo = TipoOrden::query()->firstOrCreate(['codigo' => 'OS'], ['nombre' => 'Servicio', 'estado' => true]);
        $proforma = Proforma::query()->create([
            'cliente_id' => $cliente->id, 'codigo' => 'PRO-'.$numero,
            'fecha_emision' => today(), 'moneda' => $moneda,
            'estado' => 'COTIZADA', 'registrado_por' => $usuario->id,
        ]);

        return CotizacionCliente::query()->create([
            'proforma_id' => $proforma->id, 'origen' => 'PROFORMA_ALMACEN', 'cliente_id' => $cliente->id,
            'tipo_orden_id' => $tipo->id, 'codigo_base' => 'COB-'.$numero,
            'version' => 1, 'codigo' => 'COB-'.$numero.'-VRS1',
            'cliente_nombre' => $cliente->razon_social, 'fecha_emision' => today(),
            'moneda' => $moneda, 'total' => 100, 'estado' => 'CERRADA',
            'cotizado_por' => $usuario->id,
        ]);
    }
}
