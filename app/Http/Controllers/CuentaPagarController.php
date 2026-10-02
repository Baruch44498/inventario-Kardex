<?php

namespace App\Http\Controllers;

use App\Models\FacturaProveedor;
use App\Services\Contabilidad\ExportarSaldosCsvService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CuentaPagarController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $this->filtros($request);
        $facturas = $this->consulta($filtros)
            ->paginate(15)->withQueryString();

        $resumen = [
            'PEN' => ['vencido' => 0.0, 'proximo' => 0.0, 'posterior' => 0.0, 'sin_fecha' => 0.0],
            'USD' => ['vencido' => 0.0, 'proximo' => 0.0, 'posterior' => 0.0, 'sin_fecha' => 0.0],
        ];
        $hoy = today();
        $limite = $hoy->copy()->addDays(7);
        $consultaResumen = $this->consulta($filtros);
        $consultaResumen->setEagerLoads([]);
        foreach ($consultaResumen->lazy(500) as $factura) {
            $saldo = $factura->saldoPendiente();
            if ($saldo <= 0 || ! isset($resumen[$factura->moneda])) {
                continue;
            }
            $grupo = match (true) {
                $factura->fecha_vencimiento === null => 'sin_fecha',
                $factura->fecha_vencimiento->lt($hoy) => 'vencido',
                $factura->fecha_vencimiento->lte($limite) => 'proximo',
                default => 'posterior',
            };
            $resumen[$factura->moneda][$grupo] += $saldo;
        }

        return view('cuentas_pagar.index', compact('facturas', 'resumen'));
    }

    public function csv(Request $request, ExportarSaldosCsvService $exportador): StreamedResponse
    {
        return $exportador->cuentasPagar($this->consulta($this->filtros($request)));
    }

    /** @return array<string, string> */
    private function filtros(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'in:PENDIENTE,PARCIAL,PAGADA,VENCIDA,POR_VENCER,SIN_VENCIMIENTO'],
            'moneda' => ['nullable', 'in:PEN,USD'],
        ]);
    }

    /** @param array<string, string> $filtros @return Builder<FacturaProveedor> */
    private function consulta(array $filtros): Builder
    {
        $query = FacturaProveedor::query()
            ->where('estado', '!=', 'ANULADA')
            ->with(['proveedor', 'ordenCompra'])
            ->withSum('pagosVigentes', 'monto');

        if (filled($filtros['q'] ?? null)) {
            $q = trim($filtros['q']);
            $query->where(function ($subquery) use ($q): void {
                $subquery->where('serie', 'like', "%{$q}%")
                    ->orWhere('numero', 'like', "%{$q}%")
                    ->orWhereHas('proveedor', fn($proveedor) => $proveedor
                        ->where('razon_social', 'like', "%{$q}%")
                        ->orWhere('ruc', 'like', "%{$q}%"));
            });
        }

        if (filled($filtros['moneda'] ?? null)) {
            $query->where('moneda', $filtros['moneda']);
        }

        if (filled($filtros['estado'] ?? null)) {
            match ($filtros['estado']) {
                'PENDIENTE' => $query->where('estado', 'REGISTRADA'),
                'PARCIAL' => $query->where('estado', 'PARCIAL'),
                'PAGADA' => $query->where('estado', 'PAGADA'),
                'VENCIDA' => $query->whereIn('estado', ['REGISTRADA', 'PARCIAL'])
                    ->whereDate('fecha_vencimiento', '<', today()),
                'POR_VENCER' => $query->whereIn('estado', ['REGISTRADA', 'PARCIAL'])
                    ->whereDate('fecha_vencimiento', '>=', today()->toDateString())
                    ->whereDate('fecha_vencimiento', '<=', today()->addDays(7)->toDateString()),
                'SIN_VENCIMIENTO' => $query->whereIn('estado', ['REGISTRADA', 'PARCIAL'])
                    ->whereNull('fecha_vencimiento'),
            };
        }

        return $query->orderByRaw('CASE WHEN fecha_vencimiento IS NULL THEN 1 ELSE 0 END')
            ->orderBy('fecha_vencimiento')
            ->latest('id');
    }
}
