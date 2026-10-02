<?php

namespace App\Http\Controllers;

use App\Models\CotizacionCliente;
use App\Services\Contabilidad\ExportarSaldosCsvService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CuentaCobrarController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $this->filtros($request);
        $cotizaciones = $this->consulta($filtros)
            ->paginate(15)->withQueryString();

        $resumen = [
            'PEN' => ['total' => 0.0, 'cobrado' => 0.0, 'saldo' => 0.0],
            'USD' => ['total' => 0.0, 'cobrado' => 0.0, 'saldo' => 0.0],
        ];
        $consultaResumen = $this->consulta($filtros);
        $consultaResumen->setEagerLoads([]);
        foreach ($consultaResumen->lazy(500) as $cotizacion) {
            if (! isset($resumen[$cotizacion->moneda])) {
                continue;
            }
            $resumen[$cotizacion->moneda]['total'] += (float) $cotizacion->total;
            $resumen[$cotizacion->moneda]['cobrado'] += $cotizacion->montoCobrado();
            $resumen[$cotizacion->moneda]['saldo'] += $cotizacion->saldoPorCobrar();
        }

        return view('cuentas_cobrar.index', compact('cotizaciones', 'resumen'));
    }

    public function csv(Request $request, ExportarSaldosCsvService $exportador): StreamedResponse
    {
        return $exportador->cuentasCobrar($this->consulta($this->filtros($request)));
    }

    /** @return array<string, string> */
    private function filtros(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'origen' => ['nullable', 'in:VENTA_DIRECTA,ORDEN'],
            'moneda' => ['nullable', 'in:PEN,USD'],
            'estado' => ['nullable', 'in:PENDIENTE,PARCIAL,COBRADA'],
        ]);
    }

    /** @param array<string, string> $filtros @return Builder<CotizacionCliente> */
    private function consulta(array $filtros): Builder
    {
        $query = CotizacionCliente::query()->cobrables()
            ->with(['cliente', 'proforma', 'ordenOperacion'])
            ->withSum('cobrosVigentes', 'monto');

        if (filled($filtros['q'] ?? null)) {
            $q = trim($filtros['q']);
            $query->where(fn($documento) => $documento
                ->where('codigo', 'like', "%{$q}%")
                ->orWhere('cliente_nombre', 'like', "%{$q}%")
                ->orWhere('cliente_documento', 'like', "%{$q}%"));
        }
        if (filled($filtros['moneda'] ?? null)) {
            $query->where('moneda', $filtros['moneda']);
        }
        if (($filtros['origen'] ?? null) === 'VENTA_DIRECTA') {
            $query->whereNotNull('proforma_id');
        } elseif (($filtros['origen'] ?? null) === 'ORDEN') {
            $query->whereNull('proforma_id');
        }

        if (filled($filtros['estado'] ?? null)) {
            $cobrado = '(SELECT COALESCE(SUM(c.monto), 0) FROM cobros_cotizacion_cliente c '
                .'WHERE c.cotizacion_cliente_id = cotizaciones_cliente.id AND c.anulado_en IS NULL)';
            match ($filtros['estado']) {
                'PENDIENTE' => $query->whereRaw("{$cobrado} = 0"),
                'PARCIAL' => $query->whereRaw("{$cobrado} > 0 AND {$cobrado} < cotizaciones_cliente.total"),
                'COBRADA' => $query->whereRaw("{$cobrado} >= cotizaciones_cliente.total"),
            };
        }

        return $query->latest('fecha_emision')->latest('id');
    }

    public function show(Request $request, CotizacionCliente $cotizacionCliente): View
    {
        abort_unless($cotizacionCliente->puedeRegistrarseCobro() || $cotizacionCliente->cobros()->exists(), 404);

        $cotizacionCliente->load([
            'cliente', 'proforma', 'ordenOperacion',
            'cobros.registrador', 'cobros.anulador', 'cobrosVigentes',
        ]);

        return view('cuentas_cobrar.show', [
            'cotizacion' => $cotizacionCliente,
            'puedeRegistrarCobro' => $request->user()->puede('contabilidad.registrar_cobros'),
            'habilitada' => $cotizacionCliente->puedeRegistrarseCobro(),
        ]);
    }
}
