<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MovimientoTesoreriaController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $this->filtros($request);
        $movimientos = $this->movimientos($filtros);

        $resumen = DB::query()->fromSub(clone $movimientos, 'movimientos')
            ->selectRaw('moneda, tipo, SUM(monto) as total')
            ->groupBy('moneda', 'tipo')
            ->get()
            ->groupBy('moneda');

        $registros = $this->ordenados($movimientos)
            ->paginate(20)->withQueryString();

        return view('contabilidad.movimientos', compact('registros', 'resumen'));
    }

    public function csv(Request $request): StreamedResponse
    {
        $movimientos = $this->movimientos($this->filtros($request));

        return response()->streamDownload(function () use ($movimientos): void {
            $salida = fopen('php://output', 'wb');
            if ($salida === false) {
                return;
            }
            try {
                fwrite($salida, "\xEF\xBB\xBF");
                fputcsv($salida, ['Fecha', 'Tipo', 'Documento', 'Tercero', 'Medio', 'Referencia', 'Moneda', 'Importe', 'ID movimiento'], ';', '"', '');
                foreach ($this->ordenados($movimientos)->cursor() as $registro) {
                    $documento = $registro->tipo === 'PAGO'
                        ? trim(($registro->serie ?: '').'-'.$registro->documento, '-')
                        : $registro->documento;
                    fputcsv($salida, [
                        $registro->fecha,
                        $registro->tipo,
                        $this->textoSeguro($documento),
                        $this->textoSeguro($registro->tercero),
                        $this->textoSeguro($registro->medio),
                        $this->textoSeguro($registro->referencia),
                        $registro->moneda,
                        number_format((float) $registro->monto, 4, ',', ''),
                        $registro->tipo.'-'.$registro->movimiento_id,
                    ], ';', '"', '');
                }
            } finally {
                fclose($salida);
            }
        }, 'MOVIMIENTOS_TESORERIA_'.now()->format('Ymd_His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return array<string, string> */
    private function filtros(Request $request): array
    {
        $filtros = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'tipo' => ['nullable', 'in:COBRO,PAGO'],
            'moneda' => ['nullable', 'in:PEN,USD'],
        ]);

        if (filled($filtros['desde'] ?? null) && filled($filtros['hasta'] ?? null)
            && $filtros['desde'] > $filtros['hasta']) {
            throw ValidationException::withMessages(['hasta' => 'La fecha final debe ser igual o posterior a la inicial.']);
        }

        return $filtros;
    }

    /** @param array<string, string> $filtros */
    private function movimientos(array $filtros): Builder
    {
        $cobros = DB::table('cobros_cotizacion_cliente as c')
            ->join('cotizaciones_cliente as q', 'q.id', '=', 'c.cotizacion_cliente_id')
            ->whereNull('c.anulado_en')
            ->selectRaw("'COBRO' as tipo, c.id as movimiento_id, c.fecha_cobro as fecha, c.created_at as registrado_en, c.monto, q.moneda, c.medio_cobro as medio, c.referencia, q.id as documento_id, q.codigo as documento, q.cliente_nombre as tercero");

        $pagos = DB::table('pagos_factura_proveedor as p')
            ->join('facturas_proveedor as f', 'f.id', '=', 'p.factura_proveedor_id')
            ->join('proveedores as v', 'v.id', '=', 'f.proveedor_id')
            ->whereNull('p.anulado_en')
            ->selectRaw("'PAGO' as tipo, p.id as movimiento_id, p.fecha_pago as fecha, p.created_at as registrado_en, p.monto, f.moneda, p.medio_pago as medio, p.referencia, f.id as documento_id, f.numero as documento, COALESCE(v.nombre_comercial, v.razon_social) as tercero")
            ->addSelect('f.serie as serie');

        // UNION exige las mismas columnas y el mismo orden en ambas consultas.
        $cobros->addSelect(DB::raw('NULL as serie'));

        foreach ([$cobros, $pagos] as $query) {
            $alias = $query === $cobros ? 'c' : 'p';
            $fecha = $alias === 'c' ? 'fecha_cobro' : 'fecha_pago';
            $moneda = $alias === 'c' ? 'q.moneda' : 'f.moneda';

            if (filled($filtros['desde'] ?? null)) {
                $query->whereDate("{$alias}.{$fecha}", '>=', $filtros['desde']);
            }
            if (filled($filtros['hasta'] ?? null)) {
                $query->whereDate("{$alias}.{$fecha}", '<=', $filtros['hasta']);
            }
            if (filled($filtros['moneda'] ?? null)) {
                $query->where($moneda, $filtros['moneda']);
            }
        }

        return match ($filtros['tipo'] ?? null) {
            'COBRO' => $cobros,
            'PAGO' => $pagos,
            default => $cobros->unionAll($pagos),
        };
    }

    private function ordenados(Builder $movimientos): Builder
    {
        return DB::query()->fromSub($movimientos, 'movimientos')
            ->orderByDesc('fecha')->orderByDesc('registrado_en')
            ->orderByDesc('tipo')->orderByDesc('movimiento_id');
    }

    private function textoSeguro(?string $texto): string
    {
        $texto = str_replace(["\r", "\n", "\t"], ' ', (string) $texto);

        return preg_match('/^\s*[=+\-@]/u', $texto) ? "'".$texto : $texto;
    }
}
