<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HistorialPrecioProveedorController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'producto_id' => ['nullable', 'integer', 'exists:productos,id'],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedores,id'],
            'moneda' => ['nullable', 'in:PEN,USD'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $query = CotizacionDetalle::query()
            ->with(['producto.unidadMedida', 'cotizacion.proveedor'])
            ->whereHas('cotizacion', fn($q) => $q->where('estado', '!=', 'ANULADA'));

        if (! empty($filters['q'])) {
            $search = trim($filters['q']);

            $query->whereHas('producto', fn($q) => $q
                ->where('codigo', 'like', "%{$search}%")
                ->orWhere('descripcion', 'like', "%{$search}%"));
        }

        if (! empty($filters['producto_id'])) {
            $query->where('producto_id', $filters['producto_id']);
        }

        if (! empty($filters['proveedor_id'])) {
            $providerId = $filters['proveedor_id'];

            $query->whereHas(
                'cotizacion',
                fn($q) => $q->where('proveedor_id', $providerId)
            );
        }

        if (! empty($filters['moneda'])) {
            $currency = $filters['moneda'];

            $query->whereHas(
                'cotizacion',
                fn($q) => $q->where('moneda', $currency)
            );
        }

        if (! empty($filters['desde'])) {
            $date = $filters['desde'];

            $query->whereHas(
                'cotizacion',
                fn($q) => $q->whereDate('fecha_cotizacion', '>=', $date)
            );
        }

        if (! empty($filters['hasta'])) {
            $date = $filters['hasta'];

            $query->whereHas(
                'cotizacion',
                fn($q) => $q->whereDate('fecha_cotizacion', '<=', $date)
            );
        }

        $historial = $query
            ->orderByDesc(
                Cotizacion::query()
                    ->select('fecha_cotizacion')
                    ->whereColumn('cotizaciones.id', 'cotizacion_detalles.cotizacion_id')
                    ->limit(1)
            )
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('historial_precios.index', [
            'historial' => $historial,
            'comparacion' => $this->comparisonQuery($filters)->get(),
            'estadisticas' => $this->statistics($filters),
            'productoFiltro' => ! empty($filters['producto_id'])
                ? Producto::query()->find($filters['producto_id'])
                : null,
            'proveedorFiltro' => ! empty($filters['proveedor_id'])
                ? Proveedor::query()->find($filters['proveedor_id'])
                : null,
        ]);
    }

    private function comparisonQuery(array $filters): Builder
    {
        $net = 'CASE WHEN cd.cantidad > 0 '
            . 'THEN (cd.total / cd.cantidad) * '
            . '(1 - CASE WHEN c.subtotal > 0 '
            . 'THEN c.descuento_global_monto / c.subtotal ELSE 0 END) '
            . 'ELSE cd.precio_unitario * (1 - (cd.descuento_porcentaje / 100)) END';

        $query = DB::table('cotizacion_detalles as cd')
            ->join('cotizaciones as c', 'c.id', '=', 'cd.cotizacion_id')
            ->join('proveedores as p', 'p.id', '=', 'c.proveedor_id')
            ->join('productos as pr', 'pr.id', '=', 'cd.producto_id')
            ->where('c.estado', '!=', 'ANULADA')
            ->selectRaw(
                'p.id as proveedor_id, p.razon_social, p.nombre_comercial, '
                    . 'c.moneda, COUNT(*) as ofertas, '
                    . "MIN({$net}) as precio_minimo, "
                    . "AVG({$net}) as precio_promedio, "
                    . "MAX({$net}) as precio_maximo, "
                    . 'MAX(c.fecha_cotizacion) as ultima_fecha'
            )
            ->groupBy(
                'p.id',
                'p.razon_social',
                'p.nombre_comercial',
                'c.moneda'
            )
            ->orderBy('c.moneda')
            ->orderByRaw("MIN({$net})")
            ->limit(30);

        return $this->applyDbFilters($query, $filters);
    }

    private function statistics(array $filters): array
    {
        $net = 'CASE WHEN cd.cantidad > 0 '
            . 'THEN (cd.total / cd.cantidad) * '
            . '(1 - CASE WHEN c.subtotal > 0 '
            . 'THEN c.descuento_global_monto / c.subtotal ELSE 0 END) '
            . 'ELSE cd.precio_unitario * (1 - (cd.descuento_porcentaje / 100)) END';

        $base = DB::table('cotizacion_detalles as cd')
            ->join('cotizaciones as c', 'c.id', '=', 'cd.cotizacion_id')
            ->join('proveedores as p', 'p.id', '=', 'c.proveedor_id')
            ->join('productos as pr', 'pr.id', '=', 'cd.producto_id')
            ->where('c.estado', '!=', 'ANULADA');

        $base = $this->applyDbFilters($base, $filters);

        $general = (clone $base)
            ->selectRaw(
                'COUNT(*) as ofertas, '
                    . 'COUNT(DISTINCT cd.producto_id) as productos, '
                    . 'COUNT(DISTINCT c.proveedor_id) as proveedores, '
                    . 'MAX(c.fecha_cotizacion) as ultima_fecha'
            )
            ->first();

        $pen = (clone $base)
            ->where('c.moneda', 'PEN')
            ->selectRaw("MIN({$net}) as minimo, AVG({$net}) as promedio")
            ->first();

        $usd = (clone $base)
            ->where('c.moneda', 'USD')
            ->selectRaw("MIN({$net}) as minimo, AVG({$net}) as promedio")
            ->first();

        return [
            'ofertas' => (int) ($general->ofertas ?? 0),
            'productos' => (int) ($general->productos ?? 0),
            'proveedores' => (int) ($general->proveedores ?? 0),
            'ultima_fecha' => $general->ultima_fecha ?? null,
            'pen_minimo' => $pen->minimo !== null ? (float) $pen->minimo : null,
            'pen_promedio' => $pen->promedio !== null ? (float) $pen->promedio : null,
            'usd_minimo' => $usd->minimo !== null ? (float) $usd->minimo : null,
            'usd_promedio' => $usd->promedio !== null ? (float) $usd->promedio : null,
        ];
    }

    private function applyDbFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['q'])) {
            $search = trim($filters['q']);

            $query->where(function ($q) use ($search): void {
                $q->where('pr.codigo', 'like', "%{$search}%")
                    ->orWhere('pr.descripcion', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['producto_id'])) {
            $query->where('cd.producto_id', $filters['producto_id']);
        }

        if (! empty($filters['proveedor_id'])) {
            $query->where('c.proveedor_id', $filters['proveedor_id']);
        }

        if (! empty($filters['moneda'])) {
            $query->where('c.moneda', $filters['moneda']);
        }

        if (! empty($filters['desde'])) {
            $query->whereDate('c.fecha_cotizacion', '>=', $filters['desde']);
        }

        if (! empty($filters['hasta'])) {
            $query->whereDate('c.fecha_cotizacion', '<=', $filters['hasta']);
        }

        return $query;
    }
}
