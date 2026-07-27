<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateInventarioRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventarioController extends Controller
{
    public function index(Request $request): View
    {
        $estadoSql = "
            CASE
                WHEN i.stock_actual <= 0 THEN 'SIN_STOCK'
                WHEN i.stock_actual <= i.stock_minimo THEN 'BAJO_MINIMO'
                WHEN i.stock_maximo IS NOT NULL
                    AND i.stock_actual > i.stock_maximo
                    THEN 'SOBRE_MAXIMO'
                ELSE 'NORMAL'
            END
        ";

        $query = DB::table('inventarios as i')
            ->join('productos as p', 'p.id', '=', 'i.producto_id')
            ->join('unidades_medida as um', 'um.id', '=', 'p.unidad_medida_id')
            ->join('repisas as r', 'r.id', '=', 'i.repisa_id')
            ->select([
                'i.id as id_inventario',
                'i.stock_actual',
                'i.stock_minimo',
                'i.stock_maximo',
                'i.costo_promedio_soles',
                'i.updated_at as actualizado_en',
                'p.id as id_producto',
                'p.codigo as producto_codigo',
                'p.descripcion as producto_descripcion',
                'p.estado as producto_activo',
                'um.codigo as unidad_codigo',
                'r.id as id_repisa',
                'r.codigo as repisa_codigo',
                DB::raw("{$estadoSql} as estado_stock"),
                DB::raw('(i.stock_actual * i.costo_promedio_soles) as valor_total'),
            ]);

        if ($request->filled('q')) {
            $busqueda = trim((string) $request->q);

            $query->where(function ($subquery) use ($busqueda): void {
                $subquery
                    ->where('p.codigo', 'like', "%{$busqueda}%")
                    ->orWhere('p.descripcion', 'like', "%{$busqueda}%")
                    ->orWhere('r.codigo', 'like', "%{$busqueda}%");
            });
        }

        if ($request->filled('repisa')) {
            $query->where('i.repisa_id', (int) $request->repisa);
        }

        if ($request->filled('estado_stock')) {
            $query->whereRaw("({$estadoSql}) = ?", [$request->estado_stock]);
        }

        $inventarios = $query
            ->orderBy('p.codigo')
            ->orderBy('r.codigo')
            ->paginate(15)
            ->withQueryString();

        $repisas = DB::table('repisas')
            ->select(['id as id_repisa', 'codigo', 'descripcion', 'estado'])
            ->where('estado', true)
            ->orderBy('codigo')
            ->get();

        $resumen = DB::table('inventarios')
            ->selectRaw('COUNT(*) as ubicaciones')
            ->selectRaw('SUM(CASE WHEN stock_actual <= 0 THEN 1 ELSE 0 END) as sin_stock')
            ->selectRaw('SUM(CASE WHEN stock_actual > 0 AND stock_actual <= stock_minimo THEN 1 ELSE 0 END) as bajo_minimo')
            ->selectRaw('COALESCE(SUM(stock_actual * costo_promedio_soles), 0) as valor_total')
            ->first();

        return view('inventario.index', compact('inventarios', 'repisas', 'resumen'));
    }

    public function edit(int $inventario): View
    {
        $registro = DB::table('inventarios as i')
            ->join('productos as p', 'p.id', '=', 'i.producto_id')
            ->join('repisas as r', 'r.id', '=', 'i.repisa_id')
            ->join('unidades_medida as um', 'um.id', '=', 'p.unidad_medida_id')
            ->where('i.id', $inventario)
            ->select([
                'i.id as id_inventario',
                'i.producto_id as id_producto',
                'i.repisa_id as id_repisa',
                'i.stock_actual',
                'i.stock_minimo',
                'i.stock_maximo',
                'i.costo_promedio_soles',
                'i.created_at as creado_en',
                'i.updated_at as actualizado_en',
                'p.codigo as producto_codigo',
                'p.descripcion as producto_descripcion',
                'r.codigo as repisa_codigo',
                'um.codigo as unidad_codigo',
            ])
            ->first();

        abort_if($registro === null, 404);

        return view('inventario.edit', ['inventario' => $registro]);
    }

    public function update(UpdateInventarioRequest $request, int $inventario): RedirectResponse
    {
        $datos = $request->validated();

        $existe = DB::table('inventarios')->where('id', $inventario)->exists();
        abort_unless($existe, 404);

        DB::table('inventarios')
            ->where('id', $inventario)
            ->update([
                'stock_minimo' => $datos['stock_minimo'],
                'stock_maximo' => $datos['stock_maximo'] ?? null,
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('inventario.index')
            ->with('success', 'Límites de inventario actualizados correctamente.');
    }
}
