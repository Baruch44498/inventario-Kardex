<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class KardexController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'producto_id' => ['nullable', 'integer', 'exists:productos,id'],
            'repisa_id' => ['nullable', 'integer', 'exists:repisas,id'],
            'tipo' => ['nullable', 'in:ENTRADA,SALIDA,AJUSTE_COSTO'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $valorMovimientoSql = "CASE WHEN mi.tipo_movimiento = 'AJUSTE_COSTO' "
            . 'THEN mi.stock_posterior * (mi.costo_promedio_nuevo - mi.costo_promedio_anterior) '
            . 'ELSE mi.cantidad * COALESCE(mi.costo_unitario, mi.costo_promedio_nuevo, 0) END';
        $saldoValorizadoSql = 'mi.stock_posterior * mi.costo_promedio_nuevo';

        $base = DB::table('movimientos_inventario as mi')
            ->join('productos as p', 'p.id', '=', 'mi.producto_id')
            ->join('unidades_medida as um', 'um.id', '=', 'p.unidad_medida_id')
            ->join('repisas as r', 'r.id', '=', 'mi.repisa_id')
            ->join('users as u', 'u.id', '=', 'mi.registrado_por');

        $this->aplicarFiltros($base, $filtros);

        $resumen = (clone $base)
            ->selectRaw('COUNT(*) as total_movimientos')
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN mi.tipo_movimiento = 'ENTRADA' THEN mi.cantidad ELSE 0 END), 0) as cantidad_entradas"
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN mi.tipo_movimiento = 'SALIDA' THEN mi.cantidad ELSE 0 END), 0) as cantidad_salidas"
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN mi.tipo_movimiento = 'ENTRADA' THEN {$valorMovimientoSql} ELSE 0 END), 0) as valor_entradas"
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN mi.tipo_movimiento = 'SALIDA' THEN {$valorMovimientoSql} ELSE 0 END), 0) as valor_salidas"
            )
            ->first();

        $movimientos = (clone $base)
            ->select([
                'mi.id',
                'mi.inventario_id',
                'mi.tipo_movimiento',
                'mi.motivo',
                'mi.origen_tipo',
                'mi.origen_id',
                'mi.cantidad',
                'mi.stock_anterior',
                'mi.stock_posterior',
                'mi.costo_unitario',
                'mi.costo_promedio_anterior',
                'mi.costo_promedio_nuevo',
                'mi.fecha_movimiento',
                'mi.observacion',
                'p.id as producto_id',
                'p.codigo as producto_codigo',
                'p.descripcion as producto_descripcion',
                'um.codigo as unidad_codigo',
                'r.id as repisa_id',
                'r.codigo as repisa_codigo',
                'u.username as usuario',
                DB::raw("{$valorMovimientoSql} as valor_movimiento"),
                DB::raw("{$saldoValorizadoSql} as saldo_valorizado"),
            ])
            ->orderByDesc('mi.fecha_movimiento')
            ->orderByDesc('mi.id')
            ->paginate(25)
            ->withQueryString();

        $inventarioActualQuery = DB::table('inventarios as i')
            ->join('productos as p', 'p.id', '=', 'i.producto_id')
            ->join('repisas as r', 'r.id', '=', 'i.repisa_id');

        if (! empty($filtros['producto_id'])) {
            $inventarioActualQuery->where('i.producto_id', (int) $filtros['producto_id']);
        }

        if (! empty($filtros['repisa_id'])) {
            $inventarioActualQuery->where('i.repisa_id', (int) $filtros['repisa_id']);
        }

        $inventarioActual = $inventarioActualQuery
            ->selectRaw('COALESCE(SUM(i.stock_actual), 0) as stock_actual')
            ->selectRaw('COALESCE(SUM(i.stock_actual * i.costo_promedio_soles), 0) as valor_actual')
            ->first();

        $productoFiltro = ! empty($filtros['producto_id'])
            ? DB::table('productos')
            ->where('id', (int) $filtros['producto_id'])
            ->select(['id', 'codigo', 'descripcion'])
            ->first()
            : null;

        $repisaFiltro = ! empty($filtros['repisa_id'])
            ? DB::table('repisas')
            ->where('id', (int) $filtros['repisa_id'])
            ->select(['id', 'codigo', 'descripcion'])
            ->first()
            : null;

        return view('kardex.index', compact(
            'movimientos',
            'resumen',
            'inventarioActual',
            'productoFiltro',
            'repisaFiltro'
        ));
    }

    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        if (! empty($filtros['q'])) {
            $busqueda = trim((string) $filtros['q']);

            $query->where(function (Builder $subquery) use ($busqueda): void {
                $subquery
                    ->where('p.codigo', 'like', "%{$busqueda}%")
                    ->orWhere('p.descripcion', 'like', "%{$busqueda}%")
                    ->orWhere('r.codigo', 'like', "%{$busqueda}%")
                    ->orWhere('mi.motivo', 'like', "%{$busqueda}%")
                    ->orWhere('mi.origen_tipo', 'like', "%{$busqueda}%")
                    ->orWhere('u.username', 'like', "%{$busqueda}%");

                if (ctype_digit($busqueda)) {
                    $subquery->orWhere('mi.origen_id', (int) $busqueda);
                }
            });
        }

        if (! empty($filtros['producto_id'])) {
            $query->where('mi.producto_id', (int) $filtros['producto_id']);
        }

        if (! empty($filtros['repisa_id'])) {
            $query->where('mi.repisa_id', (int) $filtros['repisa_id']);
        }

        if (! empty($filtros['tipo'])) {
            $query->where('mi.tipo_movimiento', $filtros['tipo']);
        }

        if (! empty($filtros['desde'])) {
            $query->whereDate('mi.fecha_movimiento', '>=', $filtros['desde']);
        }

        if (! empty($filtros['hasta'])) {
            $query->whereDate('mi.fecha_movimiento', '<=', $filtros['hasta']);
        }
    }
}
