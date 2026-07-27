<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MovimientoInventarioController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'tipo' => ['nullable', 'in:ENTRADA,SALIDA'],
            'motivo' => ['nullable', 'string', 'max:40'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $query = DB::table('movimientos_inventario as mi')
            ->join('productos as p', 'p.id', '=', 'mi.producto_id')
            ->join('repisas as r', 'r.id', '=', 'mi.repisa_id')
            ->join('users as u', 'u.id', '=', 'mi.registrado_por')
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
                'mi.fecha_movimiento',
                'p.id as producto_id',
                'p.codigo as producto_codigo',
                'p.descripcion as producto_descripcion',
                'r.id as repisa_id',
                'r.codigo as repisa_codigo',
                'u.username as usuario',
            ]);

        if (! empty($filtros['q'])) {
            $busqueda = trim($filtros['q']);

            $query->where(function ($subquery) use ($busqueda): void {
                $subquery
                    ->where('p.codigo', 'like', "%{$busqueda}%")
                    ->orWhere('p.descripcion', 'like', "%{$busqueda}%")
                    ->orWhere('r.codigo', 'like', "%{$busqueda}%")
                    ->orWhere('mi.origen_tipo', 'like', "%{$busqueda}%");

                if (ctype_digit($busqueda)) {
                    $subquery->orWhere('mi.origen_id', (int) $busqueda);
                }
            });
        }

        if (! empty($filtros['tipo'])) {
            $query->where('mi.tipo_movimiento', $filtros['tipo']);
        }

        if (! empty($filtros['motivo'])) {
            $query->where('mi.motivo', $filtros['motivo']);
        }

        if (! empty($filtros['desde'])) {
            $query->whereDate('mi.fecha_movimiento', '>=', $filtros['desde']);
        }

        if (! empty($filtros['hasta'])) {
            $query->whereDate('mi.fecha_movimiento', '<=', $filtros['hasta']);
        }

        $movimientos = $query
            ->orderByDesc('mi.fecha_movimiento')
            ->orderByDesc('mi.id')
            ->paginate(20)
            ->withQueryString();

        $motivos = DB::table('movimientos_inventario')
            ->whereNotNull('motivo')
            ->distinct()
            ->orderBy('motivo')
            ->pluck('motivo');

        $resumen = DB::table('movimientos_inventario')
            ->whereDate('fecha_movimiento', now()->toDateString())
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                "SUM(CASE WHEN tipo_movimiento = 'ENTRADA' THEN 1 ELSE 0 END) as entradas"
            )
            ->selectRaw(
                "SUM(CASE WHEN tipo_movimiento = 'SALIDA' THEN 1 ELSE 0 END) as salidas"
            )
            ->selectRaw(
                "SUM(CASE WHEN motivo = 'ANULACION_SALIDA' THEN 1 ELSE 0 END) as reversiones"
            )
            ->first();

        return view('movimientos.index', compact(
            'movimientos',
            'motivos',
            'resumen'
        ));
    }

    public function show(int $movimiento): View
    {
        $registro = DB::table('movimientos_inventario as mi')
            ->join('productos as p', 'p.id', '=', 'mi.producto_id')
            ->join('repisas as r', 'r.id', '=', 'mi.repisa_id')
            ->join('users as u', 'u.id', '=', 'mi.registrado_por')
            ->where('mi.id', $movimiento)
            ->select([
                'mi.*',
                'p.codigo as producto_codigo',
                'p.descripcion as producto_descripcion',
                'r.codigo as repisa_codigo',
                'r.descripcion as repisa_descripcion',
                'u.username as usuario',
            ])
            ->first();

        abort_if($registro === null, 404);

        return view('movimientos.show', [
            'movimiento' => $registro,
        ]);
    }
}
