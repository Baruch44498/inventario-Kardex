<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\Proforma;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase206ResumenCuentasCobrarTest extends TestCase
{
    use RefreshDatabase;

    public function test_resumen_y_filtros_comparten_cotizaciones_cobrables_y_cobros_vigentes(): void
    {
        $logistica = $this->usuario('COMERCIAL_LOGISTICA');
        $contabilidad = $this->usuario('CONTABILIDAD');
        $parcial = $this->cotizacion($logistica, '2101', 'PEN', 100);
        $pendiente = $this->cotizacion($logistica, '2102', 'PEN', 50);
        $cobrada = $this->cotizacion($logistica, '2103', 'PEN', 20);
        $usd = $this->cotizacion($logistica, '2104', 'USD', 40);
        $borrador = $this->cotizacion($logistica, '2105', 'PEN', 500, 'ABIERTA');

        $parcial->cobros()->create([
            'fecha_cobro' => today(), 'monto' => 30, 'medio_cobro' => 'TRANSFERENCIA',
            'registrado_por' => $contabilidad->id,
        ]);
        $parcial->cobros()->create([
            'fecha_cobro' => today(), 'monto' => 10, 'medio_cobro' => 'TRANSFERENCIA',
            'registrado_por' => $contabilidad->id, 'anulado_en' => now(),
        ]);
        $cobrada->cobros()->create([
            'fecha_cobro' => today(), 'monto' => 20, 'medio_cobro' => 'TRANSFERENCIA',
            'registrado_por' => $contabilidad->id,
        ]);

        $this->actingAs($contabilidad)->get(route('cuentas-cobrar.index'))
            ->assertOk()->assertSee('Resumen de cuentas por cobrar filtradas')
            ->assertSee('170.00')->assertSee('50.00')->assertSee('120.00')
            ->assertSee('40.00')->assertDontSee($borrador->codigo);

        $this->get(route('cuentas-cobrar.index', ['estado' => 'PENDIENTE', 'moneda' => 'PEN']))
            ->assertOk()->assertSee($pendiente->codigo)->assertDontSee($parcial->codigo)
            ->assertDontSee($usd->codigo)->assertDontSee($borrador->codigo);
        $this->get(route('cuentas-cobrar.index', ['estado' => 'PARCIAL']))
            ->assertOk()->assertSee($parcial->codigo)->assertDontSee($cobrada->codigo)
            ->assertDontSee($pendiente->codigo);
        $this->get(route('cuentas-cobrar.index', ['estado' => 'COBRADA']))
            ->assertOk()->assertSee($cobrada->codigo)->assertDontSee($parcial->codigo);

        $csv = $this->get(route('cuentas-cobrar.csv', ['estado' => 'PARCIAL']))
            ->assertOk()->streamedContent();
        $this->assertStringContainsString($parcial->codigo, $csv);
        $this->assertStringNotContainsString($pendiente->codigo, $csv);
        $this->assertStringNotContainsString($cobrada->codigo, $csv);
    }

    public function test_resumen_y_csv_mantienen_permisos_y_validan_estado(): void
    {
        $this->get(route('cuentas-cobrar.index'))->assertRedirect(route('login'));
        $logistica = $this->usuario('COMERCIAL_LOGISTICA');
        $this->actingAs($logistica)->get(route('cuentas-cobrar.index', ['estado' => 'PARCIAL']))
            ->assertForbidden();
        $this->get(route('cuentas-cobrar.csv', ['estado' => 'COBRADA']))->assertForbidden();

        $contabilidad = $this->usuario('CONTABILIDAD');
        $this->actingAs($contabilidad)->get(route('cuentas-cobrar.index', ['estado' => 'DESCONOCIDO']))
            ->assertSessionHasErrors('estado');
    }

    private function cotizacion(User $usuario, string $codigo, string $moneda, float $total, string $estado = 'CERRADA'): CotizacionCliente
    {
        $tipoCliente = TipoCliente::query()->firstOrCreate(['codigo' => 'FINAL'], [
            'nombre' => 'Final', 'porcentaje_ganancia' => 0, 'estado' => true,
        ]);
        $cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id, 'tipo_documento' => 'RUC',
            'numero_documento' => '2060020'.$codigo, 'ruc' => '2060020'.$codigo,
            'razon_social' => 'Cliente '.$codigo, 'estado' => true,
        ]);
        $tipo = TipoOrden::query()->firstOrCreate(['codigo' => 'OS'], [
            'nombre' => 'Servicio', 'estado' => true,
        ]);
        $proforma = Proforma::query()->create([
            'cliente_id' => $cliente->id, 'codigo' => 'PRO-'.$codigo,
            'fecha_emision' => today(), 'moneda' => $moneda,
            'estado' => 'COTIZADA', 'registrado_por' => $usuario->id,
        ]);

        return CotizacionCliente::query()->create([
            'proforma_id' => $proforma->id, 'origen' => 'PROFORMA_ALMACEN',
            'cliente_id' => $cliente->id, 'tipo_orden_id' => $tipo->id,
            'codigo_base' => 'COB-'.$codigo, 'version' => 1,
            'codigo' => 'COB-'.$codigo.'-VRS1',
            'cliente_nombre' => $cliente->razon_social,
            'fecha_emision' => today(), 'moneda' => $moneda,
            'tipo_cambio' => $moneda === 'USD' ? 3.8 : null,
            'subtotal' => $total, 'total' => $total,
            'estado' => $estado, 'cotizado_por' => $usuario->id,
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
